#!/usr/bin/env php
<?php

require __DIR__ . '/../vendor/autoload.php';

use App\SchedulerFactory;

// Process arguments
if ($argc < 3) {
    echo "Usage: php add-task.php \"command\" \"frequency\" [\"description\"]\n";
    echo "Frequency: daily, hourly, everyMinute, at:HH:MM (UTC)\n";
    echo "Example: php add-task.php \"php /path/to/script.php\" \"daily\" \"Daily backup\"\n";
    exit(1);
}

$command = $argv[1];
$frequency = $argv[2];
$description = $argv[3] ?? null;

try {
    // Get the scheduler type from environment or use default
    $schedulerType = getenv('SCHEDULER_TYPE') ?: 'simple';

    // Get the loader for the specified scheduler type
    $loader = SchedulerFactory::getLoader($schedulerType);

    // Add the task using the loader
    $loader->addTask($command, $frequency, $description);

    echo "Task added successfully.\n";
} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
