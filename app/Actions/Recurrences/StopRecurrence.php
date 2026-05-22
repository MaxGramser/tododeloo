<?php

namespace App\Actions\Recurrences;

use App\Models\Recurrence;
use Illuminate\Support\Facades\DB;

class StopRecurrence
{
    /**
     * Stop a recurrence: deactivate it and detach its existing instances so they
     * become plain todos again — they lose the recurring marker and, being
     * normal todos once more, are eligible for carry-over instead of regenerating.
     */
    public function __invoke(Recurrence $recurrence): void
    {
        DB::transaction(function () use ($recurrence) {
            $recurrence->update(['active' => false]);
            $recurrence->todos()->update([
                'recurrence_id' => null,
                'occurred_on' => null,
            ]);
        });
    }
}
