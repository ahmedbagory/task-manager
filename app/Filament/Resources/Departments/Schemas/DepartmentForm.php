<?php

namespace App\Filament\Resources\Departments\Schemas;

use App\Models\Department;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class DepartmentForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('بيانات القسم / الوحدة')
                    ->components([
                        TextInput::make('name')
                            ->label('الاسم')
                            ->required()
                            ->maxLength(255),
                        Select::make('parent_id')
                            ->label('القسم الرئيسي')
                            ->options(function (?Department $record): array {
                                return Department::query()
                                    ->topLevel()
                                    ->orderedHierarchy()
                                    ->when(
                                        $record,
                                        fn ($query) => $query->whereKeyNot($record->getKey()),
                                    )
                                    ->pluck('name', 'id')
                                    ->all();
                            })
                            ->searchable()
                            ->preload()
                            ->helperText('اتركه فارغاً إذا كان هذا السجل قسماً رئيسياً، أو اختر قسماً رئيسياً لإنشاء وحدة تابعة له.'),
                        TextInput::make('code')
                            ->label('الكود')
                            ->maxLength(255)
                            ->unique(ignoreRecord: true),
                        Textarea::make('description')
                            ->label('الوصف')
                            ->rows(3)
                            ->columnSpanFull(),
                        Toggle::make('is_active')
                            ->label('مفعّل')
                            ->default(true)
                            ->required(),
                    ])
                    ->columns(2),
            ]);
    }
}
