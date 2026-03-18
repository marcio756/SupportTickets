<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Transforms Ticket model for API responses.
 * Ensures strict contract adherence for consuming applications (e.g., Mobile Apps).
 */
class TicketResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param Request $request
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'status' => $this->status,
            'source' => $this->source,
            'sender_email' => $this->sender_email,
            'customer' => new UserResource($this->whenLoaded('customer')),
            'assigned_to' => $this->assigned_to, 
            'support' => new UserResource($this->whenLoaded('assignee')),
            'messages' => TicketMessageResource::collection($this->whenLoaded('messages')),
            'tags' => $this->whenLoaded('tags'),
            
            'participants' => UserResource::collection($this->whenLoaded('participants')),
            
            'created_at' => $this->created_at->toISOString(),
            'updated_at' => $this->updated_at->toISOString(),
        ];
    }
}