<?php

namespace App\Support\DateParsing\Rules\Recurrence;

use App\Support\DateParsing\Clock;
use App\Support\DateParsing\Lexicon;
use App\Support\DateParsing\Rrule;
use App\Support\DateParsing\Rule;
use App\Support\DateParsing\RuleMatch;
use Carbon\CarbonImmutable;

/**
 * "elke dag behalve zondag" → every weekday minus the excluded ones. Runs before
 * DailyRule so the "behalve …" tail is not swallowed by a bare "elke dag".
 */
class EveryDayExceptRule extends Rule
{
    public function match(string $text, CarbonImmutable $today): ?RuleMatch
    {
        if ($this->find($text, '/\b'.Lexicon::EVERY.'\s+dag\s+'.Lexicon::EXCEPT.'\s+(?:op\s+)?'.Lexicon::weekdayList().'\b/iu', $m)) {
            $days = Rrule::withoutExcluded([1, 2, 3, 4, 5, 6, 7], $m[1][0]);

            return RuleMatch::recurrence($m, 'FREQ=WEEKLY;BYDAY='.Rrule::dayCodes($days), Clock::nextDayIn($today, $days));
        }

        return null;
    }
}
