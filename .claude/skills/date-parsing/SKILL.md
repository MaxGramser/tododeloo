---
name: date-parsing
description: "Use when adding, changing, or debugging Dutch natural-language date/recurrence parsing for the quick-add field — the DutchDateParser in app/Support/DateParsing/. Covers the ordered rule pipeline (one Rule class per case-family under Rules/Date or Rules/Recurrence), the shared helpers (Lexicon, Clock, Rrule, Filler, Annotator), exactly how to add a new phrasing and where to slot it in the precedence list, and the parser test conventions. Do NOT use for the morning ritual, recurrence materialization, or other non-parsing backend."
metadata:
  author: tododeloo
---

# Dutch date parsing (quick-add)

`App\Support\DateParsing\DutchDateParser` turns a free-text quick-add line
("over 2 weken vrijdag een etentje") into `{title, date, recurrence}`, and
`annotate()` returns the same parse tiled into highlight segments for the live
preview. The design follows **chrono-node / Duckling**: an ordered pipeline of
small, single-responsibility **rules**, each owning one regex family, composed
in an explicit precedence list, with the shared vocabulary pulled out.

Deliberately rule-based and ordered (specific → general) — not a full NLP
engine, just smart enough for everyday phrasing.

## Layout

```
app/Support/DateParsing/
  DutchDateParser.php   facade · holds the two ordered rule lists · run() pipeline
  Rule.php              abstract base: match(text, today): ?RuleMatch + find() helper
  RuleMatch.php         a match as data: kind, phrase text+span, and date | rrule+anchor
  Lexicon.php           vocabulary: weekday/month/number words, regex fragments, num()/ordinal()
  Clock.php             pure date math → CarbonImmutable (upcomingWeekday, weekOffsetWeekday, …)
  Rrule.php             RRULE string builders (daily/weekly/monthly, dayCodes, withoutExcluded)
  Filler.php            strips command/connector/time chatter + looksLikeReminder + cleanup
  Annotator.php         builds the preview segments from a RuleMatch
  Rules/Date/           one-off dates, one class per case-family
  Rules/Recurrence/     recurrences, one class per case-family
```

`parse()` and `annotate()` both call the private `run()`, which: normalizes →
strips filler → tries the **recurrence** rules, and only if none match the
**date** rules → strips leftover connectors → cleans up to a title. The first
rule that returns a `RuleMatch` wins; its span is blanked out of the working
text so it can't leak into the title. `run()` hands the winning `RuleMatch` back
to `annotate()`, so highlighting needs no re-parse and there is no recording
side-effect.

## Adding or changing a phrasing — the rules of the road

1. **Date or recurrence?** A one-off date → `Rules/Date/`. A repeating pattern
   (RRULE) → `Rules/Recurrence/`.
2. **One case-family per class.** Create a focused `Rule` subclass; do NOT widen
   an unrelated rule to absorb a case it was not named for. Group only genuinely
   related phrasings (e.g. all "over N <unit>" variants live in `OverDurationRule`).
3. **Own the regex, reuse the vocabulary.** Build patterns from
   `Lexicon::WEEKDAY_RE`, `::NUMBER_RE`, `::EVERY`, `::monthRe()`, etc. Never
   re-declare weekday/month/number words in a rule. Resolve values with
   `Lexicon::weekday()/month()/num()/ordinal()`.
4. **Return data, not strings.** End with `RuleMatch::date($m, $date)` or
   `RuleMatch::recurrence($m, $rrule, $anchor)`, where `$m` is the
   `PREG_OFFSET_CAPTURE` match (use the inherited `find()`). Compute dates with
   `Clock::*` and RRULEs with `Rrule::*` — keep this logic out of the rule body.
5. **Register it in `DutchDateParser` at the right precedence.** Add it to
   `$dateRules` or `$recurrenceRules`. **The order of those arrays is the single
   source of truth for precedence** — specific before general. A rule placed too
   late never fires because a broader rule already matched.
6. **RRULEs must line up with `RecurrencePresets`** so the readable summary
   ("Elke werkdag") lights up. There is a test that asserts this.
7. **Add a dataset row** to `tests/Feature/DutchDateParserTest.php` and run
   `php artisan test --compact --filter=DutchDateParser`.

## Precedence gotchas (why the order is what it is)

- `RelativeWeekWeekdayRule` runs **before** `OverDurationRule` — otherwise
  "over 2 weken **vrijdag**" matches the bare "over N weken" and drops the
  weekday (lands on the wrong day).
- `EveryDayExceptRule` runs **before** `DailyRule` — else a bare "elke dag"
  swallows "elke dag **behalve** zondag".
- `NthWeekdayRule` runs **before** `EveryWeekdayRule`; the half-yearly clause in
  `MonthlyRule` is checked before the month-interval clause.
- `CalendarDateRule` runs **before** `BareWeekdayRule`; an out-of-range numeric
  date returns `null` so the bare-weekday fallback still gets a turn.

## Test conventions

- Reference "today" in `DutchDateParserTest.php` is **Wednesday 2026-05-20**
  (this week's Monday = 2026-05-18). Compute expected dates against that.
- One-off dates and recurrences each have a `it(...)->with([...])` dataset —
  add a labelled row, don't write a new `it()` per phrasing.
- `annotate()` has its own tests asserting the segments concatenate back to the
  input verbatim; if you touch `Annotator`, keep that invariant.
