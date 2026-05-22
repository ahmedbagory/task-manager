<?php

namespace App\Filament\Resources\Departments\Schemas;

use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class DepartmentInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('تفاصيل القسم / الوحدة')
                    ->components([
                        TextEntry::make('hierarchy_name')
                            ->label('الاسم'),
                        TextEntry::make('code')
                            ->label('الكود')
                            ->placeholder('-'),
                        TextEntry::make('level_label')
                            ->label('النوع')
                            ->badge(),
                        TextEntry::make('parent.name')
                            ->label('القسم الرئيسي')
                            ->placeholder('—'),
                        IconEntry::make('is_active')
                            ->label('مفعّل')
                            ->boolean(),
                        TextEntry::make('children_count')
                            ->label('الوحدات التابعة')
                            ->state(fn ($record) => $record->children()->count()),
                        TextEntry::make('users_count')
                            ->label('الموظفون')
                            ->state(fn ($record) => $record->users()->count()),
                        TextEntry::make('tasks_count')
                            ->label('المهام')
                            ->state(fn ($record) => $record->tasks()->count()),
                        TextEntry::make('description')
                            ->label('الوصف')
                            ->placeholder('-')
                            ->columnSpanFull(),
                        TextEntry::make('created_at')
                            ->label('تاريخ الإنشاء')
                            ->dateTime()
                            ->placeholder('-'),
                        TextEntry::make('updated_at')
                            ->label('آخر تحديث')
                            ->dateTime()
                            ->placeholder('-'),
                    ])
                    ->columns(3),
            ]);
    }
}
