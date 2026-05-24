<?php

namespace App\Support\DateParsing\Rules\Recurrence;

use App\Support\DateParsing\Lexicon;
use App\Support\DateParsing\Rule;
use App\Support\DateParsing\RuleMatch;
use Carbon\CarbonImmutable;

/** "elk jaar", "jaarlijks", "per jaar" → yearly. */
class YearlyRule extends Rule
{
    public function match(string $text, CarbonImmutable $today): ?RuleMatch
    {
        if ($this->find($text, '/\b'.Lexicon::EVERY.'\s+jaar\b/iu', $m) || $this->find($text, '/\bjaarlijks\b/iu', $m) || $this->find($text, '/\bper\s+jaar\b/iu', $m)) {
            return RuleMatch::recurrence($m, 'FREQ=YEARLY', $today);
        }

        return null;
    }
}
