<?php

namespace App\Support\DateParsing\Rules\Recurrence;

use App\Support\DateParsing\Clock;
use App\Support\DateParsing\Lexicon;
use App\Support\DateParsing\Rrule;
use App\Support\DateParsing\Rule;
use App\Support\DateParsing\RuleMatch;
use Carbon\CarbonImmutable;

/**
 * The nth weekday of a month or quarter: "iedere eerste dinsdag van de maand",
 * "elke laatste vrijdag van het kwartaal". Quarterly becomes MONTHLY;INTERVAL=3
 * anchored on a quarter start.
 */
class NthWeekdayRule extends Rule
{
    public function match(string $text, CarbonImmutable $today): ?RuleMatch
    {
        $ordinal = '(eerste|tweede|derde|vierde|vijfde|laatste|\d{1,2}(?:e|ste|de)?)';
        $wd = Lexicon::WEEKDAY_RE;

        if ($this->find($text, '/\b'.Lexicon::EVERY.'\s+'.$ordinal.'\s+('.$wd.')\s+van\s+(?:de\s+|het\s+)?(maand|kwartaal)\b/iu', $m)) {
            $n = Lexicon::ordinal($m[1][0]);
            $iso = Lexicon::weekday($m[2][0]);
            $byDay = Rrule::nthCode($n).Lexicon::DAY_CODE[$iso];

            if (mb_strtolower($m[3][0]) === 'kwartaal') {
                return RuleMatch::recurrence($m, 'FREQ=MONTHLY;INTERVAL=3;BYDAY='.$byDay, Clock::upcomingQuarterlyNthWeekday($today, $n, $iso));
            }

            return RuleMatch::recurrence($m, 'FREQ=MONTHLY;BYDAY='.$byDay, Clock::upcomingNthWeekday($today, $n, $iso));
        }

        return null;
    }
}
