<?php

namespace App\Filament\Widgets;

use App\Models\Document;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;

class DocumentsStats extends BaseWidget
{

    protected function getColumns(): int|array|null
    {
        return 2;
    }

    protected function getCards(): array
    {
        $count = Document::where('user_id', auth()->id())->count();
        $totalBytes = (int)Document::where('user_id', auth()->id())->sum('full_size') + (int)Document::where('user_id', auth()->id())->sum('size');

        return [
            BaseWidget\Stat::make('Documents', number_format($count, 0, ',', ' '))
                ->icon('heroicon-o-document-text'),
            BaseWidget\Stat::make('Total size', self::formatBytes($totalBytes))
                ->icon('heroicon-o-folder')
        ];
    }

    private static function formatBytes(int $bytes): string
    {
        if ($bytes < 1024) {
            return $bytes . ' B';
        }

        $units = ['KB', 'MB', 'GB', 'TB'];
        $bytesFloat = $bytes / 1024; // now in KB
        foreach ($units as $unit) {
            if ($bytesFloat < 1024) {
                return number_format($bytesFloat, $bytesFloat >= 100 ? 0 : 2, ',', ' ') . ' ' . $unit;
            }
            $bytesFloat /= 1024;
        }

        // If extremely large, show in PB
        return number_format($bytesFloat, 2, ',', ' ') . ' PB';
    }
}
