<?php

namespace App\Filament\Resources\WhatsappContacts\Tables;

use App\Services\Departments\DepartmentHierarchyService;
use App\Support\BidiText;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class WhatsappContactsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with(['department', 'user', 'latestMessage'])->withCount('messages'))
            ->columns([
                TextColumn::make('name')
                    ->label('الاسم')
                    ->state(fn ($record): string => $record->displayName())
                    ->formatStateUsing(fn (?string $state) => BidiText::auto($state))
                    ->html()
                    ->searchable()
                    ->sortable()
                    ->description(fn ($record): ?string => $record->user?->name ? 'الموظف المرتبط: ' . $record->user->name : null),
                TextColumn::make('phone')
                    ->label('الهاتف')
                    ->formatStateUsing(fn (?string $state) => BidiText::ltr($state))
                    ->html()
                    ->searchable()
                    ->sortable(),
                TextColumn::make('department.hierarchy_name')
                    ->label('القسم / الوحدة')
                    ->toggleable()
                    ->placeholder('—'),
                TextColumn::make('default_location')
                    ->label('الموقع الافتراضي')
                    ->toggleable()
                    ->placeholder('—'),
                TextColumn::make('status')
                    ->label('الحالة')
                    ->state(fn ($record): string => $record->statusLabel())
                    ->badge()
                    ->color(fn ($record): string => $record->statusColor()),
                TextColumn::make('latest_message')
                    ->label('آخر رسالة')
                    ->state(fn ($record): string => $record->latestMessagePreview())
                    ->description(fn ($record): string => $record->last_message_at?->diffForHumans() ?? 'بدون تاريخ')
                    ->wrap()
                    ->limit(70),
            ])
            ->filters([
                SelectFilter::make('department_id')
                    ->label('القسم / الوحدة')
                    ->options(fn (): array => app(DepartmentHierarchyService::class)->hierarchyOptions()),
            ])
            ->defaultSort('last_message_at', 'desc')
            ->recordActions([
                ViewAction::make()->label('عرض'),
                EditAction::make()->label('تعديل'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()->label('حذف المحدد'),
                ]),
            ]);
    }
}
