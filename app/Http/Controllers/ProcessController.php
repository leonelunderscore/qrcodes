<?php

namespace App\Http\Controllers;

use App\Models\Document;
use Illuminate\Support\Facades\Storage;

class ProcessController extends Controller
{
    public function download(string $reference)
    {
        $document = Document::where('reference', $reference)->firstOrFail();
        $parts = explode('.', $document->path);
        $filename = str($document->name)->slug()->toString() . '.' . $parts[count($parts) - 1];
        return response(Storage::disk('s3')->get($document->path))
            ->header('Content-Type', $document->mime)
            ->header('Content-Disposition', 'inline; filename="' . mb_strtolower($filename) . '"');
    }
}
