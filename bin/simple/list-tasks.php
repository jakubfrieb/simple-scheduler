#!/usr/bin/env php
<?php

require __DIR__ . '/../../vendor/autoload.php';

use App\Adapter\Simple\TaskRepository;


// Create repository
$repository = new TaskRepository();

// Get all tasks
$tasks = $repository->findAll();

if (empty($tasks)) {
    echo "No tasks found.\n";
    exit(0);
}

// Display tasks in a table format
echo str_repeat('-', 80) . "\n";
printf("%-20s %-15s %-30s %s\n", "ID", "FREQUENCY", "COMMAND", "DESCRIPTION");
echo str_repeat('-', 80) . "\n";

foreach ($tasks as $task) {
    $id = $task->getId(); // Truncate ID for display
    $expression = $task->getExpression();

    // Convert cron expression to human-readable format
    $frequency = match($expression) {
        '0 0 * * *' => 'daily',
        '0 * * * *' => 'hourly',
        '* * * * *' => 'every minute',
        default => $expression
    };

    $command = substr($task->getCommand(), 0, 30);
    if (strlen($task->getCommand()) > 30) {
        $command .= '...';
    }

    $description = $task->getDescription() ?? '';

    printf("%-20s %-15s %-30s %s\n", $id, $frequency, $command, $description);
}

echo str_repeat('-', 80) . "\n";
