<?php

namespace App\Actions\Todos;

use App\Actions\Lists\GetOrCreateDailyList;
use App\Models\Todo;
use App\Models\TodoList;
use App\Support\Workday;
use Illuminate\Support\Facades\DB;

class AddTodoToToday
{
    public function __construct(
        private readonly GetOrCreateDailyList $getOrCreateDailyList,
        private readonly AddTodoToList $addTodoToList,
    ) {}

    /**
     * Attach an existing todo to today's daily list (or next Monday in weekend).
     * Idempotent — if already on the list, this is a no-op.
     */
    public function __invoke(Todo $todo): TodoList
    {
        return DB::transaction(function () use ($todo) {
            $targetDate = Workday::quickAddTargetDate();
            $dailyList = ($this->getOrCreateDailyList)($todo->user, $targetDate);
            ($this->addTodoToList)($todo, $dailyList);

            return $dailyList;
        });
    }
}
