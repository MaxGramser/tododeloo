<?php

namespace App\Support\DateParsing;

use App\Support\RecurrencePresets;
use Carbon\CarbonImmutable;

/**
 * Turns a parse result into the live "how will this be read" preview: the
 * original sentence tiled into coloured segments (date / recurrence / title /
 * ignored), each carrying its resolved value. Platform-agnostic — web, iOS and
 * Mac render the same data. Concatenating every segment's `text` reproduces the
 * input verbatim, so front ends never need to do offset math.
 */
final class Annotator
{
    public function __construct(private ?RecurrencePresets $presets = null) {}

    /**
     * @param  array{rrule: string, anchor: CarbonImmutable}|null  $recurrence
     * @return array{
     *     input: string,
     *     title: string,
     *     date: ?array{iso: string, label: string},
     *     recurrence: ?array{rrule: string, summary: string, anchor_iso: string, anchor_label: string},
     *     segments: list<array{type: string, text: string, start: int, length: int, resolved?: string}>,
     * }
     */
    public function build(string $original, ?RuleMatch $match, string $title, ?CarbonImmutable $date, ?array $recurrence): array
    {
        $dateInfo = $date !== null
            ? ['iso' => $date->toDateString(), 'label' => $this->dateLabel($date)]
            : null;

        $recurrenceInfo = null;
        $recurrenceResolved = null;
        if ($recurrence !== null) {
            $presets = $this->presets ?? app(RecurrencePresets::class);
            $summary = $presets->describe($recurrence['rrule'], $recurrence['anchor'])['label'];
            $anchorLabel = $this->shortLabel($recurrence['anchor']);
            $recurrenceInfo = [
                'rrule' => $recurrence['rrule'],
                'summary' => $summary,
                'anchor_iso' => $recurrence['anchor']->toDateString(),
                'anchor_label' => $anchorLabel,
            ];
            $recurrenceResolved = $summary.' · vanaf '.$anchorLabel;
        }

        $segments = $this->buildSegments($original, $match, $title, $dateInfo['label'] ?? null, $recurrenceResolved);

        return [
            'input' => $original,
            'title' => $title,
            'date' => $dateInfo,
            'recurrence' => $recurrenceInfo,
            'segments' => $segments,
        ];
    }

    /**
     * Tile the original sentence into typed segments. The date/recurrence phrase
     * is located verbatim from the match; the cleaned title is matched
     * word-for-word as an ordered subsequence (so stripped filler shows as
     * "ignored").
     *
     * @return list<array{type: string, text: string, start: int, length: int, resolved?: string}>
     */
    private function buildSegments(string $original, ?RuleMatch $match, string $title, ?string $dateResolved, ?string $recurrenceResolved): array
    {
        $n = strlen($original);
        if ($n === 0) {
            return [];
        }

        $types = array_fill(0, $n, null);

        $pStart = $pEnd = -1;
        $phraseKind = null;
        if ($match !== null && $match->text !== '') {
            $pos = strpos($original, $match->text);
            if ($pos !== false) {
                $pStart = $pos;
                $pEnd = $pos + strlen($match->text);
                $phraseKind = $match->kind;
            }
        }

        $titleWords = preg_split('/\s+/u', $title, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $ti = 0;
        preg_match_all('/\S+/u', $original, $wm, PREG_OFFSET_CAPTURE);
        foreach ($wm[0] as [$word, $off]) {
            $start = $off;
            $end = $off + strlen($word);
            if ($pStart >= 0 && $start >= $pStart && $end <= $pEnd) {
                continue; // belongs to the date/recurrence phrase
            }
            $type = 'ignored';
            if ($ti < count($titleWords) && $this->wordCore($word) === $this->wordCore($titleWords[$ti])) {
                $type = 'title';
                $ti++;
            }
            for ($i = $start; $i < $end; $i++) {
                $types[$i] = $type;
            }
        }

        if ($pStart >= 0) {
            for ($i = $pStart; $i < $pEnd; $i++) {
                $types[$i] = $phraseKind;
            }
        }

        // Glue whitespace onto the preceding run so segments tile the input —
        // except the space *after* a date/recurrence phrase stays neutral, so the
        // highlight hugs the phrase tightly instead of trailing into the gap.
        $last = null;
        for ($i = 0; $i < $n; $i++) {
            if ($types[$i] === null) {
                $types[$i] = ($last === 'date' || $last === 'recurrence') ? 'ignored' : $last;
            } else {
                $last = $types[$i];
            }
        }

        $segments = [];
        $i = 0;
        while ($i < $n) {
            $type = $types[$i] ?? 'ignored';
            $j = $i;
            while ($j < $n && ($types[$j] ?? 'ignored') === $type) {
                $j++;
            }
            $segment = [
                'type' => $type,
                'text' => substr($original, $i, $j - $i),
                'start' => $i,
                'length' => $j - $i,
            ];
            if ($type === 'date' && $dateResolved !== null) {
                $segment['resolved'] = $dateResolved;
            }
            if ($type === 'recurrence' && $recurrenceResolved !== null) {
                $segment['resolved'] = $recurrenceResolved;
            }
            $segments[] = $segment;
            $i = $j;
        }

        return $segments;
    }

    /** Lowercased, edge-punctuation-stripped form for tolerant word matching. */
    private function wordCore(string $word): string
    {
        return (string) preg_replace('/^[^\p{L}\p{N}]+|[^\p{L}\p{N}]+$/u', '', mb_strtolower($word));
    }

    /** "dinsdag 26 mei" */
    private function dateLabel(CarbonImmutable $date): string
    {
        return $date->locale('nl')->isoFormat('dddd D MMMM');
    }

    /** "di 26 mei" */
    private function shortLabel(CarbonImmutable $date): string
    {
        return $date->locale('nl')->isoFormat('ddd D MMM');
    }
}
