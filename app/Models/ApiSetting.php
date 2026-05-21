<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'provider',
    'enforce_selected_provider',
    'outbound_enabled',
    'bridge_outbound_target',
    'verify_token',
    'access_token',
    'bridge_secret',
    'phone_number_id',
    'api_base_url',
    'graph_version',
    'message_templates',
])]
class ApiSetting extends Model
{
    protected function casts(): array
    {
        return [
            'enforce_selected_provider' => 'boolean',
            'outbound_enabled' => 'boolean',
            'verify_token' => 'encrypted',
            'access_token' => 'encrypted',
            'bridge_secret' => 'encrypted',
            'message_templates' => 'array',
        ];
    }
}
