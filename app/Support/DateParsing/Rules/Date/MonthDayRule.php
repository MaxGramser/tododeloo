<?php

namespace App\Support\DateParsing\Rules\Date;

use App\Support\DateParsing\Clock;
use App\Support\DateParsing\Rule;
use App\Support\DateParsing\RuleMatch;
use Carbon\CarbonImmutable;

/**
 * A day inside a calendar month, or a bare month/year jump: "volgende maand de
 * 1e", "de 15e van volgende maand", a bare "de 15e" (this month or next),
 * "volgende maand", "volgend jaar".
 */
class MonthDayRule extends Rule
{
    public function match(string $text, CarbonImmutable $today): ?RuleMatch
    {
        if ($this->find($text, '/\bvolgende\s+maand\s+(?:op\s+)?de\s+(\d{1,2}|eerste|laatste)(?:e|ste|de)?\b/iu', $m)) {
            return RuleMatch::date($m, Clock::dayOfMonth($today->addMonth(), $m[1][0]));
        }
        if ($this->find($text, '/\b(?:op\s+)?de\s+(\d{1,2}|eerste|laatste)(?:e|ste|de)?\s+van\s+(?:de\s+)?(deze|volgende|komende)\s+maand\b/iu', $m)) {
            $base = mb_strtolower($m[2][0]) === 'deze' ? $today : $today->addMonth();

            return RuleMatch::date($m, Clock::dayOfMonth($base, $m[1][0]));
        }
        if ($this->find($text, '/\bvolgende\s+maand\b/iu', $m)) {
            return RuleMatch::date($m, $today->addMonth());
        }
        if ($this->find($text, '/\bvolgend(?:e)?\s+jaar\b/iu', $m)) {
            return RuleMatch::date($m, $today->addYear());
        }
        // Bare "de 15e" → that day this month, or next month if it already passed.
        // The ordinal suffix is required so "de 3 honden" is not read as a date.
        if ($this->find($text, '/\b(?:op\s+)?de\s+(\d{1,2})(?:e|ste|de)\b/iu', $m)) {
            return RuleMatch::date($m, Clock::nextDayOfMonth($today, (int) $m[1][0]));
        }

        return null;
    }
}
