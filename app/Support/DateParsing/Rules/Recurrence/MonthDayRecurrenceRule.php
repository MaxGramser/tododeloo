<?php

namespace App\Support\DateParsing\Rules\Recurrence;

use App\Support\DateParsing\Clock;
use App\Support\DateParsing\Lexicon;
use App\Support\DateParsing\Rule;
use App\Support\DateParsing\RuleMatch;
use Carbon\CarbonImmutable;

/**
 * A monthly recurrence on a day-of-month: "elke 1e van de maand", "maandelijks
 * op de 15e", "elke laatste van de maand" → FREQ=MONTHLY;BYMONTHDAY=N (−1 for
 * the last day). Distinct from the nth-weekday rule, which names a weekday.
 */
class MonthDayRecurrenceRule extends Rule
{
    public function match(string $text, CarbonImmutable $today): ?RuleMatch
    {
        $day = '(\d{1,2}|eerste|laatste)(?:e|ste|de)?';

        if ($this->find($text, '/\b'.Lexicon::EVERY.'\s+'.$day.'\s+van\s+(?:de\s+)?maand\b/iu', $m)
            || $this->find($text, '/\bmaandelijks\s+op\s+de\s+'.$day.'\b/iu', $m)) {
            $token = mb_strtolower($m[1][0]);

            if ($token === 'laatste') {
                return RuleMatch::recurrence($m, 'FREQ=MONTHLY;BYMONTHDAY=-1', Clock::endOfMonth($today));
            }

            $monthDay = $token === 'eerste' ? 1 : max(1, min((int) $token, 31));

            return RuleMatch::recurrence($m, 'FREQ=MONTHLY;BYMONTHDAY='.$monthDay, Clock::nextDayOfMonth($today, $monthDay));
        }

        return null;
    }
}
