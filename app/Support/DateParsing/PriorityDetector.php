<?php

namespace App\Support\DateParsing;

use App\Enums\Priority;

/**
 * Pulls a priority hint out of a quick-add line ("belangrijk", "urgent", "!!!",
 * "geen haast") and strips the phrase, so it never lands in the title. Returned
 * separately from date/recurrence — it is an orthogonal attribute, not a
 * schedule — and the stripped words show as ignored filler in the preview.
 */
final class PriorityDetector
{
    /** @return array{0: ?Priority, 1: string} The detected priority (or null) and the text with the hint removed. */
    public static function detect(string $text): array
    {
        $high = '/\b(?:urgent|dringend|spoed(?:eisend)?|met\s+spoed|asap|a\.?s\.?a\.?p\.?|belangrijke?|(?:hoge|hoog)\s+prioriteit|prio(?:riteit)?\s+hoog)\b/iu';
        $low = '/\b(?:geen\s+haast|(?:lage|laag)\s+prioriteit|prio(?:riteit)?\s+laag|wanneer\s+het\s+uitkomt|geen\s+prioriteit|ooit\s+eens|ooit)\b/iu';
        $bangs = '/\s*!{2,}/u';

        $priority = null;
        if (preg_match($high, $text) === 1 || preg_match($bangs, $text) === 1) {
            $priority = Priority::High;
            $text = (string) preg_replace([$high, $bangs], ' ', $text);
        } elseif (preg_match($low, $text) === 1) {
            $priority = Priority::Low;
            $text = (string) preg_replace($low, ' ', $text);
        }

        return [$priority, $text];
    }
}
