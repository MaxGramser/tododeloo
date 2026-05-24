<?php

namespace App\Support\DateParsing\Rules\Recurrence;

use App\Support\DateParsing\Clock;
use App\Support\DateParsing\Lexicon;
use App\Support\DateParsing\Rule;
use App\Support\DateParsing\RuleMatch;
use Carbon\CarbonImmutable;

/**
 * A yearly recurrence pinned to a date: "elk jaar op 25 december", "jaarlijks op
 * 1/6" → FREQ=YEARLY anchored on the next occurrence of that date. Runs before
 * the plain YearlyRule so the date is not dropped.
 */
class YearlyOnDateRule extends Rule
{
    public function match(string $text, CarbonImmutable $today): ?RuleMatch
    {
        $every = '(?:'.Lexicon::EVERY.'\s+jaar|jaarlijks)';
        $month = Lexicon::monthRe();

        if ($this->find($text, '/\b'.$every.'\s+op\s+(?:de\s+)?(\d{1,2})(?:e|ste|de)?\s+('.$month.')\b/iu', $m)) {
            $anchor = Clock::buildDate($today, (int) $m[1][0], Lexicon::month($m[2][0]), null);

            return RuleMatch::recurrence($m, 'FREQ=YEARLY', $anchor);
        }

        if ($this->find($text, '/\b'.$every.'\s+op\s+(\d{1,2})[-\/.](\d{1,2})\b/iu', $m)) {
            $day = (int) $m[1][0];
            $monthNum = (int) $m[2][0];
            if ($day >= 1 && $day <= 31 && $monthNum >= 1 && $monthNum <= 12) {
                return RuleMatch::recurrence($m, 'FREQ=YEARLY', Clock::buildDate($today, $day, $monthNum, null));
            }
        }

        return null;
    }
}
