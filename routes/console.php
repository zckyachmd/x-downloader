<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('twitter:refresh-inactive')
    ->hourly()
    ->withoutOverlapping(1200);

Schedule::command('twitter:fetch-tweets')
    ->everyThirtyMinutes()
    ->withoutOverlapping(1080);

Schedule::command('twitter:replies-queue')
    ->everyFiveMinutes()
    ->withoutOverlapping(280);

Schedule::command('twitter:replies-clean --days=7')
    ->at('00:00')
    ->withoutOverlapping(300);
