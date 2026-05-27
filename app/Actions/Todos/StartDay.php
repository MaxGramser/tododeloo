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
     * mark it started, carry over the chosen todos, catch up the chosen missed
     * recurrences, and create any new ones. Pre-scheduled todos already on the
     * list are left untouched.
     *
     * @param  array<int>  $carryOverIds
     * @param  array<string>  $newTitles
     * @param  array<int>  $missedRecurringIds  Representative ids of missed recurrences to catch up.
     */
    public function __invoke(
        User $user,
        DateTimeInterface $date,
        array $carryOverIds = [],
        array $newTitles = [],
        array $missedRecurringIds = [],
    ): TodoList {
        return DB::transaction(function () use ($user, $date, $carryOverIds, $newTitles, $missedRecurringIds) {
            $list = ($this->getOrCreateDailyList)($user, $date);
            $list->update(['started_at' => now()]);

            $todos = $user->todos()->active()->whereIn('id', $carryOverIds)->get();
            foreach ($todos as $todo) {
                ($this->addTodoToList)($todo, $list);
            }

            $this->catchUpMissedRecurrences($user, $date, $list, $missedRecurringIds);

            foreach ($newTitles as $title) {
                if (trim($title) !== '') {
                    ($this->createTodo)($user, ['title' => $title], $list);
                }
            }

            return $list->fresh();
        });
    }

    /**
     * Pull every open instance of the chosen missed recurrences onto today's
     * list so they get genuinely caught up, stamping each title with its
     * original date so the duplicated rows stay distinguishable.
     *
     * @param  array<int>  $missedRecurringIds
     */
    private function catchUpMissedRecurrences(User $user, DateTimeInterface $date, TodoList $list, array $missedRecurringIds): void
    {
        if ($missedRecurringIds === []) {
            return;
        }

        $recurrenceIds = $user->todos()
            ->active()
            ->whereNotNull('recurrence_id')
            ->whereIn('id', $missedRecurringIds)
            ->pluck('recurrence_id')
            ->unique();

        foreach ($recurrenceIds as $recurrenceId) {
            $instances = $user->todos()
                ->active()
                ->where('recurrence_id', $recurrenceId)
                ->whereDate('occurred_on', '<', $date)
                ->with('recurrence')
                ->get();

            foreach ($instances as $instance) {
                $base = $instance->recurrence?->title ?? $instance->title;
                $instance->update([
                    'title' => $base.' · '.$instance->occurred_on->locale('nl')->translatedFormat('d M'),
                ]);

                ($this->addTodoToList)($instance, $list);
            }
        }
    }
}
