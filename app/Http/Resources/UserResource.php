<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Transforms User model into a standardized JSON structure
 */
class UserResource extends JsonResource
{
    /**
     * @param Request $request
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'role' => $this->role,
            'avatar_url' => "https://ui-avatars.com/api/?name=" . urlencode($this->name),
            'chat_time' => [
                'total' => $this->max_chat_time,
                'remaining' => $this->remaining_chat_time,
            ],
        ];
    }
}