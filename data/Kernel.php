<?php
// this is a dummy kernel file for Laravel scheduler

namespace App\Adapter\Laravel;

use Illuminate\Console\Scheduling\Schedule;

class Kernel
{
    /**
     * Define the application's command schedule.
     */
    public function schedule(Schedule $schedule)
    {
        // Define your scheduled tasks here
        echo "Inside Kernel::schedule method\n";
        $schedule->exec('ping -c3 www.seznam.cz')->everyMinute();
        $schedule->exec('ping -c10 www.seznam.cz')->everyMinute();
    }
}
