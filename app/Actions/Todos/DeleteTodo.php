<?php

namespace App\Actions\Todos;

use App\Models\Todo;

class DeleteTodo
{
    /**
     * Soft-delete a todo. List item pivots remain (so an undo restores fully); the soft-delete
     * scope on the Todo model hides it from all list queries.
     */
    public function __invoke(Todo $todo): void
    {
        $todo->delete();
    }
}
