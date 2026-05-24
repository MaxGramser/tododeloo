<?php

namespace App\Support\DateParsing;

/**
 * Builds the RRULE strings the recurrence rules emit. Kept apart from the
 * vocabulary so the BYDAY / INTERVAL formatting lives in one place and every
 * recurrence rule produces strings that line up with App\Support\RecurrencePresets.
 */
final class Rrule
{
    public static function daily(int $interval): string
    {
        return $interval <= 1 ? 'FREQ=DAILY' : "FREQ=DAILY;INTERVAL={$interval}";
    }

    public static function weekly(int $interval): string
    {
        return $interval <= 1 ? 'FREQ=WEEKLY;BYDAY=MO' : "FREQ=WEEKLY;INTERVAL={$interval};BYDAY=MO";
    }

    public static function monthly(int $interval): string
    {
        return $interval <= 1 ? 'FREQ=MONTHLY' : "FREQ=MONTHLY;INTERVAL={$interval}";
    }

    /** RRULE BYDAY ordinal: 1..5 or -1 for "last". */
    public static function nthCode(int $n): string
    {
        return $n < 0 ? '-1' : (string) $n;
    }

    /**
     * "MO,TU,WE" for a list of ISO weekdays.
     *
     * @param  list<int>  $isoDays
     */
    public static function dayCodes(array $isoDays): string
    {
        return implode(',', array_map(fn (int $iso): string => Lexicon::DAY_CODE[$iso], $isoDays));
    }

    /**
     * Drop the weekdays named in $text from $isoDays (order preserved). An empty
     * result falls back to the full set, so "behalve <gibberish>" never empties.
     *
     * @param  list<int>  $isoDays
     * @return list<int>
     */
    public static function withoutExcluded(array $isoDays, string $text): array
    {
        if ($text === '') {
            return $isoDays;
        }

        $excluded = [];
        if (preg_match_all('/'.Lexicon::WEEKDAY_RE.'/iu', $text, $mm)) {
            foreach ($mm[0] as $name) {
                $excluded[] = Lexicon::weekday($name);
            }
        }

        $remaining = array_values(array_diff($isoDays, $excluded));

        return $remaining === [] ? $isoDays : $remaining;
    }
}
