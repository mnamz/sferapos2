<?php

use Illuminate\Console\Scheduling\Schedule;

it('schedules tangent:push-sales hourly', function () {
    $events = app(Schedule::class)->events();

    $found = collect($events)->contains(
        fn ($event) => str_contains((string) $event->command, 'tangent:push-sales')
    );

    expect($found)->toBeTrue();
});
