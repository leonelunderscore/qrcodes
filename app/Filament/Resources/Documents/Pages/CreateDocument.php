<?php

namespace App\Filament\Resources\Documents\Pages;

use App\Filament\Resources\Documents\DocumentResource;
use App\Jobs\HandleDocument;
use App\Models\Document;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class CreateDocument extends CreateRecord
{
    protected static string $resource = DocumentResource::class;

    public function canCreateAnother(): bool
    {
        return false;
    }

    protected function handleRecordCreation(array $data): Model
    {
        $params = [
            'size' => Storage::disk('s3')->size($data['path']),
            'type' => Storage::disk('s3')->mimeType($data['path']),
        ];

        // Validation que le fichier est bien un PDF
        if ($params['type'] !== 'application/pdf') {
            throw new \InvalidArgumentException('Le fichier doit être un PDF (application/pdf)');
        }

        $data['size'] = $params['size'];
        $data['mime'] = $params['type'];
        $data['user_id'] = auth()->id();
        $data['reference'] = Document::generateReference();
        $data['status'] = 'pending';

        return parent::handleRecordCreation($data);
    }

    protected function afterCreate(): void
    {
        if ($this->record instanceof Document) {
            HandleDocument::dispatch($this->record);
        }
    }
}
