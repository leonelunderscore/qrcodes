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
                ->icon('heroicon-o-pencil-square')->label('Modifier le document'),
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
                ->label('Télécharger le QR Code')
                ->icon('heroicon-o-qr-code'),
            DeleteAction::make()->label('Supprimer le document')->icon('heroicon-o-trash')->before(function (Document $record) {
                Storage::disk('s3')->delete($record->path);
            }),
        ];
    }
}
