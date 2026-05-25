<?php

namespace App\Http\Resources\Api;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin User */
class UserResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $role = $this->relationLoaded('roles')
            ? $this->roles->pluck('name')->filter()->first()
            : $this->getRoleNames()->first();

        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'role' => $role,
            'phone' => $this->phone,
            'avatar' => null,
            'avatar_url' => null,
        ];
    }
}
