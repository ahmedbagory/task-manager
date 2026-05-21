<?php

namespace App\Filament\Resources\WhatsappContacts\Schemas;

use App\Models\Department;
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
                    ->description(__('Map phone numbers to default branch/location used while converting inbox messages to tasks.'))
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
                            ->label(__('Default Department (Optional)'))
                            ->options(fn (): array => Department::query()
                                ->where('is_active', true)
                                ->orderBy('name')
                                ->pluck('name', 'id')
                                ->all())
                            ->searchable()
                            ->preload(),
                        TextInput::make('default_location')
                            ->label(__('Default Branch / Location'))
                            ->maxLength(255)
                            ->placeholder(__('Example: Mega 6')),
                    ])
                    ->columns(2),
            ]);
    }
}
