<?php

namespace App\Actions\Recurrences;

use App\Actions\Lists\GetOrCreateDailyList;
use App\Actions\Todos\AddTodoToList;
use App\Models\Recurrence;
use App\Models\Todo;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class MaterializeRecurrences
{
    public function __construct(
        private readonly GetOrCreateDailyList $getOrCreateDailyList,
        private readonly AddTodoToList $addTodoToList,
    ) {}

    /**
     * Ensure every active recurrence that fires on $date has a todo instance
     * attached to that day's daily list. Idempotent: the (recurrence_id,
     * occurred_on) unique index plus an existence check guarantee one instance
     * per recurrence per day, even across concurrent calls.
     *
     * @return int Number of instances created.
     */
    public function __invoke(User $user, CarbonImmutable $date): int
    {
        $day = $date->startOfDay();

        $due = $user->recurrences()
            ->active()
            ->get()
            ->filter(fn (Recurrence $recurrence): bool => $recurrence->schedule()->occursOn($day));

        if ($due->isEmpty()) {
            return 0;
        }

        return DB::transaction(function () use ($user, $day, $due): int {
            $dailyList = ($this->getOrCreateDailyList)($user, $day);
            $created = 0;

            foreach ($due as $recurrence) {
                if ($this->alreadyMaterialized($recurrence, $day)) {
                    continue;
                }

                $todo = Todo::create([
                    'user_id' => $user->id,
                    'recurrence_id' => $recurrence->id,
                    'occurred_on' => $day,
                    'title' => $recurrence->title,
                    'priority' => $recurrence->priority,
                ]);

                ($this->addTodoToList)($todo, $dailyList);

                if ($recurrence->last_generated_on === null || $recurrence->last_generated_on->lt($day)) {
                    $recurrence->update(['last_generated_on' => $day]);
                }

                $created++;
            }

            return $created;
        });
    }

    private function alreadyMaterialized(Recurrence $recurrence, CarbonImmutable $day): bool
    {
        return Todo::withTrashed()
            ->where('recurrence_id', $recurrence->id)
            ->whereDate('occurred_on', $day)
            ->exists();
    }
}
