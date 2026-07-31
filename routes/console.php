<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('audit:prune')->dailyAt('02:30')->withoutOverlapping();

Schedule::command('marketplace:scan-due')->hourly()->withoutOverlapping();

Schedule::command('marketplace:scan-saved-searches')->hourly()->withoutOverlapping();
