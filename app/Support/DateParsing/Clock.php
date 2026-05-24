<?php

namespace App\Support\DateParsing;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * Pure date arithmetic shared by the date rules. Every method takes the
 * reference "today" explicitly and returns a fresh immutable date — no clock
 * reads, no state — so rules stay deterministic and easy to test.
 */
final class Clock
{
    /** Upcoming occurrence of an ISO weekday, today included. */
    public static function upcomingWeekday(CarbonImmutable $today, int $iso): CarbonImmutable
    {
        $monday = $today->startOfWeek(CarbonInterface::MONDAY);
        $candidate = $monday->addDays($iso - 1);

        return $candidate->lt($today) ? $candidate->addWeek() : $candidate;
    }

    /** A weekday in this week ($weeks=0) or a future week relative to today. */
    public static function weekOffsetWeekday(CarbonImmutable $today, int $weeks, int $iso): CarbonImmutable
    {
        return $today->startOfWeek(CarbonInterface::MONDAY)->addWeeks($weeks)->addDays($iso - 1);
    }

    /** The $n-th occurrence of ISO weekday $iso within $base's month (n<0 → last). */
    public static function nthWeekday(CarbonImmutable $base, int $n, int $iso): CarbonImmutable
    {
        if ($n < 0) {
            $date = $base->endOfMonth()->startOfDay();
            while ($date->dayOfWeekIso !== $iso) {
                $date = $date->subDay();
            }

            return $date;
        }

        $date = $base->startOfMonth();
        while ($date->dayOfWeekIso !== $iso) {
            $date = $date->addDay();
        }

        return $date->addWeeks($n - 1)->startOfDay();
    }

    /** Next $n-th $iso-weekday on or after today, repeating monthly. */
    public static function upcomingNthWeekday(CarbonImmutable $today, int $n, int $iso): CarbonImmutable
    {
        $candidate = self::nthWeekday($today, $n, $iso);
        if ($candidate->lt($today) || $candidate->month !== $today->month) {
            $candidate = self::nthWeekday($today->startOfMonth()->addMonth(), $n, $iso);
        }

        return $candidate;
    }

    /**
     * Next $n-th $iso-weekday at the start of a calendar quarter (Jan/Apr/Jul/Oct).
     * Uses the current quarter if its occurrence is still upcoming, else the next
     * one — so a MONTHLY;INTERVAL=3 rule anchored here lands on every quarter start.
     */
    public static function upcomingQuarterlyNthWeekday(CarbonImmutable $today, int $n, int $iso): CarbonImmutable
    {
        $quarterStartMonth = (int) (floor(($today->month - 1) / 3) * 3) + 1;
        $base = $today->startOfMonth()->month($quarterStartMonth);

        $candidate = self::nthWeekday($base, $n, $iso);
        if ($candidate->lt($today)) {
            $candidate = self::nthWeekday($base->addMonths(3), $n, $iso);
        }

        return $candidate;
    }

    /** A day within $base's month: a number, "eerste" (1st) or "laatste" (last). */
    public static function dayOfMonth(CarbonImmutable $base, string $token): CarbonImmutable
    {
        $token = mb_strtolower($token);

        if ($token === 'laatste') {
            return $base->endOfMonth()->startOfDay();
        }

        $day = $token === 'eerste' ? 1 : (int) $token;
        $day = max(1, min($day, $base->daysInMonth));

        return $base->day($day)->startOfDay();
    }

    /** A concrete day/month, rolling to next year when an unqualified date already passed. */
    public static function buildDate(CarbonImmutable $today, int $day, int $month, ?int $year): CarbonImmutable
    {
        $date = CarbonImmutable::create($year ?? $today->year, $month, $day, 0, 0, 0, $today->timezone);

        if ($year === null && $date->lt($today)) {
            $date = $date->addYear();
        }

        return $date;
    }

    /**
     * The next date on or after today whose ISO weekday is allowed.
     *
     * @param  list<int>  $isoDays
     */
    public static function nextDayIn(CarbonImmutable $today, array $isoDays): CarbonImmutable
    {
        $date = $today;
        for ($i = 0; $i < 14; $i++) {
            if (in_array($date->dayOfWeekIso, $isoDays, true)) {
                return $date;
            }
            $date = $date->addDay();
        }

        return $today;
    }

    /**
     * A part ("begin" = 1st, "half" = 15th, "eind" = last day) of a named month,
     * this year or next if it already passed.
     */
    public static function monthPart(CarbonImmutable $today, int $month, string $part): CarbonImmutable
    {
        $build = function (int $year) use ($month, $part, $today): CarbonImmutable {
            $first = CarbonImmutable::create($year, $month, 1, 0, 0, 0, $today->timezone);

            return match ($part) {
                'begin' => $first,
                'half' => $first->day(15),
                default => $first->endOfMonth()->startOfDay(),
            };
        };

        $date = $build($today->year);

        return $date->lt($today) ? $build($today->year + 1) : $date;
    }

    /** The upcoming 1st of a month: this month if still ahead, otherwise next month. */
    public static function beginOfMonth(CarbonImmutable $today): CarbonImmutable
    {
        $first = $today->startOfMonth();

        return $first->lt($today) ? $first->addMonth() : $first;
    }

    /** The last day of the current month. */
    public static function endOfMonth(CarbonImmutable $today): CarbonImmutable
    {
        return $today->endOfMonth()->startOfDay();
    }

    /** The upcoming 1 January (next year unless today is already 1 January). */
    public static function beginOfYear(CarbonImmutable $today): CarbonImmutable
    {
        $first = $today->startOfYear();

        return $first->lt($today) ? $first->addYear() : $first;
    }

    /** 31 December of the current year. */
    public static function endOfYear(CarbonImmutable $today): CarbonImmutable
    {
        return $today->endOfYear()->startOfDay();
    }

    /** The day-of-month $day on or after today (this month, else next), clamped to the month length. */
    public static function nextDayOfMonth(CarbonImmutable $today, int $day): CarbonImmutable
    {
        $on = fn (CarbonImmutable $base): CarbonImmutable => $base->day(min($day, $base->daysInMonth))->startOfDay();

        $candidate = $on($today);

        return $candidate->lt($today) ? $on($today->startOfMonth()->addMonth()) : $candidate;
    }

    /** First (or last) Mon–Fri of $base's month. */
    public static function workdayOfMonth(CarbonImmutable $base, bool $last): CarbonImmutable
    {
        $date = $last ? $base->endOfMonth()->startOfDay() : $base->startOfMonth();
        $step = $last ? -1 : 1;
        while (! $date->isWeekday()) {
            $date = $date->addDays($step);
        }

        return $date;
    }

    /** The first/last workday of the month on or after today, repeating monthly. */
    public static function upcomingMonthlyWorkday(CarbonImmutable $today, bool $last): CarbonImmutable
    {
        $candidate = self::workdayOfMonth($today, $last);

        return $candidate->lt($today) ? self::workdayOfMonth($today->startOfMonth()->addMonth(), $last) : $candidate;
    }
}
