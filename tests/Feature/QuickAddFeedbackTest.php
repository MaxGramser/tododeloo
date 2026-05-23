<?php

use App\Models\Recurrence;
use App\Models\Todo;
use App\Models\User;
use App\Support\QuickAddFeedback;
use Carbon\CarbonImmutable;
use Laravel\Sanctum\Sanctum;

/** Reference "today" = Wednesday 20 May 2026, matching DutchDateParserTest. */
function feedbackFor(Todo $todo, ?CarbonImmutable $target): array
{
    return (new QuickAddFeedback)->build($todo, $target, CarbonImmutable::parse('2026-05-20'));
}

function datedTodo(string $title): Todo
{
    $todo = new Todo(['title' => $title]);
    $todo->setRelation('recurrence', null);

    return $todo;
}

it('quotes the cleaned title and phrases the date relative to today', function (string $date, string $expected) {
    $result = feedbackFor(datedTodo('Bel de dokter'), CarbonImmutable::parse($date));

    expect($result['message'])->toBe('"Bel de dokter"')
        ->and($result['description'])->toBe($expected);
})->with([
    'today' => ['2026-05-20', 'ingepland voor vandaag'],
    'tomorrow' => ['2026-05-21', 'ingepland voor morgen'],
    'day after' => ['2026-05-22', 'ingepland voor overmorgen'],
    'later this week' => ['2026-05-23', 'ingepland voor zaterdag'],
    'next week weekday' => ['2026-05-26', 'ingepland voor volgende week dinsdag'],
    'further out' => ['2026-06-10', 'ingepland voor 10 juni'],
    'next year' => ['2027-01-05', 'ingepland voor 5 januari 2027'],
    'yesterday' => ['2026-05-19', 'ingepland voor gisteren'],
]);

it('describes a recurrence with its preset label', function (string $rrule, string $expected) {
    $todo = new Todo(['title' => 'Sport']);
    $todo->setRelation('recurrence', new Recurrence(['rrule' => $rrule, 'dtstart' => '2026-05-20']));

    expect(feedbackFor($todo, CarbonImmutable::parse('2026-05-20'))['description'])->toBe($expected);
})->with([
    'daily' => ['FREQ=DAILY', 'herhaalt elke dag'],
    'weekdays' => ['FREQ=WEEKLY;BYDAY=MO,TU,WE,TH,FR', 'herhaalt elke werkdag'],
    'weekly' => ['FREQ=WEEKLY;BYDAY=WE', 'herhaalt elke woensdag'],
    'custom interval' => ['FREQ=DAILY;INTERVAL=2', 'herhaalt elke 2 dagen'],
]);

it('falls back to "toegevoegd" when nothing was scheduled', function () {
    $result = feedbackFor(datedTodo('Boodschappen doen'), null);

    expect($result['message'])->toBe('"Boodschappen doen"')
        ->and($result['description'])->toBe('toegevoegd');
});

it('returns the feedback in the quick-add API response', function () {
    Sanctum::actingAs(User::factory()->create());

    $response = $this->postJson('/api/quick-add', ['title' => 'sport iedere dag']);

    $response->assertOk()
        ->assertJsonPath('feedback.message', '"sport"')
        ->assertJsonPath('feedback.description', 'herhaalt elke dag');
});
