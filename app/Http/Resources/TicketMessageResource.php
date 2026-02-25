<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

/**
 * Transforms TicketMessage model for API responses
 */
class TicketMessageResource extends JsonResource
{
    /**
     * @param Request $request
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'message' => $this->message,
            // CORREÇÃO: Lê a relação correta 'sender' que é injetada no Controller
            'sender' => new UserResource($this->whenLoaded('sender')),
            'attachment_url' => $this->attachment_path ? Storage::url($this->attachment_path) : null,
            'created_at' => $this->created_at->toISOString(),
        ];
    }
}