<?php

use Illuminate\Support\Facades\Schedule;

// Expired session tokens are already rejected; this keeps the table small.
Schedule::command('sanctum:prune-expired --hours=24')->daily();
