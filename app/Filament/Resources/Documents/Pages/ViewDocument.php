<?php

namespace App\Filament\Resources\Documents\Pages;

use App\Filament\Resources\Documents\DocumentResource;
use App\Models\Document;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\Facades\Storage;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class ViewDocument extends ViewRecord
{
    protected static string $resource = DocumentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make()
                ->icon('heroicon-o-pencil-square')->label('Edit Document'),
            Action::make('qr')
                ->action(function (Document $record) {
                    return response()->streamDownload(function () use ($record) {
                        echo QrCode::format('png')
                            ->size(1024)
                            ->margin(1)
                            ->eye('circle')
                            ->style('round')
                            ->generate(route('link', ['reference' => $record->reference]));
                    }, $record->reference . '.png', ['Content-Type' => 'image/png']);
                })
                ->color('info')
                ->label('Download QR Code')
                ->icon('heroicon-o-qr-code'),
            Action::make('download')
                ->color('success')
                ->icon('heroicon-o-arrow-down-tray')
                ->url(fn(Document $record) => route('link', ['reference' => $record->reference]))
                ->label('Download Document'),
            DeleteAction::make()->label('Delete Document')->icon('heroicon-o-trash')->before(function (Document $record) {
                Storage::disk('s3')->delete($record->path);
            }),
//            Action::make('handle')
//                ->icon('heroicon-o-document-duplicate')
//                ->label('Handle Document')
//                ->visible(fn(Document $record) => in_array($record->status, ['pending', 'failed']))
//                ->disabled(fn(Document $record) => $record->status === 'processing')
//                ->action(function (Document $record) {
//                    // Double vérification pour éviter la race condition
//                    $record->update(['status' => 'pending']);
//                    $record->refresh();
//                    if (in_array($record->status, ['processing', 'completed'])) {
//                        Notification::make('already_processed')
//                            ->title('Document already processed')
//                            ->warning()
//                            ->body('This document is already being processed or has been completed.')
//                            ->send();
//                        return;
//                    }
//
//                    HandleDocument::dispatch($record);
//
//                    Notification::make('show')
//                        ->title('Document handled')
//                        ->info()
//                        ->body('The document processing has been scheduled successfully.')
//                        ->send();
//                })
        ];
    }
}
