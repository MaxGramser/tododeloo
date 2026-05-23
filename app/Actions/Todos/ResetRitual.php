<?php

namespace App\Actions\Todos;

use App\Models\User;
use DateTimeInterface;

/**
 * Re-open the morning ritual for a date by clearing its daily list's
 * `started_at`. Todos already on the day are kept — they resurface as
 * "al gepland" and re-starting is idempotent. A no-op when no daily list
 * exists yet (the ritual already shows).
 */
class ResetRitual
{
    public function __invoke(User $user, DateTimeInterface $date): void
    {
        $user->lists()
            ->forDate($date)
            ->update(['started_at' => null]);
    }
}
