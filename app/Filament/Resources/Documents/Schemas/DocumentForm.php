<?php

namespace App\Filament\Resources\Documents\Schemas;

use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

class DocumentForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->columnSpanFull()
                    ->required(),
                FileUpload::make('path')
                    ->label('File')
                    ->columnSpanFull()
                    ->disk('s3')
                    ->maxFiles(1)
                    ->maxSize(1024 * 1024 * 50)
                    ->directory('documents')
                    ->getUploadedFileNameForStorageUsing(
                        fn(TemporaryUploadedFile $file) => str(md5(uniqid()) . "." . $file->extension())->toString(),
                    )
                    ->required(),
            ]);
    }
}
