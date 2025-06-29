<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('twitter:refresh-inactive --limit=5')
    ->everyFifteenMinutes()
    ->withoutOverlapping();

Schedule::command('twitter:fetch-tweets --mode=fresh --limit=10')
    ->everyFiveMinutes()
    ->withoutOverlapping(600);

Schedule::command('twitter:fetch-tweets --mode=historical --limit=10')
    ->hourly()
    ->withoutOverlapping(900);

Schedule::command('twitter:replies-queue --limit=5')
    ->everyMinute()
    ->withoutOverlapping(60);
