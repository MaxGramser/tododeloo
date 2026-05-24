<?php

namespace App\Support\DateParsing;

use Carbon\CarbonImmutable;

/**
 * One natural-language case. A rule owns its regex(es) and the extraction of a
 * date or recurrence from them, and nothing else. Rules are pure and stateless;
 * DutchDateParser tries them in a fixed order and the first non-null match wins.
 *
 * To support a new phrasing, add a focused rule class under Rules/Date or
 * Rules/Recurrence and slot it into the ordered list in DutchDateParser — never
 * widen an unrelated rule to absorb a case it was not named for.
 */
abstract class Rule
{
    /** The first match in $text, or null when this rule does not apply. */
    abstract public function match(string $text, CarbonImmutable $today): ?RuleMatch;

    /**
     * preg_match with offset capture — the shape every rule uses to both read its
     * groups and locate the phrase for later stripping/highlighting.
     *
     * @param  array<int, array{0: string, 1: int}>|null  $m
     */
    final protected function find(string $text, string $pattern, ?array &$m): bool
    {
        return preg_match($pattern, $text, $m, PREG_OFFSET_CAPTURE) === 1;
    }
}
