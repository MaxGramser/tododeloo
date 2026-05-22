<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use DateTimeInterface;
use InvalidArgumentException;
use RRule\RRule;

/**
 * Thin wrapper around php-rrule that answers the only two questions the app
 * cares about: does a recurrence fire on a given day, and when does it fire next.
 */
class RecurrenceSchedule
{
    private RRule $rrule;

    public function __construct(string $rrule, DateTimeInterface $dtstart, ?DateTimeInterface $until = null)
    {
        $rule = $rrule;

        if ($until !== null) {
            $rule .= ';UNTIL='.CarbonImmutable::instance($until)->format('Ymd');
        }

        $this->rrule = new RRule($rule, CarbonImmutable::instance($dtstart)->startOfDay());
    }

    public function occursOn(DateTimeInterface $date): bool
    {
        $day = CarbonImmutable::instance($date)->startOfDay();

        return count($this->rrule->getOccurrencesBetween($day, $day->endOfDay())) > 0;
    }

    public function nextOnOrAfter(DateTimeInterface $date): ?CarbonImmutable
    {
        $occurrences = $this->rrule->getOccurrencesAfter(
            CarbonImmutable::instance($date)->startOfDay(),
            true,
            1,
        );

        return isset($occurrences[0])
            ? CarbonImmutable::instance($occurrences[0])->startOfDay()
            : null;
    }

    public static function isValid(string $rrule): bool
    {
        // Cheap structural guard so blatantly malformed input never reaches the
        // parser (its error path is fragile under repeated use in some PHP builds).
        if (! preg_match('/\bFREQ=(DAILY|WEEKLY|MONTHLY|YEARLY)\b/', $rrule)) {
            return false;
        }

        try {
            new RRule($rrule, CarbonImmutable::today());

            return true;
        } catch (InvalidArgumentException) {
            return false;
        }
    }
}
