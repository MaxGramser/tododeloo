<?php

namespace App\Actions\Todos;

use App\Actions\Lists\GetOrCreateDailyList;
use App\Models\Todo;
use App\Models\TodoList;
use DateTimeInterface;
use Illuminate\Support\Facades\DB;

class MoveTodoToDate
{
    public function __construct(
        private readonly GetOrCreateDailyList $getOrCreateDailyList,
        private readonly AddTodoToList $addTodoToList,
    ) {}

    /**
     * Move a todo to the daily list for the given date.
     * If $fromDaily is supplied, the todo is unlinked from it first.
     * Master attachment is untouched.
     */
    public function __invoke(Todo $todo, DateTimeInterface $date, ?TodoList $fromDaily = null): TodoList
    {
        return DB::transaction(function () use ($todo, $date, $fromDaily) {
            if ($fromDaily !== null && ! $fromDaily->isMaster()) {
                $fromDaily->items()->where('todo_id', $todo->id)->delete();
            }

            $target = ($this->getOrCreateDailyList)($todo->user, $date);
            ($this->addTodoToList)($todo, $target);

            return $target;
        });
    }
}
