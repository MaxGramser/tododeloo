<?php

namespace App\Support\DateParsing;

/**
 * Strips the non-scheduling chatter around a quick-add line: leading command
 * filler ("kan je me", "herinner mij aan"), clock times (todos have no time
 * field) and leftover connectors once the date/recurrence is removed. Also tells
 * whether the line is phrased as a reminder.
 */
final class Filler
{
    /** Whether the line is phrased as a reminder ("herinner me …", "onthoud …", "reminder …"). */
    public static function looksLikeReminder(string $text): bool
    {
        return preg_match('/\b(?:herinner(?:ing|en)?|onthoud(?:en)?|reminder|memo|vergeet\s+niet|denk\s+(?:er)?aan)\b/iu', $text) === 1;
    }

    public static function stripLeadingCommands(string $text): string
    {
        $patterns = [
            '/^(?:kun|kan)\s+(?:je|jij|u)\s+(?:me|mij|mn|m\'n|ons)?\s*/iu',
            '/^(?:zou|wil)\s+je\s+(?:misschien\s+|even\s+)?(?:me|mij)?\s*/iu',
            '/^herinner(?:ing|en)?\s*(?:me|mij|mn|ons)?\s*(?:er)?\s*(?:aan|voor|om|dat)?\s*/iu',
            '/^onthoud(?:en)?\s*(?:dat\s+(?:ik\s+)?|(?:om|te)\s+)?/iu',
            '/^reminder\s*[:\-–]?\s*(?:(?:voor|om)\s+)?/iu',
            '/^memo\s*[:\-–]?\s*/iu',
            '/^denk(?:en)?\s+(?:er)?aan\s+(?:om|te|dat)?\s*/iu',
            '/^vergeet\s+niet\s+(?:om|te|dat)?\s*/iu',
            '/^ik\s+(?:moet|wil|zou|ga|hoef)\s+(?:nog\s+)?/iu',
            '/^(?:we|wij)\s+(?:moeten|willen|gaan)\s+(?:nog\s+)?/iu',
            '/^moet\s+(?:ik\s+)?nog\s+/iu',
            '/^zorg\s+(?:er)?voor\s+dat\s+(?:ik\s+)?/iu',
            '/^zorg\s+dat\s+(?:ik\s+)?/iu',
            '/^(?:graag|alsjeblieft|alstublieft|aub|a\.u\.b\.)\s+/iu',
            '/^(?:to-?do|taak|task|notitie)\s*[:\-–]\s*/iu',
            '/^maak(?=.*\b(?:to-?do|taak|elke|iedere|dagelijks|wekelijks|maandelijks|jaarlijks)\b)\s+/iu',
            '/^plan(?:\s+in)?\s+om\s+/iu',
            '/^plan\s+in\s+/iu',
            '/^inplannen\s*[:\-]?\s*/iu',
            '/^(?:uiterlijk|ten\s+laatste)\s+/iu',
            '/^deadline\s*[:\-–]?\s*/iu',
        ];

        return self::loopStrip($text, $patterns);
    }

    public static function stripConnectors(string $text): string
    {
        $patterns = [
            '/^(?:een|de|het)\s+(?:nieuwe\s+)?(?:to-?do|taak)\s+(?:aan\s+)?(?:voor|om|van)\b\s*/iu',
            '/^(?:to-?do|taak)\s+(?:aan\s+)?(?:voor|om)\b\s*/iu',
            '/^(?:een|de|het)\s+(?:to-?do|taak)\b\s*/iu',
            '/^herinner(?:ing|en)?\s*(?:me|mij)?\s*(?:aan|voor|om)\b\s*/iu',
            '/^om\s+te\b\s*/iu',
            '/^(?:om|aan|voor|op|met|dat|dan|te|vanaf|eraan|er\s+aan)\b\s*/iu',
        ];

        return self::loopStrip($text, $patterns);
    }

    /**
     * Remove clock times — todos have no time field. Conservative: only matches
     * with an explicit "om", a colon, or "uur", so "3 uur durende meeting" stays.
     */
    public static function stripTimes(string $text): string
    {
        $patterns = [
            '/\bom\s+half\s+\d{1,2}\b/iu',
            '/\bom\s+kwart\s+(?:over|voor)\s+\d{1,2}\b/iu',
            '/\btussen\s+\d{1,2}(?:[:.]\d{2})?\s*(?:en|-|tot)\s*\d{1,2}(?:[:.]\d{2})?\s*uur\b/iu',
            '/\bom\s+\d{1,2}[:.]\d{2}\s*(?:uur)?\b/iu',
            '/\bom\s+\d{1,2}\s*uur\b/iu',
            '/\b\d{1,2}[:.]\d{2}\s*uur\b/iu',
            '/\b\d{1,2}[:.]\d{2}\b/iu',
            "/'s\\s*(?:ochtends|middags|avonds|nachts)\\b/iu",
        ];

        return self::loopStrip($text, $patterns);
    }

    public static function cleanup(string $text): string
    {
        $text = (string) preg_replace('/\s+/u', ' ', $text);
        $text = trim($text);
        // Strip a single layer of surrounding quotes.
        $text = (string) preg_replace('/^["\'“”‘’](.*)["\'“”‘’]$/u', '$1', $text);
        // Trim dangling separators (keep ! and ?).
        $text = (string) preg_replace('/^[\s:,\-–]+/u', '', $text);
        $text = (string) preg_replace('/[\s:,\-–]+$/u', '', $text);

        return trim($text);
    }

    /**
     * @param  list<string>  $patterns
     */
    private static function loopStrip(string $text, array $patterns): string
    {
        do {
            $before = $text;
            foreach ($patterns as $pattern) {
                $text = (string) preg_replace($pattern, '', $text, 1);
            }
            $text = ltrim($text);
        } while ($text !== $before);

        return $text;
    }
}
