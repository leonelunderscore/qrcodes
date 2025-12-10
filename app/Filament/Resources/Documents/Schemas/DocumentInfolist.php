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
                TextEntry::make('name')->columnSpanFull(),
                TextEntry::make('reference'),
                TextEntry::make('mime')->label('Type'),
                TextEntry::make('size')
                    ->formatStateUsing(fn($state) => number_format($state / 1000, thousands_separator: ' ') . " KB"),
                TextEntry::make('created_at')
                    ->dateTime()
                    ->placeholder('-'),
            ]);
    }
}
