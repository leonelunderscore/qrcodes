<?php

namespace App\Jobs;

use App\Models\Document;
use App\Services\PdfService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class HandleDocument implements ShouldQueue
{
    use Queueable;

    public int $timeout = 600;

    /**
     * Create a new job instance.
     */
    public function __construct(
        private readonly Document $document
    )
    {
    }

    /**
     * Execute the job.
     */
    public function handle(PdfService $pdfService): void
    {
        try {
            // Vérifier si déjà en traitement ou complété
            if (in_array($this->document->status, ['processing', 'completed'])) {
                Log::info("Document {$this->document->reference} already {$this->document->status}, skipping.");
                return;
            }

            // Marquer comme en traitement
            $this->document->update(['status' => 'processing']);

            // Séparer le PDF
            $result = $pdfService->splitPdfByPage(
                $this->document,
                'pages/' . $this->document->reference
            );

            dump($result);

            // Mettre à jour le document avec les résultats
            $this->document->update([
                'page_count' => $result['page_count'],
                'pages' => array_map(fn($page) => ['path' => $page], $result['pages']),
                'full_size' => $result['total_size'],
                'status' => 'completed'
            ]);

            Log::info("Document {$this->document->reference} processed successfully: {$result['page_count']} pages, {$result['total_size']} bytes");

        } catch (\Throwable $e) {
            // Marquer comme échoué
            $this->document->update(['status' => 'failed']);

            Log::error("Failed to process document {$this->document->reference}: {$e->getMessage()}", [
                'exception' => $e,
                'document_id' => $this->document->id,
            ]);

            // Nettoyer les fichiers partiels si existants
            $this->cleanupPartialFiles();

            throw $e;
        }
    }

    /**
     * Nettoyer les fichiers partiels en cas d'échec
     */
    private function cleanupPartialFiles(): void
    {
        try {
            $directory = 'pages/' . $this->document->reference;
            if (Storage::disk('s3')->exists($directory)) {
                $files = Storage::disk('s3')->files($directory);
                Storage::disk('s3')->delete($files);
                Storage::disk('s3')->deleteDirectory($directory);
            }
        } catch (\Throwable $e) {
            Log::warning("Failed to cleanup partial files for document {$this->document->reference}: {$e->getMessage()}");
        }
    }

    /**
     * Gérer l'échec définitif du job
     */
    public function failed(\Throwable $exception): void
    {
        $this->document->update(['status' => 'failed']);

        Log::error("Document {$this->document->reference} processing failed permanently", [
            'exception' => $exception,
            'document_id' => $this->document->id,
        ]);
    }
}
