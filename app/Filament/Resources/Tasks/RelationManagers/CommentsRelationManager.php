<?php

namespace App\Filament\Resources\Tasks\RelationManagers;

use App\Support\Rbac;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class CommentsRelationManager extends RelationManager
{
    protected static string $relationship = 'comments';

    protected static ?string $title = null;

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return auth()->user()?->can('view', $ownerRecord) ?? false;
    }

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('Comments');
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('comment')
            ->columns([
                TextColumn::make('user.name')
                    ->label(__('By'))
                    ->placeholder('-'),
                TextColumn::make('comment')
                    ->wrap()
                    ->limit(100),
                IconColumn::make('is_internal')
                    ->label(__('Internal'))
                    ->boolean(),
                TextColumn::make('created_at')
                    ->dateTime(),
            ])
            ->defaultSort('created_at', 'desc')
            ->headerActions([
                CreateAction::make()
                    ->label(__('Add Comment'))
                    ->authorize(fn (): bool => auth()->user()?->can('comment', $this->getOwnerRecord()) ?? false)
                    ->schema([
                        Textarea::make('comment')
                            ->required()
                            ->rows(3)
                            ->columnSpanFull(),
                        Toggle::make('is_internal')
                            ->label(__('Internal comment'))
                            ->visible(fn (): bool => ! (auth()->user()?->hasRole(Rbac::EMPLOYEE) ?? false))
                            ->default(false),
                    ])
                    ->mutateDataUsing(fn (array $data): array => [
                        ...$data,
                        'user_id' => auth()->id(),
                    ]),
            ])
            ->recordActions([])
            ->toolbarActions([]);
    }
}
