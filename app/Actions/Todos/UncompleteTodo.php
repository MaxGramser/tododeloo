<?php

namespace App\Actions\Todos;

use App\Models\Todo;

class UncompleteTodo
{
    public function __invoke(Todo $todo): Todo
    {
        if (! $todo->isCompleted()) {
            return $todo;
        }

        $todo->update(['completed_at' => null]);

        return $todo->fresh();
    }
}
