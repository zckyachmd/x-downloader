<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('twitter:refresh-inactive --mode=light --limit=3')
    ->hourly()
    ->withoutOverlapping(1200);

Schedule::command('twitter:fetch-tweets --mode=all --limit=3 --max-keyword=3')
    ->everyThirtyMinutes()
    ->withoutOverlapping(1080);

Schedule::command('twitter:replies-queue --limit=3 --max-account=2 --usage=85 --mode=aggressive')
    ->everyFiveMinutes()
    ->withoutOverlapping(280);

Schedule::command('twitter:replies-clean --days=7')
    ->at('00:00')
    ->withoutOverlapping(300);
