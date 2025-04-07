#!/usr/bin/env php
<?php

require __DIR__ . '/../vendor/autoload.php';

use App\SchedulerFactory;

try {
    // echo current date and time
    echo "Current time: " . date('Y-m-d H:i:s') . "\n";

    // Create scheduler using factory
    // use env or default
    $schedulerType = getenv('SCHEDULER_TYPE') ?: 'simple';
    $mutexType = getenv('SCHEDULER_MUTEX_TYPE') ?: null;

    if (!$mutexType && $schedulerType === 'simple'){
        $mutexType = 'file'; // simple scheduler doesn't have default mutex
    }
    $scheduler = SchedulerFactory::create($schedulerType, $mutexType);

    // Run due tasks
    // TODO void method can't return to $results ...
    $results = $scheduler->run();

    // Output results
    if (empty($results)) {
        echo "No tasks to run.\n";
    } else {
        echo "Executed " . count($results) . " tasks:\n";

        foreach ($results as $taskId => $result) {
            $status = $result['status'];
            echo "- Task $taskId: $status\n";
        }
    }
} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
