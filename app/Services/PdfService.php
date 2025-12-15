<?php

namespace App\Services;

use App\Jobs\HandlePartUpload;
use App\Models\Document;
use Illuminate\Support\Facades\Storage;
use setasign\Fpdi\Fpdi;
use setasign\Fpdi\PdfParser\CrossReference\CrossReferenceException;
use setasign\Fpdi\PdfParser\StreamReader;
use setasign\Fpdi\PdfReader\PageBoundaries;
use Symfony\Component\Process\Process;

class PdfService
{
    /**
     * Découpe un PDF en plusieurs fichiers (un par page).
     */
    public function splitPdfByPage(Document $document, string $outputDirectory): array
    {
        if (!Storage::disk('s3')->exists($outputDirectory)) {
            Storage::disk('s3')->makeDirectory($outputDirectory);
        }

        dump("Starting processing of {$document->reference}");

        // 1. Récupération et nettoyage éventuel du PDF source
        $rawBytes = Storage::disk('s3')->get($document->path);

        // Cette méthode s'occupe de vérifier si le PDF est lisible et lance QPDF si besoin
        $pdfBytes = $this->getCleanPdfBytes($rawBytes);

        // On compte les pages sur le fichier propre
        $fpdi = new Fpdi();
        $pageCount = $fpdi->setSourceFile(StreamReader::createByString($pdfBytes));

        dump("Document ready. Pages: {$pageCount}");

        $generatedFiles = [];
        $totalSize = 0;
        $pdfContents = [];

        // 2. Génération des fichiers par page
        for ($pageNo = 1; $pageNo <= $pageCount; $pageNo++) {
            dump("Processing page {$pageNo}/{$pageCount} of {$document->reference}");

            $newPdf = new Fpdi();
            $newPdf->setSourceFile(StreamReader::createByString($pdfBytes));
            $newPdf->AddPage();

            // Importer la page avec liens externes
            $templateId = $newPdf->importPage($pageNo, PageBoundaries::MEDIA_BOX, importExternalLinks: true);
            $newPdf->useTemplate($templateId);

            // Générer le nom du fichier
            $outputFilename = sprintf('%s.pdf', md5(uniqid()));
            $outputPath = $outputDirectory . '/' . $outputFilename;

            // Générer le contenu PDF
            $pdfString = $newPdf->Output('S');
            $size = strlen($pdfString);

            $pdfContents[] = [
                'path' => $outputPath,
                'content' => $pdfString,
                'size' => $size
            ];

            $totalSize += $size;
        }

        dump("Generated {$pageCount} pages");
        dump("Total size: {$totalSize} bytes");

        // 3. Upload batch sur S3
        foreach ($pdfContents as $k => $pdf) {
            HandlePartUpload::dispatch($document, $pdf['path'], base64_encode($pdf['content']), $k + 1, sizeof($pdfContents));
            $generatedFiles[] = $pdf['path'];
        }

        return [
            'pages' => $generatedFiles,
            'page_count' => count($generatedFiles),
            'total_size' => $totalSize
        ];
    }

    /**
     * Tente de lire le PDF. En cas d'erreur CrossReference, tente une réparation via QPDF.
     * Retourne les octets du PDF (originaux ou réparés).
     * * @throws CrossReferenceException|\RuntimeException Si le PDF est illisible même après réparation
     */
    private function getCleanPdfBytes(string $pdfBytes): string
    {
        $fpdi = new Fpdi();

        try {
            // Tentative standard
            $fpdi->setSourceFile(StreamReader::createByString($pdfBytes));
            return $pdfBytes;
        } catch (CrossReferenceException $e) {
            // Si échec et QPDF présent, on tente la réparation
            if ($this->binaryExists('qpdf')) {
                try {
                    dump("Attempting QPDF repair...");
                    $repairedBytes = $this->preprocessWithQpdf($pdfBytes);

                    // Vérification que le fichier réparé est valide
                    $fpdi->setSourceFile(StreamReader::createByString($repairedBytes));
                    dump("QPDF repair successful.");

                    return $repairedBytes;
                } catch (\Throwable $qe) {
                    // Si la réparation échoue, on ne fait rien ici, on laissera l'exception originale être lancée
                    dump("QPDF repair failed: " . $qe->getMessage());
                }
            }

            // Si pas de QPDF ou réparation échouée, on relance l'erreur originale
            throw $e;
        }
    }

    private function binaryExists(string $binary): bool
    {
        try {
            $process = new Process([$binary, '--version']);
            $process->setTimeout(10);
            $process->run();
            return $process->isSuccessful();
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Utilise qpdf pour réécrire le PDF sans object streams et avec données décompressées.
     */
    private function preprocessWithQpdf(string $pdfBytes): string
    {
        $in = tempnam(sys_get_temp_dir(), 'pdf-in-');
        $out = tempnam(sys_get_temp_dir(), 'pdf-out-');

        try {
            file_put_contents($in, $pdfBytes);

            $process = new Process(['qpdf', '--object-streams=disable', '--stream-data=uncompress', $in, $out]);
            $process->setTimeout(60);
            $process->run();

            $this->ensureSuccessful($process, 'qpdf');

            $newBytes = file_get_contents($out);

            if ($newBytes === false || $newBytes === '') {
                throw new \RuntimeException('qpdf produced empty output');
            }

            return $newBytes;
        } finally {
            // Nettoyage des fichiers temporaires dans tous les cas
            @unlink($in);
            @unlink($out);
        }
    }

    private function ensureSuccessful(Process $process, string $tool): void
    {
        if (!$process->isSuccessful()) {
            throw new \RuntimeException(sprintf(
                '%s failed (exit %d): %s',
                $tool,
                $process->getExitCode(),
                $process->getErrorOutput() ?: $process->getOutput()
            ));
        }
    }

    /**
     * Fusionne plusieurs PDFs (chemins S3) en un seul fichier sur S3.
     * Conserve l'orientation et la taille de chaque page.
     *
     * @param array $inputPaths Liste des chemins S3 des fichiers à fusionner
     * @param string $outputPath Chemin de destination sur S3
     * @return string Le chemin du fichier final
     */
    public function mergePdfs(array $inputPaths, string $outputPath): string
    {
        $mergedPdf = new Fpdi();

        foreach ($inputPaths as $path) {
            if (!Storage::disk('s3')->exists($path)) {
                dump("Warning: File not found for merging: {$path}");
                continue;
            }

            $rawBytes = Storage::disk('s3')->get($path);

            try {
                // Nettoyage/Vérification du PDF (QPDF fallback auto)
                $cleanBytes = $this->getCleanPdfBytes($rawBytes);

                // Charger le fichier source dans l'instance de fusion
                $pageCount = $mergedPdf->setSourceFile(StreamReader::createByString($cleanBytes));
            } catch (\Exception $e) {
                dump("Error loading PDF for merge {$path}: " . $e->getMessage());
                // On saute le fichier corrompu pour ne pas casser toute la chaîne
                continue;
            }

            // Importation de toutes les pages de ce fichier
            for ($pageNo = 1; $pageNo <= $pageCount; $pageNo++) {
                $templateId = $mergedPdf->importPage($pageNo);

                // Récupérer la taille et l'orientation (Portrait/Paysage) originales
                $size = $mergedPdf->getTemplateSize($templateId);

                // Ajouter une page avec les dimensions exactes de l'original
                $mergedPdf->AddPage(
                    $size['orientation'],
                    [$size['width'], $size['height']]
                );

                $mergedPdf->useTemplate($templateId);
            }
        }

        // Génération du contenu final
        $mergedContent = $mergedPdf->Output('S');

        // Sauvegarde sur S3
        Storage::disk('s3')->put($outputPath, $mergedContent);

        return $outputPath;
    }

    /**
     * Obtenir le nombre de pages d'un PDF.
     */
    public function getPageCount(Document $document): int
    {
        $rawBytes = Storage::disk('s3')->get($document->path);

        // Utilise la logique centralisée de réparation
        $cleanBytes = $this->getCleanPdfBytes($rawBytes);

        $fpdi = new Fpdi();
        return $fpdi->setSourceFile(StreamReader::createByString($cleanBytes));
    }
}
