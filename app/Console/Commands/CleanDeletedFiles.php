<?php

namespace App\Console\Commands;

use App\Models\Document;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class CleanDeletedFiles extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:clean-deleted-files';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Get deleted files from storage and remove them from database.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $documents = Document::whereNotNull('deleted_at')->where('size', '>', 0)->withTrashed()->get();
        $this->info("Files to remove: {$documents->count()}");
        foreach ($documents as $document) {
            $this->info("Removing {$document->reference} from storage.");
            Storage::disk('s3')->delete($document->path);
            Storage::disk('s3')->deleteDirectory("pages/{$document->reference}");
            $document->update([
                'size' => 0,
                'full_size' => 0,
            ]);
        }
        $this->info("Documents removed");
    }
}
