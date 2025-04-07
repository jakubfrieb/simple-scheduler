<?php

use Crunz\Schedule;

$schedule = new Schedule();
$task = $schedule->run("ping -c5 www.seznam.cz")->everyMinute();

return $schedule;