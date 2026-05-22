<?php

namespace App\Actions\Recurrences;

use App\Models\Recurrence;
use App\Models\Todo;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class CreateRecurrence
{
    /**
     * Turn an existing todo into a recurring one. A template is created from the
     * todo (title + priority), and the todo itself is claimed as the first
     * materialized instance on $dtstart so the current day is never duplicated.
     * Future days are filled in by MaterializeRecurrences.
     */
    public function __invoke(Todo $todo, string $rrule, CarbonImmutable $dtstart): Recurrence
    {
        return DB::transaction(function () use ($todo, $rrule, $dtstart) {
            $start = $dtstart->startOfDay();

            $recurrence = Recurrence::create([
                'user_id' => $todo->user_id,
                'title' => $todo->title,
                'priority' => $todo->priority,
                'rrule' => $rrule,
                'dtstart' => $start,
                'active' => true,
                'last_generated_on' => $start,
            ]);

            $todo->update([
                'recurrence_id' => $recurrence->id,
                'occurred_on' => $start,
            ]);

            return $recurrence;
        });
    }
}
