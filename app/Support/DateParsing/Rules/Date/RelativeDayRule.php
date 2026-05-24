<?php

namespace App\Support\DateParsing\Rules\Date;

use App\Support\DateParsing\Rule;
use App\Support\DateParsing\RuleMatch;
use Carbon\CarbonImmutable;

/**
 * Day words relative to today: "overmorgen", "morgen", "eergisteren",
 * "gisteren", and the "now-ish" group ("vandaag", "vanavond", "straks", …) which
 * all resolve to today. "overmorgen"/"eergisteren" are tested before the shorter
 * forms they contain.
 */
class RelativeDayRule extends Rule
{
    public function match(string $text, CarbonImmutable $today): ?RuleMatch
    {
        if ($this->find($text, '/\bovermorgen\b/iu', $m)) {
            return RuleMatch::date($m, $today->addDays(2));
        }
        if ($this->find($text, '/\bmorgen\b/iu', $m)) {
            return RuleMatch::date($m, $today->addDay());
        }
        if ($this->find($text, '/\beergisteren\b/iu', $m)) {
            return RuleMatch::date($m, $today->subDays(2));
        }
        if ($this->find($text, '/\bgisteren\b/iu', $m)) {
            return RuleMatch::date($m, $today->subDay());
        }
        if ($this->find($text, '/\b(?:vandaag|vanmiddag|vanavond|vannacht|vanochtend|vanmorgen|straks|zometeen|zo meteen|nu|meteen)\b/iu', $m)) {
            return RuleMatch::date($m, $today);
        }

        return null;
    }
}
