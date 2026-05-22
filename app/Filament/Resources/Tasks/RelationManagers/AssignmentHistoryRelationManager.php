<?php

namespace App\Filament\Resources\Tasks\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class AssignmentHistoryRelationManager extends RelationManager
{
    protected static string $relationship = 'assignmentHistories';

    protected static ?string $title = 'سجل التعيينات';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('action_label')
                    ->label(__('الإجراء'))
                    ->badge()
                    ->color(fn ($record) => match ($record->action) {
                        'assigned' => 'info',
                        'reassigned' => 'warning',
                        'accepted' => 'success',
                        'rejected' => 'danger',
                        'completed' => 'success',
                        'started' => 'primary',
                        default => 'gray',
                    }),
                TextColumn::make('fromUser.name')
                    ->label(__('من'))
                    ->placeholder('—'),
                TextColumn::make('toUser.name')
                    ->label(__('إلى'))
                    ->placeholder('—'),
                TextColumn::make('performedByUser.name')
                    ->label(__('بواسطة'))
                    ->placeholder('—'),
                TextColumn::make('note')
                    ->label(__('ملاحظة'))
                    ->placeholder('—')
                    ->limit(50)
                    ->tooltip(fn ($record) => $record->note),
                TextColumn::make('created_at')
                    ->label(__('التاريخ'))
                    ->dateTime('Y-m-d H:i')
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->paginated(false);
    }
}
