<?php

namespace App\Filament\Resources\WhatsappContacts\Schemas;

use App\Services\Departments\DepartmentHierarchyService;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class WhatsappContactForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('بيانات جهة الاتصال')
                    ->description('نموذج مختصر لربط رقم واتساب بالقسم والموقع الافتراضي عند المتابعة أو التحويل إلى مهمة.')
                    ->columns(12)
                    ->components([
                        TextInput::make('name')
                            ->label('الاسم')
                            ->maxLength(255)
                            ->placeholder('اسم الجهة أو الفرع')
                            ->columnSpan(4),
                        TextInput::make('phone')
                            ->label('رقم الهاتف')
                            ->required()
                            ->maxLength(255)
                            ->unique(ignoreRecord: true)
                            ->helperText('بصيغة موحدة مثل: 201555960069')
                            ->placeholder('201555960069')
                            ->columnSpan(4),
                        Select::make('department_id')
                            ->label('القسم / الوحدة الافتراضية')
                            ->options(fn (): array => app(DepartmentHierarchyService::class)->hierarchyOptions())
                            ->searchable()
                            ->preload()
                            ->columnSpan(4),
                        TextInput::make('default_location')
                            ->label('الموقع الافتراضي')
                            ->maxLength(255)
                            ->placeholder('مثال: فرع مدينة نصر')
                            ->columnSpan(6),
                    ]),
            ]);
    }
}
