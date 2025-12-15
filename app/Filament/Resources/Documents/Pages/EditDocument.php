<?php

namespace App\Filament\Resources\Documents\Pages;

use App\Filament\Resources\Documents\DocumentResource;
use App\Jobs\HandleDocumentUpdate;
use App\Models\Document;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditDocument extends EditRecord
{
    protected static string $resource = DocumentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make()->icon('heroicon-o-eye')->label('View Document'),
            DeleteAction::make()->icon('heroicon-o-trash')->label('Delete Document'),
        ];
    }

    protected function afterSave(): void
    {
        if ($this->record instanceof Document) {
            HandleDocumentUpdate::dispatch($this->record);
        }
    }
}
