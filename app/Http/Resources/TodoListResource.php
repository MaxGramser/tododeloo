<?php

namespace App\Http\Resources;

use App\Models\TodoList;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin TodoList
 */
class TodoListResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type->value,
            'name' => $this->name,
            'date' => $this->date?->toDateString(),
            'sort_mode' => $this->sort_mode->value,
            'todos' => $this->whenLoaded('todos', fn () => TodoResource::collection($this->todos)->resolve()),
        ];
    }
}
