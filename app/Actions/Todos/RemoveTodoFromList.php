<?php

namespace App\Actions\Todos;

use App\Models\Todo;
use App\Models\TodoList;
use InvalidArgumentException;

class RemoveTodoFromList
{
    /**
     * Unlink a todo from a list. Refuses to unlink from a master list — that would orphan the todo.
     * Use DeleteTodo to remove from master (which soft-deletes everywhere).
     */
    public function __invoke(Todo $todo, TodoList $list): void
    {
        if ($list->isMaster()) {
            throw new InvalidArgumentException('Cannot unlink a todo from its master list; delete the todo instead.');
        }

        $list->items()->where('todo_id', $todo->id)->delete();
    }
}
