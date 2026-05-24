<?php

namespace App\Support\DateParsing\Rules\Recurrence;

use App\Support\DateParsing\Clock;
use App\Support\DateParsing\Lexicon;
use App\Support\DateParsing\Rule;
use App\Support\DateParsing\RuleMatch;
use Carbon\CarbonImmutable;

/**
 * "2 keer per week", "3x per maand", "1 keer per dag". The count can't be
 * expressed as an RRULE without specific days, so it maps to the base frequency
 * of the unit (the count is intentionally ignored) and consumes the phrase.
 */
class CountPerUnitRule extends Rule
{
    public function match(string $text, CarbonImmutable $today): ?RuleMatch
    {
        if ($this->find($text, '/\b('.Lexicon::NUMBER_RE.')\s*(?:keer|keren|maal|x)\s+per\s+(dag|week|maand|jaar)\b/iu', $m)) {
            return match (mb_strtolower($m[2][0])) {
                'dag' => RuleMatch::recurrence($m, 'FREQ=DAILY', $today),
                'week' => RuleMatch::recurrence($m, 'FREQ=WEEKLY;BYDAY=MO', Clock::upcomingWeekday($today, 1)),
                'maand' => RuleMatch::recurrence($m, 'FREQ=MONTHLY', $today),
                default => RuleMatch::recurrence($m, 'FREQ=YEARLY', $today),
            };
        }

        return null;
    }
}
