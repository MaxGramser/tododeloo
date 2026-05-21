<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use DateTimeInterface;

/**
 * Workday rules for Tododeloo: Mon-Fri are workdays.
 * Quick-add on weekend lands on next Monday. Carry-over looks at last weekday.
 */
class Workday
{
    /**
     * Date a quick-add should land on: today if weekday, otherwise next Monday.
     */
    public static function quickAddTargetDate(?DateTimeInterface $now = null): CarbonImmutable
    {
        $today = CarbonImmutable::instance($now ?? CarbonImmutable::now())->startOfDay();

        if ($today->isWeekday()) {
            return $today;
        }

        return $today->next(CarbonImmutable::MONDAY);
    }

    /**
     * Date of the last workday strictly before the given date.
     * Monday → Friday. Tuesday-Friday → previous day. Weekend → previous Friday.
     */
    public static function lastWorkdayBefore(DateTimeInterface $date): CarbonImmutable
    {
        $cursor = CarbonImmutable::instance($date)->startOfDay()->subDay();

        while (! $cursor->isWeekday()) {
            $cursor = $cursor->subDay();
        }

        return $cursor;
    }
}
