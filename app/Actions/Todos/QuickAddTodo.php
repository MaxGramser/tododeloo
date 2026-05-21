<?php

namespace App\Actions\Todos;

use App\Actions\Lists\GetOrCreateDailyList;
use App\Models\Todo;
use App\Models\User;
use App\Support\Workday;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class QuickAddTodo
{
    public function __construct(
        private readonly CreateTodo $createTodo,
        private readonly GetOrCreateDailyList $getOrCreateDailyList,
    ) {}

    /**
     * Create a todo and attach to the appropriate daily list:
     * - workday: today
     * - weekend: next Monday
     * Always also attached to master via CreateTodo.
     *
     * @return array{todo: Todo, target_date: CarbonImmutable}
     */
    public function __invoke(User $user, string $title): array
    {
        return DB::transaction(function () use ($user, $title) {
            $targetDate = Workday::quickAddTargetDate();
            $dailyList = ($this->getOrCreateDailyList)($user, $targetDate);
            $todo = ($this->createTodo)($user, ['title' => $title], $dailyList);

            return ['todo' => $todo, 'target_date' => $targetDate];
        });
    }
}
