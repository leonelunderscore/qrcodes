<?php

namespace App\Filament\Resources\Documents\Schemas;

use App\Models\Document;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
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
                    ->visible(fn() => $schema->getOperation() != 'edit')
                    ->label('File')
                    ->columnSpanFull()
                    ->acceptedFileTypes(['application/pdf'])
                    ->disk('s3')
                    ->maxFiles(1)
                    ->maxSize(1024 * 1024 * 50)
                    ->directory('documents')
                    ->getUploadedFileNameForStorageUsing(
                        fn(TemporaryUploadedFile $file) => str(md5(uniqid()) . "." . $file->extension())->toString(),
                    )
                    ->required(),
                Repeater::make('pages')
                    ->columnSpanFull()
                    ->visible(fn() => $schema->getOperation() == 'edit')
                    ->schema([
                        FileUpload::make('path')
                            ->visible(fn() => $schema->getOperation() == 'edit')
                            ->label('File')
                            ->hiddenLabel()
                            ->columnSpanFull()
                            ->acceptedFileTypes(['application/pdf'])
                            ->disk('s3')
                            ->maxFiles(1)
                            ->maxSize(1024 * 1024 * 50)
                            ->directory(fn() => 'pages/' . $schema->getRecord()->reference)
                            ->getUploadedFileNameForStorageUsing(
                                fn(TemporaryUploadedFile $file) => str(md5(uniqid()) . "." . $file->extension())->toString(),
                            )
                            ->required(),
                    ])
                    ->itemLabel(function (array $state, Document $record) {
                        if (empty($state['path']) || empty($record->pages)) {
                            return 'Page 1';
                        }
                        $pages = collect($record->pages);
                        $index = $pages->search(
                            fn ($page) => data_get($page, 'path') === $state['path']
                        );
                        if ($index !== false) {
                            return 'Page ' . ($index + 1);
                        }
                        return 'Page 1';
                    })
                    ->deleteAction(
                        fn(Action $action) => $action->requiresConfirmation()
                    ),
            ]);
    }
}
