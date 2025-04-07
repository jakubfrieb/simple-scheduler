#!/usr/bin/env php
<?php

require __DIR__ . '/../../vendor/autoload.php';

use App\SchedulerFactory;

// Process arguments
if ($argc < 2) {
    echo "Usage: php remove-task.php <task_id>\n";
    echo "To see all task IDs, run: php list-tasks.php\n";
    exit(1);
}

$taskId = $argv[1];
$force = isset($argv[2]) && $argv[2] === '--force';

try {
    // Create scheduler and get repository
    $scheduler = SchedulerFactory::create('simple');
    $repository = $scheduler->getRepository();

    // Check if task exists
    $task = $repository->findById($taskId);

    if (!$task) {
        echo "Task with ID '$taskId' not found.\n";
        exit(1);
    }

    // Ask for confirmation unless --force is used
    if (!$force) {
        echo "Are you sure you want to remove task '{$task->getDescription()}' with command '{$task->getCommand()}'? [y/N]: ";
        $handle = fopen("php://stdin", "r");
        $line = fgets($handle);
        if (trim(strtolower($line)) != 'y') {
            echo "Operation cancelled.\n";
            exit(0);
        }
    }

    // Remove the task
    $repository->delete($taskId);

    echo "Task with ID '$taskId' has been removed.\n";
} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
