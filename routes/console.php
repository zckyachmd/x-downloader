<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('twitter:refresh-inactive')
    ->withoutOverlapping()
    ->hourly();
