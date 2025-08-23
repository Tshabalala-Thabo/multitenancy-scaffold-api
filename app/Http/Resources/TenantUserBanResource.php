<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TenantUserBanResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'reason' => $this->reason,
            'banned_at' => $this->banned_at,
            'user' => [
                'id' => $this->user->id,
                'name' => $this->user->name,
                'last_name' => $this->user->last_name,
                'email' => $this->user->email,
            ],
            'banned_by' => $this->bannedBy ? [
                'id' => $this->bannedBy->id,
                'name' => $this->bannedBy->name,
                'last_name' => $this->bannedBy->last_name,
                'email' => $this->bannedBy->email,
            ] : null,
        ];
    }
}
