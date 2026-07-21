<?php

use Illuminate\Console\Scheduling\Schedule;

it('schedules tangent:push-sales hourly', function () {
    $events = app(Schedule::class)->events();

    $hourly = collect($events)->first(
        fn ($event) => str_contains((string) $event->command, 'tangent:push-sales')
            && ! str_contains((string) $event->command, '--force')
    );

    expect($hourly)->not->toBeNull();
    expect($hourly->expression)->toBe('0 * * * *');
});

it('schedules a daily forced re-send of the past-7-days window', function () {
    $events = app(Schedule::class)->events();

    $daily = collect($events)->first(
        fn ($event) => str_contains((string) $event->command, 'tangent:push-sales --force')
    );

    expect($daily)->not->toBeNull();
    expect($daily->expression)->toBe('45 23 * * *');
});
