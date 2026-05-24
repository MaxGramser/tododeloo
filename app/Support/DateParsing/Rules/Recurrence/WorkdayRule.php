<?php

namespace App\Support\DateParsing\Rules\Recurrence;

use App\Support\DateParsing\Clock;
use App\Support\DateParsing\Lexicon;
use App\Support\DateParsing\Rrule;
use App\Support\DateParsing\Rule;
use App\Support\DateParsing\RuleMatch;
use Carbon\CarbonImmutable;

/** "elke werkdag", "iedere werkdag behalve maandag", "op werkdagen" → Mon–Fri. */
class WorkdayRule extends Rule
{
    public function match(string $text, CarbonImmutable $today): ?RuleMatch
    {
        $except = '(?:\s+'.Lexicon::EXCEPT.'\s+(?:op\s+)?'.Lexicon::weekdayList().')?';

        if ($this->find($text, '/\b'.Lexicon::EVERY.'\s+werkdag(?:en)?'.$except.'\b/iu', $m)
            || $this->find($text, '/\bop\s+werkdagen\b/iu', $m)) {
            $days = Rrule::withoutExcluded([1, 2, 3, 4, 5], $m[1][0] ?? '');

            return RuleMatch::recurrence($m, 'FREQ=WEEKLY;BYDAY='.Rrule::dayCodes($days), Clock::nextDayIn($today, $days));
        }

        return null;
    }
}
