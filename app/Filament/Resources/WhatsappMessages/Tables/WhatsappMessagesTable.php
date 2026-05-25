<?php

namespace App\Filament\Resources\WhatsappMessages\Tables;

use App\Enums\WhatsappMessageDirection;
use App\Filament\Resources\WhatsappMessages\Actions\ConvertWhatsappMessageToTaskAction;
use App\Models\WhatsappMessage;
use App\Support\BidiText;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class WhatsappMessagesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('created_at')
                    ->label('تاريخ الاستلام')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('direction')
                    ->label('الاتجاه')
                    ->badge()
                    ->toggleable()
                    ->formatStateUsing(fn (WhatsappMessageDirection|string $state): string => ($state instanceof WhatsappMessageDirection ? $state : WhatsappMessageDirection::from((string) $state))->label())
                    ->color(fn (WhatsappMessageDirection|string $state): string => ($state instanceof WhatsappMessageDirection ? $state : WhatsappMessageDirection::from((string) $state))->color()),
                TextColumn::make('contact.phone')
                    ->label('رقم الهاتف')
                    ->state(fn (WhatsappMessage $record): ?string => $record->contact?->phone ?? $record->from_phone ?? $record->to_phone)
                    ->formatStateUsing(fn (?string $state) => BidiText::ltr($state))
                    ->html()
                    ->searchable(query: function (Builder $query, string $search): void {
                        $query->whereHas('contact', fn (Builder $q) => $q->where('phone', 'like', "%{$search}%"))
                            ->orWhere('from_phone', 'like', "%{$search}%")
                            ->orWhere('to_phone', 'like', "%{$search}%");
                    })
                    ->toggleable()
                    ->placeholder('-'),
                TextColumn::make('contact.name')
                    ->label('اسم جهة الاتصال')
                    ->state(fn (WhatsappMessage $record): ?string => $record->contact?->name)
                    ->formatStateUsing(fn (?string $state) => BidiText::auto($state))
                    ->html()
                    ->searchable(query: function (Builder $query, string $search): void {
                        $query->whereHas('contact', fn (Builder $q) => $q->where('name', 'like', "%{$search}%"));
                    })
                    ->toggleable()
                    ->placeholder('-'),
                TextColumn::make('body')
                    ->label('نص الرسالة')
                    ->limit(80)
                    ->searchable()
                    ->toggleable()
                    ->placeholder('-'),
                TextColumn::make('task.id')
                    ->label('رقم المهمة')
                    ->formatStateUsing(fn ($state, WhatsappMessage $record): string => $record->task?->displayNumber() ?? '-')
                    ->placeholder('-')
                    ->toggleable()
                    ->searchable(),
                TextColumn::make('status')
                    ->label('الحالة')
                    ->badge()
                    ->toggleable()
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
                    ->label('نوع الرسالة')
                    ->placeholder('-')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('from_phone')
                    ->label('من رقم')
                    ->formatStateUsing(fn (?string $state) => BidiText::ltr($state))
                    ->html()
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->placeholder('-'),
                TextColumn::make('to_phone')
                    ->label('إلى رقم')
                    ->formatStateUsing(fn (?string $state) => BidiText::ltr($state))
                    ->html()
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->placeholder('-'),
                TextColumn::make('group_name')
                    ->label('اسم المجموعة')
                    ->formatStateUsing(fn (?string $state) => BidiText::auto($state))
                    ->html()
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->placeholder('-'),
                TextColumn::make('group_id')
                    ->label('معرّف المجموعة')
                    ->formatStateUsing(fn (?string $state) => BidiText::ltr($state))
                    ->html()
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->placeholder('-'),
            ])
            ->filters([
                SelectFilter::make('direction')
                    ->label('الاتجاه')
                    ->options(WhatsappMessageDirection::options()),
                SelectFilter::make('status')
                    ->label('الحالة')
                    ->options([
                        'received' => 'مستلمة',
                        'sent' => 'مرسلة',
                        'delivered' => 'تم التسليم',
                        'read' => 'مقروءة',
                        'failed' => 'فشلت',
                    ]),
                SelectFilter::make('message_type')
                    ->label('نوع الرسالة')
                    ->options([
                        'text' => 'نص',
                        'image' => 'صورة',
                        'audio' => 'صوت',
                        'document' => 'مستند',
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
