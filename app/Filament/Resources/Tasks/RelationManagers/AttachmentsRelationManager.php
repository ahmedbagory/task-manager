<?php

namespace App\Filament\Resources\Tasks\RelationManagers;

use App\Models\TaskAttachment;
use Filament\Actions\Action;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\HtmlString;

class AttachmentsRelationManager extends RelationManager
{
    protected static string $relationship = 'attachments';

    protected static ?string $title = null;

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return auth()->user()?->can('viewAttachments', $ownerRecord) ?? false;
    }

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('Attachments');
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('original_name')
            ->columns([
                TextColumn::make('original_name')
                    ->label(__('File Name'))
                    ->icon(fn (TaskAttachment $record): string => match (true) {
                        $record->isImage() => 'heroicon-o-photo',
                        $record->isVideo() => 'heroicon-o-film',
                        $record->mime_type === 'application/pdf' => 'heroicon-o-document-text',
                        default => 'heroicon-o-paper-clip',
                    })
                    ->searchable(),
                TextColumn::make('mime_type')
                    ->label(__('MIME'))
                    ->badge()
                    ->color(fn (TaskAttachment $record): string => match (true) {
                        $record->isImage() => 'success',
                        $record->isVideo() => 'info',
                        $record->mime_type === 'application/pdf' => 'danger',
                        default => 'gray',
                    })
                    ->toggleable(),
                TextColumn::make('size')
                    ->label(__('Size'))
                    ->formatStateUsing(fn (TaskAttachment $record): string => $record->humanSize())
                    ->toggleable(),
                TextColumn::make('user.name')
                    ->label(__('Uploaded By'))
                    ->placeholder('-'),
                TextColumn::make('created_at')
                    ->label(__('Created At'))
                    ->dateTime()
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->recordActions([
                Action::make('preview')
                    ->label(__('Preview'))
                    ->icon('heroicon-o-eye')
                    ->color('info')
                    ->modalContent(function (TaskAttachment $record): HtmlString {
                        $url = route('attachments.preview', $record);

                        if ($record->isImage()) {
                            return new HtmlString(
                                '<div class="flex justify-center p-4"><img src="' . e($url) . '" alt="' . e($record->original_name) . '" class="max-h-[70vh] max-w-full rounded-lg object-contain" /></div>'
                            );
                        }

                        if ($record->isVideo()) {
                            return new HtmlString(
                                '<div class="flex justify-center p-4"><video controls class="max-h-[70vh] max-w-full rounded-lg"><source src="' . e($url) . '" type="' . e($record->mime_type) . '" /></video></div>'
                            );
                        }

                        if ($record->mime_type === 'application/pdf') {
                            return new HtmlString(
                                '<div class="p-4"><iframe src="' . e($url) . '" class="h-[70vh] w-full rounded-lg border-0"></iframe></div>'
                            );
                        }

                        return new HtmlString(
                            '<div class="p-8 text-center text-sm text-gray-500 dark:text-gray-400">' . __('Preview not available for this file type.') . '</div>'
                        );
                    })
                    ->modalHeading(fn (TaskAttachment $record): string => $record->original_name ?: __('Preview'))
                    ->modalWidth('5xl')
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel(__('Close'))
                    ->visible(fn (TaskAttachment $record): bool => $record->isPreviewable()),

                Action::make('download')
                    ->label(__('Download'))
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('gray')
                    ->url(fn (TaskAttachment $record): string => route('attachments.download', $record))
                    ->openUrlInNewTab(),
            ])
            ->headerActions([])
            ->bulkActions([])
            ->emptyStateHeading('لا توجد مرفقات')
            ->emptyStateIcon('heroicon-o-paper-clip');
    }
}
