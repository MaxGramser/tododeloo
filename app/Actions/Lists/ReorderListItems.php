<?php

namespace App\Actions\Lists;

use App\Models\TodoList;
use Illuminate\Support\Facades\DB;

class ReorderListItems
{
    /**
     * Assign positions to the given todo ids in the supplied order.
     *
     * @param  array<int>  $orderedTodoIds
     */
    public function __invoke(TodoList $list, array $orderedTodoIds): void
    {
        DB::transaction(function () use ($list, $orderedTodoIds) {
            foreach ($orderedTodoIds as $position => $todoId) {
                $list->items()
                    ->where('todo_id', $todoId)
                    ->update(['position' => $position]);
            }
        });
    }
}
