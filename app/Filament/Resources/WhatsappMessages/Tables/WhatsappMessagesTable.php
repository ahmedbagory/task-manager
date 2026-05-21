<?php

namespace App\Filament\Resources\WhatsappMessages\Tables;

use App\Support\BidiText;
use App\Enums\WhatsappMessageDirection;
use App\Filament\Resources\WhatsappMessages\Actions\ConvertWhatsappMessageToTaskAction;
use App\Models\WhatsappMessage;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class WhatsappMessagesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('created_at')
                    ->label(__('Received At'))
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('direction')
                    ->label(__('Direction'))
                    ->badge()
                    ->formatStateUsing(fn (WhatsappMessageDirection|string $state): string => ($state instanceof WhatsappMessageDirection ? $state : WhatsappMessageDirection::from((string) $state))->label())
                    ->color(fn (WhatsappMessageDirection|string $state): string => ($state instanceof WhatsappMessageDirection ? $state : WhatsappMessageDirection::from((string) $state))->color()),
                TextColumn::make('contact.phone')
                    ->label(__('Contact Phone'))
                    ->state(fn (WhatsappMessage $record): ?string => $record->contact?->phone ?? $record->from_phone ?? $record->to_phone)
                    ->formatStateUsing(fn (?string $state) => BidiText::ltr($state))
                    ->html()
                    ->searchable()
                    ->placeholder('-'),
                TextColumn::make('contact.name')
                    ->label(__('Contact Name'))
                    ->state(fn (WhatsappMessage $record): ?string => $record->contact?->name)
                    ->formatStateUsing(fn (?string $state) => BidiText::auto($state))
                    ->html()
                    ->searchable()
                    ->placeholder('-'),
                TextColumn::make('body')
                    ->label(__('Message Body'))
                    ->limit(80)
                    ->searchable()
                    ->placeholder('-'),
                TextColumn::make('task.task_number')
                    ->label(__('Task #'))
                    ->placeholder('-')
                    ->searchable(),
                TextColumn::make('status')
                    ->label(__('Status'))
                    ->badge()
                    ->color(fn (?string $state): string => match ($state) {
                        'sent', 'delivered', 'read' => 'success',
                        'received' => 'info',
                        'queued_bridge', 'dispatching_bridge' => 'warning',
                        'pending_disabled' => 'gray',
                        'failed', 'failed_configuration', 'failed_exception' => 'danger',
                        default => 'gray',
                    })
                    ->placeholder('-'),
                TextColumn::make('message_type')
                    ->label(__('Message Type'))
                    ->placeholder('-')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('from_phone')
                    ->label(__('From Phone'))
                    ->formatStateUsing(fn (?string $state) => BidiText::ltr($state))
                    ->html()
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->placeholder('-'),
                TextColumn::make('to_phone')
                    ->label(__('To Phone'))
                    ->formatStateUsing(fn (?string $state) => BidiText::ltr($state))
                    ->html()
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->placeholder('-'),
                TextColumn::make('group_name')
                    ->label(__('Group Name'))
                    ->formatStateUsing(fn (?string $state) => BidiText::auto($state))
                    ->html()
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->placeholder('-'),
                TextColumn::make('group_id')
                    ->label(__('Group ID'))
                    ->formatStateUsing(fn (?string $state) => BidiText::ltr($state))
                    ->html()
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->placeholder('-'),
            ])
            ->filters([
                SelectFilter::make('direction')
                    ->options(WhatsappMessageDirection::options()),
                SelectFilter::make('status')
                    ->options(fn (): array => [
                        'received' => __('Received'),
                        'sent' => __('Sent'),
                        'delivered' => __('Delivered'),
                        'read' => __('Read'),
                        'failed' => __('Failed'),
                    ]),
                SelectFilter::make('message_type')
                    ->options(fn (): array => [
                        'text' => __('Text'),
                        'image' => __('Image'),
                        'audio' => __('Audio'),
                        'document' => __('Document'),
                    ]),
            ])
            ->defaultSort('created_at', 'desc')
            ->recordActions([
                ConvertWhatsappMessageToTaskAction::makeViewLinkedTask(),
                ConvertWhatsappMessageToTaskAction::make(),
                ViewAction::make(),
            ])
            ->toolbarActions([]);
    }
}
