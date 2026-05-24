<?php

namespace App\Support\DateParsing\Rules\Date;

use App\Support\DateParsing\Clock;
use App\Support\DateParsing\Lexicon;
use App\Support\DateParsing\Rule;
use App\Support\DateParsing\RuleMatch;
use Carbon\CarbonImmutable;

/**
 * An explicit calendar date, either spelled ("op 25 december", "25 december
 * 2027") or numeric ("25-12", "1/6/2026"). A numeric match with an out-of-range
 * day or month is ignored, so a bare weekday can still be tried afterwards.
 */
class CalendarDateRule extends Rule
{
    public function match(string $text, CarbonImmutable $today): ?RuleMatch
    {
        $month = Lexicon::monthRe();

        if ($this->find($text, '/\b(?:op\s+)?(\d{1,2})(?:e|ste|de)?\s+('.$month.')\b(?:\s+(\d{4}))?/iu', $m)) {
            $day = (int) $m[1][0];
            $monthNum = Lexicon::month($m[2][0]);
            $year = isset($m[3]) && $m[3][0] !== '' ? (int) $m[3][0] : null;

            return RuleMatch::date($m, Clock::buildDate($today, $day, $monthNum, $year));
        }

        if ($this->find($text, '/\b(\d{1,2})[-\/.](\d{1,2})(?:[-\/.](\d{2,4}))?\b/iu', $m)) {
            $day = (int) $m[1][0];
            $monthNum = (int) $m[2][0];
            if ($day >= 1 && $day <= 31 && $monthNum >= 1 && $monthNum <= 12) {
                $year = isset($m[3]) && $m[3][0] !== '' ? (int) $m[3][0] : null;
                if ($year !== null && $year < 100) {
                    $year += 2000;
                }

                return RuleMatch::date($m, Clock::buildDate($today, $day, $monthNum, $year));
            }
        }

        return null;
    }
}
