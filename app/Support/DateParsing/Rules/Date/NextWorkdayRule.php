<?php

namespace App\Support\DateParsing\Rules\Date;

use App\Support\DateParsing\Rule;
use App\Support\DateParsing\RuleMatch;
use App\Support\Workday;
use Carbon\CarbonImmutable;

/** "eerstvolgende werkdag", "volgende werkdag" → the next Mon–Fri after today. */
class NextWorkdayRule extends Rule
{
    public function match(string $text, CarbonImmutable $today): ?RuleMatch
    {
        if ($this->find($text, '/\b(?:eerstvolgende|eerst volgende|volgende|komende)\s+werkdag\b/iu', $m)) {
            return RuleMatch::date($m, Workday::nextWorkdayAfter($today));
        }

        return null;
    }
}
