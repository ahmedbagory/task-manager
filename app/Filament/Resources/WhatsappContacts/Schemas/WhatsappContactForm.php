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
                Section::make(__('Contact Routing'))
                    ->description('اربط أرقام الهواتف بالقسم أو الوحدة الافتراضية والموقع الافتراضي عند تحويل الرسائل إلى مهام.')
                    ->components([
                        TextInput::make('phone')
                            ->label(__('Phone Number'))
                            ->required()
                            ->maxLength(255)
                            ->unique(ignoreRecord: true)
                            ->helperText(__('Use normalized phone format, for example: 201555960069.')),
                        TextInput::make('name')
                            ->label(__('Contact Name'))
                            ->maxLength(255),
                        Select::make('department_id')
                            ->label('القسم / الوحدة الافتراضية')
                            ->options(fn (): array => app(DepartmentHierarchyService::class)->hierarchyOptions())
                            ->searchable()
                            ->preload(),
                        TextInput::make('default_location')
                            ->label('الموقع الافتراضي')
                            ->maxLength(255)
                            ->placeholder(__('Example: Mega 6')),
                    ])
                    ->columns(2),
            ]);
    }
}
