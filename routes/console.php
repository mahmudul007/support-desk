<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schedule;

Schedule::command('tickets:escalate-sla')
    ->everyMinute()
    ->withoutOverlapping();
