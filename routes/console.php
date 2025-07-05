<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('twitter:refresh-inactive --mode=light --limit=3')
    ->everyThirtyMinutes()
    ->withoutOverlapping(600);

Schedule::command('twitter:fetch-tweets --mode=all --limit=3 --max-keyword=3')
    ->everyFifteenMinutes()
    ->withoutOverlapping(540);

Schedule::command('twitter:replies-queue --limit=5')
    ->everyFiveMinutes()
    ->withoutOverlapping(30);
