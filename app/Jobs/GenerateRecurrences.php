<?php

namespace App\Jobs;

use App\Actions\Recurrences\MaterializeRecurrences;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Automatic registration of recurring todos. Runs on a schedule so each day's
 * instances exist before the user opens the app (the controller's lazy
 * materialization is the safety net for days this never ran).
 */
class GenerateRecurrences implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $daysAhead = 1) {}

    public function handle(MaterializeRecurrences $materializeRecurrences): void
    {
        $today = CarbonImmutable::today();

        User::query()
            ->whereHas('recurrences', fn ($query) => $query->active())
            ->cursor()
            ->each(function (User $user) use ($materializeRecurrences, $today): void {
                for ($offset = 0; $offset <= $this->daysAhead; $offset++) {
                    $materializeRecurrences($user, $today->addDays($offset));
                }
            });
    }
}
