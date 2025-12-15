<?php

namespace App\Jobs;

use App\Models\Document;
use App\Services\PdfService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;

class HandleDocumentUpdate implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct(
        private readonly Document $document
    )
    {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(PdfService $pdfService): void
    {
        $documentCount = 0;
        $documentSize = 0;
        foreach ($this->document->pages as $page) {
            $documentCount++;
            $documentSize += Storage::disk('s3')->size($page['path']);
        }

        $this->document->update([
            'full_size' => $documentSize,
            'page_count' => $documentCount
        ]);

        $files = Storage::disk('s3')->allFiles('pages/' . $this->document->reference);
        $mainFiles = collect($this->document->pages)->pluck('path')->toArray();

        $intersect = array_diff($files, $mainFiles);

        foreach ($intersect as $file) {
            Storage::disk('s3')->delete($file);
        }
        dump("Merging {$this->document->reference}");
        $pdfService->mergePdfs(
            $mainFiles,
            $this->document->path
        );
    }
}
