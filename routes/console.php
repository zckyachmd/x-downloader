<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('twitter:refresh-inactive --limit=3')
    ->everyThirtyMinutes()
    ->withoutOverlapping(600);

Schedule::command('twitter:fetch-tweets --limit=3')
    ->everyTenMinutes()
    ->withoutOverlapping(300);

Schedule::command('twitter:replies-queue --limit=5')
    ->everyMinute()
    ->withoutOverlapping(30);
