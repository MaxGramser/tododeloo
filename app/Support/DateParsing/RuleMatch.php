<?php

namespace App\Support\DateParsing;

use Carbon\CarbonImmutable;

/**
 * The result of a rule firing: the matched phrase (its text and span, so the
 * parser can blank it out and the annotator can highlight it) plus the resolved
 * value — either a one-off date or a recurrence (rrule + anchor). Carrying the
 * span as data is what lets the parser drop the old recording side-effect.
 */
final class RuleMatch
{
    private function __construct(
        public readonly string $kind,
        public readonly string $text,
        public readonly int $start,
        public readonly int $length,
        public readonly ?CarbonImmutable $date,
        public readonly ?string $rrule,
        public readonly ?CarbonImmutable $anchor,
    ) {}

    /**
     * @param  array<int, array{0: string, 1: int}>  $m  A PREG_OFFSET_CAPTURE match.
     */
    public static function date(array $m, CarbonImmutable $date): self
    {
        return new self('date', $m[0][0], $m[0][1], strlen($m[0][0]), $date, null, null);
    }

    /**
     * @param  array<int, array{0: string, 1: int}>  $m  A PREG_OFFSET_CAPTURE match.
     */
    public static function recurrence(array $m, string $rrule, CarbonImmutable $anchor): self
    {
        return new self('recurrence', $m[0][0], $m[0][1], strlen($m[0][0]), null, $rrule, $anchor);
    }
}
