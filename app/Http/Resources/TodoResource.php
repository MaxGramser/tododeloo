<?php

namespace App\Http\Resources;

use App\Enums\ListType;
use App\Models\Todo;
use App\Models\TodoList;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Todo
 */
class TodoResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'priority' => $this->priority->value,
            'completed_at' => $this->completed_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'recurrence_id' => $this->recurrence_id,
            'recurrence' => $this->whenLoaded('recurrence', function () {
                if ($this->recurrence === null) {
                    return null;
                }

                $described = $this->recurrence->describe();

                return [
                    'id' => $this->recurrence->id,
                    'rrule' => $this->recurrence->rrule,
                    'active' => $this->recurrence->active,
                    'preset' => $described['preset'],
                    'summary' => $described['label'],
                ];
            }),
            'tags' => $this->whenLoaded('tags', fn () => TagResource::collection($this->tags)->resolve()),
            'position' => $this->whenPivotLoaded('list_items', fn () => $this->pivot->position),
            'list_memberships' => $this->whenLoaded('lists', fn () => $this->lists
                ->filter(fn (TodoList $l) => $l->type !== ListType::Master)
                ->map(fn (TodoList $l) => [
                    'id' => $l->id,
                    'type' => $l->type->value,
                    'label' => self::labelFor($l),
                ])
                ->values()
                ->all()),
            'sub_todos' => $this->whenLoaded('subTodos', fn () => $this->subTodos
                ->map(fn ($s) => [
                    'id' => $s->id,
                    'title' => $s->title,
                    'completed_at' => $s->completed_at?->toIso8601String(),
                    'position' => $s->position,
                ])
                ->all()),
        ];
    }

    private static function labelFor(TodoList $list): string
    {
        if ($list->type === ListType::Custom) {
            return $list->name ?? 'lijst';
        }

        if ($list->date === null) {
            return '';
        }

        $today = CarbonImmutable::today();
        $date = CarbonImmutable::instance($list->date);

        if ($date->isSameDay($today)) {
            return 'vandaag';
        }
        if ($date->isSameDay($today->addDay())) {
            return 'morgen';
        }
        if ($date->isSameDay($today->subDay())) {
            return 'gisteren';
        }

        return $date->translatedFormat('d M');
    }
}
