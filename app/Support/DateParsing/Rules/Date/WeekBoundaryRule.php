<?php

namespace App\Support\DateParsing\Rules\Date;

use App\Support\DateParsing\Clock;
use App\Support\DateParsing\Rule;
use App\Support\DateParsing\RuleMatch;
use Carbon\CarbonImmutable;

/**
 * A whole week or its edges: "begin volgende week" (Mon), "eind volgende week"
 * (Fri), "eind deze week" (Fri), bare "volgende week" (next Mon) and bare
 * "deze week" (today). Runs after RelativeWeekWeekdayRule so a named weekday wins.
 */
class WeekBoundaryRule extends Rule
{
    public function match(string $text, CarbonImmutable $today): ?RuleMatch
    {
        if ($this->find($text, '/\b(?:begin|aan het begin)\s+(?:van\s+)?(?:de\s+)?(?:volgende|komende)\s+week\b/iu', $m)) {
            return RuleMatch::date($m, Clock::weekOffsetWeekday($today, 1, 1));
        }
        if ($this->find($text, '/\b(?:eind|einde|aan het eind)\s+(?:van\s+)?(?:de\s+)?(?:volgende|komende)\s+week\b/iu', $m)) {
            return RuleMatch::date($m, Clock::weekOffsetWeekday($today, 1, 5));
        }
        if ($this->find($text, '/\b(?:eind|einde)\s+(?:van\s+)?(?:de\s+)?(?:deze\s+)?week\b/iu', $m)) {
            return RuleMatch::date($m, Clock::weekOffsetWeekday($today, 0, 5));
        }
        if ($this->find($text, '/\b(?:volgende|komende)\s+week\b/iu', $m)) {
            return RuleMatch::date($m, Clock::weekOffsetWeekday($today, 1, 1));
        }
        if ($this->find($text, '/\bdeze\s+week\b/iu', $m)) {
            return RuleMatch::date($m, $today);
        }

        return null;
    }
}
