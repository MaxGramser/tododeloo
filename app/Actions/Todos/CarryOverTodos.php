<?php

namespace App\Actions\Todos;

use App\Actions\Lists\GetOrCreateDailyList;
use App\Models\TodoList;
use App\Models\User;
use DateTimeInterface;
use Illuminate\Support\Facades\DB;

class CarryOverTodos
{
    public function __construct(
        private readonly GetOrCreateDailyList $getOrCreateDailyList,
        private readonly AddTodoToList $addTodoToList,
    ) {}

    /**
     * Attach the given todo ids to the daily list for $date.
     * Used by the morning-ritual: user picks which uncompleted items to carry over.
     *
     * @param  array<int>  $todoIds
     */
    public function __invoke(User $user, DateTimeInterface $date, array $todoIds): TodoList
    {
        return DB::transaction(function () use ($user, $date, $todoIds) {
            $dailyList = ($this->getOrCreateDailyList)($user, $date);

            $todos = $user->todos()->active()->whereIn('id', $todoIds)->get();

            foreach ($todos as $todo) {
                ($this->addTodoToList)($todo, $dailyList);
            }

            return $dailyList->fresh();
        });
    }
}
