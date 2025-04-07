#!/usr/bin/env php
<?php

require __DIR__ . '/../../vendor/autoload.php';

use App\Adapter\Simple\TaskRepository;

// Create repository
$repository = new TaskRepository();

// Clear the task_executions table
$result = $repository->clearExecutions();

if ($result) {
    echo "Task executions table has been cleared successfully.\n";
} else {
    echo "Failed to clear task executions table.\n";
}
