<?php

use App\Support\Workday;
use Carbon\CarbonImmutable;

it('returns today as quick-add target on a weekday', function () {
    $wednesday = CarbonImmutable::create(2026, 5, 20);

    expect(Workday::quickAddTargetDate($wednesday)->toDateString())
        ->toBe('2026-05-20');
});

it('returns next Monday as quick-add target on Saturday', function () {
    $saturday = CarbonImmutable::create(2026, 5, 23);

    expect(Workday::quickAddTargetDate($saturday)->toDateString())
        ->toBe('2026-05-25');
});

it('returns next Monday as quick-add target on Sunday', function () {
    $sunday = CarbonImmutable::create(2026, 5, 24);

    expect(Workday::quickAddTargetDate($sunday)->toDateString())
        ->toBe('2026-05-25');
});

it('returns Friday as the last workday before Monday', function () {
    $monday = CarbonImmutable::create(2026, 5, 25);

    expect(Workday::lastWorkdayBefore($monday)->toDateString())
        ->toBe('2026-05-22');
});

it('returns yesterday as the last workday on Tuesday', function () {
    $tuesday = CarbonImmutable::create(2026, 5, 26);

    expect(Workday::lastWorkdayBefore($tuesday)->toDateString())
        ->toBe('2026-05-25');
});

it('skips weekend when looking back from a Saturday', function () {
    $saturday = CarbonImmutable::create(2026, 5, 23);

    expect(Workday::lastWorkdayBefore($saturday)->toDateString())
        ->toBe('2026-05-22');
});
