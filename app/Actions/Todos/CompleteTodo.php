<?php

namespace App\Actions\Todos;

use App\Models\Todo;

class CompleteTodo
{
    public function __invoke(Todo $todo): Todo
    {
        if ($todo->isCompleted()) {
            return $todo;
        }

        $todo->update(['completed_at' => now()]);

        return $todo->fresh();
    }
}
