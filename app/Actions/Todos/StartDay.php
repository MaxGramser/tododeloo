<?php

namespace App\Actions\Todos;

use App\Actions\Lists\GetOrCreateDailyList;
use App\Models\TodoList;
use App\Models\User;
use DateTimeInterface;
use Illuminate\Support\Facades\DB;

class StartDay
{
    public function __construct(
        private readonly GetOrCreateDailyList $getOrCreateDailyList,
        private readonly AddTodoToList $addTodoToList,
        private readonly CreateTodo $createTodo,
    ) {}

    /**
     * Complete the morning ritual for a date: ensure the daily list exists,
     * mark it started, carry over the chosen todos, and create any new ones.
     * Pre-scheduled todos already on the list are left untouched.
     *
     * @param  array<int>  $carryOverIds
     * @param  array<string>  $newTitles
     */
    public function __invoke(
        User $user,
        DateTimeInterface $date,
        array $carryOverIds = [],
        array $newTitles = [],
    ): TodoList {
        return DB::transaction(function () use ($user, $date, $carryOverIds, $newTitles) {
            $list = ($this->getOrCreateDailyList)($user, $date);
            $list->update(['started_at' => now()]);

            $todos = $user->todos()->active()->whereIn('id', $carryOverIds)->get();
            foreach ($todos as $todo) {
                ($this->addTodoToList)($todo, $list);
            }

            foreach ($newTitles as $title) {
                if (trim($title) !== '') {
                    ($this->createTodo)($user, ['title' => $title], $list);
                }
            }

            return $list->fresh();
        });
    }
}
