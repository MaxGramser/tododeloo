<?php

namespace App\Actions\Lists;

use App\Enums\SortMode;
use App\Models\TodoList;
use Illuminate\Support\Facades\DB;

class UpdateListSortMode
{
    /**
     * When switching to manual, freeze the current visible order into position.
     *
     * @param  array<int>|null  $currentlyVisibleTodoIds  Ordered as the UI shows them right now.
     */
    public function __invoke(TodoList $list, SortMode $sortMode, ?array $currentlyVisibleTodoIds = null): void
    {
        DB::transaction(function () use ($list, $sortMode, $currentlyVisibleTodoIds) {
            $list->update(['sort_mode' => $sortMode]);

            if ($sortMode === SortMode::Manual && $currentlyVisibleTodoIds !== null) {
                foreach ($currentlyVisibleTodoIds as $position => $todoId) {
                    $list->items()
                        ->where('todo_id', $todoId)
                        ->update(['position' => $position]);
                }
            }
        });
    }
}
