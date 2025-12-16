<?php

namespace App\Filament\Resources\Documents\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class DocumentInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('name')->label('Nom')->columnSpanFull(),
                TextEntry::make('page_count')->label('Pages'),
                TextEntry::make('full_size')
                    ->label('Taille totale')
                    ->formatStateUsing(fn($state) => number_format($state / 1000, thousands_separator: ' ') . " KB"),
                TextEntry::make('reference')->label('Référence'),
                TextEntry::make('created_at')
                    ->label('Créé le')
                    ->dateTime()
                    ->placeholder('-'),
            ]);
    }
}
