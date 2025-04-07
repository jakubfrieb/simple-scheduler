<?php

use Crunz\Schedule;

$schedule = new Schedule();
$task = $schedule->run("echo \'Hello from Crunz task\'")->everyMinute()->description("Sample Crunz task");

return $schedule;