<?php

namespace App\Actions\Todos;

use App\Models\Todo;
use App\Models\TodoList;
use Illuminate\Support\Facades\DB;

class AddTodoToList
{
    /**
     * Attach a todo to a list. Idempotent: if already attached, this is a no-op.
     * Position defaults to "next free" (end of the list).
     */
    public function __invoke(Todo $todo, TodoList $list, ?int $position = null): void
    {
        DB::transaction(function () use ($todo, $list, $position) {
            if ($list->items()->where('todo_id', $todo->id)->exists()) {
                return;
            }

            $resolvedPosition = $position ?? ((int) $list->items()->max('position') + 1);

            $list->items()->create([
                'todo_id' => $todo->id,
                'position' => $resolvedPosition,
                'added_at' => now(),
            ]);
        });
    }
}
