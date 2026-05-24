<?php

namespace App\Support\DateParsing\Rules\Recurrence;

use App\Support\DateParsing\Clock;
use App\Support\DateParsing\Lexicon;
use App\Support\DateParsing\Rule;
use App\Support\DateParsing\RuleMatch;
use Carbon\CarbonImmutable;

/** "elke dinsdag", "iedere vrijdag" → weekly on that weekday. */
class EveryWeekdayRule extends Rule
{
    public function match(string $text, CarbonImmutable $today): ?RuleMatch
    {
        if ($this->find($text, '/\b'.Lexicon::EVERY.'\s+('.Lexicon::WEEKDAY_RE.')\b/iu', $m)) {
            $iso = Lexicon::weekday($m[1][0]);

            return RuleMatch::recurrence($m, 'FREQ=WEEKLY;BYDAY='.Lexicon::DAY_CODE[$iso], Clock::upcomingWeekday($today, $iso));
        }

        return null;
    }
}
