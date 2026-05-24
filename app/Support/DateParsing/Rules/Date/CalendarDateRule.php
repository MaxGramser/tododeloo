<?php

namespace App\Support\DateParsing\Rules\Date;

use App\Support\DateParsing\Clock;
use App\Support\DateParsing\Lexicon;
use App\Support\DateParsing\Rule;
use App\Support\DateParsing\RuleMatch;
use Carbon\CarbonImmutable;

/**
 * An explicit calendar date: ISO ("2026-06-05"), spelled ("op 25 december",
 * "25 december 2027") or numeric ("25-12", "1/6/2026"). ISO is matched first so
 * "2026-06-05" is not mis-read as a day-month. A numeric match with an
 * out-of-range day or month is ignored, so a bare weekday can still be tried.
 */
class CalendarDateRule extends Rule
{
    public function match(string $text, CarbonImmutable $today): ?RuleMatch
    {
        $month = Lexicon::monthRe();

        if ($this->find($text, '/\b(\d{4})-(\d{1,2})-(\d{1,2})\b/u', $m)) {
            $monthNum = (int) $m[2][0];
            $day = (int) $m[3][0];
            if ($monthNum >= 1 && $monthNum <= 12 && $day >= 1 && $day <= 31) {
                return RuleMatch::date($m, Clock::buildDate($today, $day, $monthNum, (int) $m[1][0]));
            }
        }

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
