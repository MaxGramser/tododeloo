<?php

namespace App\Actions\SubTodos;

use App\Models\SubTodo;
use App\Models\Todo;
use Illuminate\Support\Facades\DB;

class CreateSubTodo
{
    /**
     * Adds a subtodo to the end of the parent's list.
     * If the parent was already done, re-open it (a new sub means there's work).
     */
    public function __invoke(Todo $todo, string $title): SubTodo
    {
        return DB::transaction(function () use ($todo, $title) {
            $position = ((int) $todo->subTodos()->max('position')) + 1;

            $sub = $todo->subTodos()->create([
                'title' => $title,
                'position' => $position,
            ]);

            if ($todo->isCompleted()) {
                $todo->update(['completed_at' => null]);
            }

            return $sub;
        });
    }
}
