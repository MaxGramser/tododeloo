<?php

use App\Actions\Lists\GetOrCreateDailyList;
use App\Actions\Recurrences\MaterializeRecurrences;
use App\Actions\Todos\AddTodoToList;
use App\Actions\Todos\CreateTodo;
use App\Enums\ListType;
use App\Enums\RecurrencePreset;
use App\Models\Recurrence;
use App\Models\Todo;
use App\Models\User;
use App\Support\RecurrencePresets;
use App\Support\RecurrenceSchedule;
use App\Support\Workday;
use Carbon\CarbonImmutable;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

it('builds correct RRULEs from presets and an anchor date', function () {
    $presets = new RecurrencePresets;
    $thirdTuesday = CarbonImmutable::parse('2026-05-19'); // 3rd Tuesday of May

    expect($presets->rrule(RecurrencePreset::Daily, $thirdTuesday))->toBe('FREQ=DAILY')
        ->and($presets->rrule(RecurrencePreset::Weekdays, $thirdTuesday))->toBe('FREQ=WEEKLY;BYDAY=MO,TU,WE,TH,FR')
        ->and($presets->rrule(RecurrencePreset::Weekly, $thirdTuesday))->toBe('FREQ=WEEKLY;BYDAY=TU')
        ->and($presets->rrule(RecurrencePreset::MonthlyNthWeekday, $thirdTuesday))->toBe('FREQ=MONTHLY;BYDAY=3TU')
        ->and($presets->rrule(RecurrencePreset::HalfYearly, $thirdTuesday))->toBe('FREQ=MONTHLY;INTERVAL=6')
        ->and($presets->rrule(RecurrencePreset::Yearly, $thirdTuesday))->toBe('FREQ=YEARLY');
});

it('evaluates "3rd Tuesday of the month" correctly', function () {
    $schedule = new RecurrenceSchedule('FREQ=MONTHLY;BYDAY=3TU', CarbonImmutable::parse('2026-05-19'));

    expect($schedule->occursOn(CarbonImmutable::parse('2026-06-16')))->toBeTrue()  // 3rd Tue June
        ->and($schedule->occursOn(CarbonImmutable::parse('2026-06-09')))->toBeFalse() // 2nd Tue June
        ->and($schedule->nextOnOrAfter(CarbonImmutable::parse('2026-05-20'))->toDateString())->toBe('2026-06-16');
});

it('sets a recurrence on a todo and claims it as the first instance', function () {
    $todo = app(CreateTodo::class)($this->user, ['title' => 'Standup']);
    $today = CarbonImmutable::today();

    $this->post(route('todos.recurrence.store', $todo), [
        'preset' => RecurrencePreset::Daily->value,
        'anchor_date' => $today->toDateString(),
    ])->assertRedirect();

    $recurrence = Recurrence::firstWhere('user_id', $this->user->id);
    expect($recurrence)->not->toBeNull()
        ->and($recurrence->rrule)->toBe('FREQ=DAILY')
        ->and($recurrence->active)->toBeTrue();

    $todo->refresh();
    expect($todo->recurrence_id)->toBe($recurrence->id)
        ->and($todo->occurred_on->toDateString())->toBe($today->toDateString());
});

it('accepts a valid custom rrule', function () {
    $todo = app(CreateTodo::class)($this->user, ['title' => 'Sprint review']);

    $this->post(route('todos.recurrence.store', $todo), [
        'rrule' => 'FREQ=WEEKLY;INTERVAL=2;BYDAY=MO,TH',
        'anchor_date' => CarbonImmutable::today()->toDateString(),
    ])->assertRedirect();

    $recurrence = Recurrence::firstWhere('user_id', $this->user->id);
    expect($recurrence->rrule)->toBe('FREQ=WEEKLY;INTERVAL=2;BYDAY=MO,TH')
        ->and($todo->fresh()->recurrence_id)->toBe($recurrence->id);
});

it('rejects an invalid custom rrule', function () {
    $todo = app(CreateTodo::class)($this->user, ['title' => 'Kapot']);

    $this->post(route('todos.recurrence.store', $todo), ['rrule' => 'FREQ=NONSENSE'])
        ->assertSessionHasErrors('rrule');

    expect(Recurrence::count())->toBe(0);
});

it('materializes a due recurrence onto a day, exactly once', function () {
    $recurrence = Recurrence::factory()->daily()->for($this->user)->create([
        'dtstart' => CarbonImmutable::today()->subWeek(),
    ]);
    $date = CarbonImmutable::today()->addDay();

    $created = app(MaterializeRecurrences::class)($this->user, $date);
    $again = app(MaterializeRecurrences::class)($this->user, $date);

    expect($created)->toBe(1)
        ->and($again)->toBe(0);

    $list = $this->user->lists()->where('type', ListType::Daily)->whereDate('date', $date)->first();
    expect($list->todos)->toHaveCount(1)
        ->and($list->todos->first()->title)->toBe($recurrence->title)
        ->and($list->todos->first()->recurrence_id)->toBe($recurrence->id);
});

it('does not materialize an inactive recurrence', function () {
    Recurrence::factory()->daily()->inactive()->for($this->user)->create([
        'dtstart' => CarbonImmutable::today()->subWeek(),
    ]);

    $created = app(MaterializeRecurrences::class)($this->user, CarbonImmutable::today()->addDay());

    expect($created)->toBe(0);
});

it('excludes recurrence instances from carry-over candidates', function () {
    $previousWorkday = Workday::lastWorkdayBefore(CarbonImmutable::today());
    $previousList = app(GetOrCreateDailyList::class)($this->user, $previousWorkday);

    $normal = app(CreateTodo::class)($this->user, ['title' => 'Echte carry-over']);
    app(AddTodoToList::class)($normal, $previousList);

    // An instance left over from a recurrence on the previous workday.
    $recurrence = Recurrence::factory()->daily()->inactive()->for($this->user)->create();
    $instance = Todo::factory()->for($this->user)->create([
        'recurrence_id' => $recurrence->id,
        'occurred_on' => $previousWorkday,
        'title' => 'Recurring instance',
    ]);
    app(AddTodoToList::class)($instance, $previousList);

    $this->get(route('today.show'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('needsRitual', true)
            ->has('carryOverCandidates', 1)
            ->where('carryOverCandidates.0.title', 'Echte carry-over'));
});

it('splits ritual carry-over into previous-workday and earlier buckets', function () {
    $previousWorkday = Workday::lastWorkdayBefore(CarbonImmutable::today());
    $earlierDay = Workday::lastWorkdayBefore($previousWorkday);

    $prevList = app(GetOrCreateDailyList::class)($this->user, $previousWorkday);
    $recent = app(CreateTodo::class)($this->user, ['title' => 'Van vorige werkdag']);
    app(AddTodoToList::class)($recent, $prevList);

    $oldList = app(GetOrCreateDailyList::class)($this->user, $earlierDay);
    $old = app(CreateTodo::class)($this->user, ['title' => 'Al langer blijven liggen']);
    app(AddTodoToList::class)($old, $oldList);

    $this->get(route('today.show'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('needsRitual', true)
            ->has('carryOverCandidates', 1)
            ->where('carryOverCandidates.0.title', 'Van vorige werkdag')
            ->has('earlierCandidates', 1)
            ->where('earlierCandidates.0.title', 'Al langer blijven liggen'));
});

it('surfaces a due recurrence as pre-scheduled when opening today', function () {
    Recurrence::factory()->daily()->for($this->user)->create([
        'dtstart' => CarbonImmutable::today()->subWeek(),
    ]);

    $this->get(route('today.show'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('needsRitual', true)
            ->has('preScheduled', 1));
});

it('stops a recurrence so no further instances are generated', function () {
    $recurrence = Recurrence::factory()->daily()->for($this->user)->create([
        'dtstart' => CarbonImmutable::today()->subWeek(),
    ]);

    $this->delete(route('recurrences.destroy', $recurrence))->assertRedirect();

    expect($recurrence->fresh()->active)->toBeFalse();

    $created = app(MaterializeRecurrences::class)($this->user, CarbonImmutable::today()->addDay());
    expect($created)->toBe(0);
});

it('detaches instances when a recurrence is stopped', function () {
    $recurrence = Recurrence::factory()->daily()->for($this->user)->create();
    $instance = Todo::factory()->for($this->user)->create([
        'recurrence_id' => $recurrence->id,
        'occurred_on' => CarbonImmutable::today(),
    ]);

    $this->delete(route('recurrences.destroy', $recurrence))->assertRedirect();

    expect($recurrence->fresh()->active)->toBeFalse()
        ->and($instance->fresh()->recurrence_id)->toBeNull()
        ->and($instance->fresh()->occurred_on)->toBeNull();
});

it('updates the existing recurrence instead of creating a second one', function () {
    $todo = app(CreateTodo::class)($this->user, ['title' => 'Standup']);
    $today = CarbonImmutable::today()->toDateString();

    $this->post(route('todos.recurrence.store', $todo), ['preset' => 'daily', 'anchor_date' => $today])
        ->assertRedirect();
    $this->post(route('todos.recurrence.store', $todo), ['preset' => 'weekdays', 'anchor_date' => $today])
        ->assertRedirect();

    expect(Recurrence::where('user_id', $this->user->id)->count())->toBe(1)
        ->and(Recurrence::firstWhere('user_id', $this->user->id)->rrule)->toBe('FREQ=WEEKLY;BYDAY=MO,TU,WE,TH,FR');
});

it('materializes recurrences for all users via the scheduled command', function () {
    Recurrence::factory()->daily()->for($this->user)->create([
        'dtstart' => CarbonImmutable::today()->subWeek(),
    ]);

    $this->artisan('recurrences:materialize', ['--days' => 0])->assertSuccessful();

    $list = $this->user->lists()
        ->where('type', ListType::Daily)
        ->whereDate('date', CarbonImmutable::today())
        ->first();

    expect($list)->not->toBeNull()
        ->and($list->todos)->toHaveCount(1);
});
