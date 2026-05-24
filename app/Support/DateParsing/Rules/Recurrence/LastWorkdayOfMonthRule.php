<?php

namespace App\Support\DateParsing\Rules\Recurrence;

use App\Support\DateParsing\Clock;
use App\Support\DateParsing\Lexicon;
use App\Support\DateParsing\Rule;
use App\Support\DateParsing\RuleMatch;
use Carbon\CarbonImmutable;

/**
 * "elke laatste werkdag van de maand" / "maandelijks op de eerste werkdag van de
 * maand" → FREQ=MONTHLY;BYDAY=MO..FR;BYSETPOS=±1, anchored on the next such day.
 */
class LastWorkdayOfMonthRule extends Rule
{
    public function match(string $text, CarbonImmutable $today): ?RuleMatch
    {
        if ($this->find($text, '/\b(?:'.Lexicon::EVERY.'\s+|maandelijks\s+op\s+de\s+)(eerste|laatste)\s+werkdag\s+van\s+(?:de\s+)?maand\b/iu', $m)) {
            $last = mb_strtolower($m[1][0]) === 'laatste';

            return RuleMatch::recurrence(
                $m,
                'FREQ=MONTHLY;BYDAY=MO,TU,WE,TH,FR;BYSETPOS='.($last ? '-1' : '1'),
                Clock::upcomingMonthlyWorkday($today, $last),
            );
        }

        return null;
    }
}
