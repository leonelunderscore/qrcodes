<?php

namespace App\Jobs;

use App\Models\Document;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;

class HandlePartUpload implements ShouldQueue
{
    use Queueable;

    public int $timeout = 600;
    public int $tries = 3;

    /**
     * Create a new job instance.
     */
    public function __construct(
        private readonly Document $document, private readonly string $path, private readonly string $content, private readonly int $partNumber, private readonly int $totalParts
    )
    {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        dump("Uploading {$this->partNumber}/{$this->totalParts}");
        Storage::disk('s3')->put($this->path, base64_decode($this->content));
        dump("Uploaded {$this->path}");
        if ($this->partNumber === $this->totalParts) {
            $files = Storage::disk('s3')->allFiles('pages/' . $this->document->reference);
            $mainFiles = collect($this->document->pages)->pluck('path')->toArray();
            $intersect = array_diff($files, $mainFiles);
            foreach ($intersect as $file) {
                Storage::disk('s3')->delete($file);
            }
        }
    }
}
