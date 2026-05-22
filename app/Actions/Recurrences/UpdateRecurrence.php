<?php

namespace App\Actions\Recurrences;

use App\Models\Recurrence;

class UpdateRecurrence
{
    /**
     * Change the schedule of an existing recurrence. Lets the user adjust the
     * settings from any instance, every time it comes by. Already-materialized
     * instances are left in place; the new rule takes effect from here on.
     */
    public function __invoke(Recurrence $recurrence, string $rrule): Recurrence
    {
        $recurrence->update([
            'rrule' => $rrule,
            'active' => true,
        ]);

        return $recurrence;
    }
}
