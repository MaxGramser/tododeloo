<?php

namespace App\Support\DateParsing\Rules\Recurrence;

use App\Support\DateParsing\Lexicon;
use App\Support\DateParsing\Rrule;
use App\Support\DateParsing\Rule;
use App\Support\DateParsing\RuleMatch;
use Carbon\CarbonImmutable;

/**
 * "elk half jaar"/"halfjaarlijks" (INTERVAL=6), "elke 2 maanden", "elke maand",
 * "maandelijks", "per maand". Half-yearly is checked first so "half jaar" is not
 * read as a plain month interval.
 */
class MonthlyRule extends Rule
{
    public function match(string $text, CarbonImmutable $today): ?RuleMatch
    {
        $num = Lexicon::NUMBER_RE;

        if ($this->find($text, '/\b'.Lexicon::EVERY.'\s+half\s+jaar\b/iu', $m) || $this->find($text, '/\bhalfjaarlijks\b/iu', $m)) {
            return RuleMatch::recurrence($m, 'FREQ=MONTHLY;INTERVAL=6', $today);
        }
        if ($this->find($text, '/\b(?:'.Lexicon::EVERY.'|om de)\s+('.$num.')\s+maanden\b/iu', $m)) {
            return RuleMatch::recurrence($m, Rrule::monthly(Lexicon::num($m[1][0])), $today);
        }
        if ($this->find($text, '/\b'.Lexicon::EVERY.'\s+maand\b/iu', $m) || $this->find($text, '/\bmaandelijks\b/iu', $m) || $this->find($text, '/\bper\s+maand\b/iu', $m)) {
            return RuleMatch::recurrence($m, 'FREQ=MONTHLY', $today);
        }

        return null;
    }
}
