<?php

namespace App\Support\DateParsing\Rules\Recurrence;

use App\Support\DateParsing\Lexicon;
use App\Support\DateParsing\Rrule;
use App\Support\DateParsing\Rule;
use App\Support\DateParsing\RuleMatch;
use Carbon\CarbonImmutable;

/** "elke 2 dagen", "om de dag" (every other day), "elke dag", "dagelijks". */
class DailyRule extends Rule
{
    public function match(string $text, CarbonImmutable $today): ?RuleMatch
    {
        $num = Lexicon::NUMBER_RE;

        if ($this->find($text, '/\b(?:'.Lexicon::EVERY.'|om de)\s+('.$num.')\s+dag(?:en)?\b/iu', $m)) {
            return RuleMatch::recurrence($m, Rrule::daily(Lexicon::num($m[1][0])), $today);
        }
        if ($this->find($text, '/\bom\s+de\s+dag\b/iu', $m)) {
            return RuleMatch::recurrence($m, 'FREQ=DAILY;INTERVAL=2', $today);
        }
        if ($this->find($text, '/\b'.Lexicon::EVERY.'\s+dag\b/iu', $m) || $this->find($text, '/\bdagelijks\b/iu', $m)) {
            return RuleMatch::recurrence($m, 'FREQ=DAILY', $today);
        }

        return null;
    }
}
