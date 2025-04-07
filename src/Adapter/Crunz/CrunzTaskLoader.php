<?php

namespace App\Adapter\Crunz;

use App\Adapter\SchedulerInterface;
use App\Adapter\TaskLoaderInterface;

class CrunzTaskLoader implements TaskLoaderInterface
{
    private string $tasksDir;

    public function __construct(?string $tasksDir = null)
    {
        $this->tasksDir = $tasksDir ?? __DIR__ . '/../../../data/tasks';
    }

    public function loadTasks(SchedulerInterface $scheduler): void
    {
        // Find all PHP files in the tasks directory
        $taskFiles = glob($this->tasksDir . '/*.php');
        $totalTaskFiles = count($taskFiles);
        $loadedSchedules = 0;
        $loadedEvents = 0;

        echo "Found {$totalTaskFiles} task files in {$this->tasksDir}\n";

        foreach ($taskFiles as $taskFile) {
            // Include the task file to get its schedule
            $crunzSchedule = require $taskFile;

            if ($crunzSchedule instanceof \Crunz\Schedule) {
                $loadedSchedules++;
                // Extract tasks from the Crunz schedule
                $events = $crunzSchedule->events();
                $fileEvents = count($events);
                $loadedEvents += $fileEvents;

                echo "Loaded schedule from " . basename($taskFile) . " with {$fileEvents} events\n";

                foreach ($events as $event) {
                    $command = $event->getCommand();
                    $task = $scheduler->task($command);

                    // Set frequency based on Crunz expression
                    $expression = $event->getExpression();

                    switch ($expression) {
                        case '0 0 * * *':
                            $task->daily();
                            break;
                        case '0 * * * *':
                            $task->hourly();
                            break;
                        case '* * * * *':
                            $task->everyMinute();
                            break;
                        default:
                            // Handle custom expressions
                            if (preg_match('/^(\d+) (\d+) \* \* \*$/', $expression, $matches)) {
                                $task->at(sprintf('%02d:%02d', $matches[2], $matches[1]));
                            }
                    }

                    // Check if the description method exists before calling it
                    try {
                        $reflection = new \ReflectionClass($event);
                        if ($reflection->hasMethod('getDescription')) {
                            $task->description($event->getDescription());
                        } elseif ($reflection->hasProperty('description')) {
                            $property = $reflection->getProperty('description');
                            $property->setAccessible(true);
                            $description = $property->getValue($event);
                            if ($description) {
                                $task->description($description);
                            }
                        }
                    } catch (\Exception $e) {
                        // Silently ignore if we can't get the description
                    }
                }
            } else {
                echo "Warning: " . basename($taskFile) . " does not return a valid Crunz\\Schedule instance\n";
            }
        }

        echo "Summary: Loaded {$loadedEvents} events from {$loadedSchedules} schedules (out of {$totalTaskFiles} files)\n";
    }

    public function addTask(string $command, string $frequency, ?string $description = null): void
    {
        // Create a new task file for Crunz
        $taskId = md5($command . uniqid());
        $filename = $this->tasksDir . '/' . $taskId . '.php';

        // Ensure tasks directory exists
        if (!file_exists($this->tasksDir)) {
            mkdir($this->tasksDir, 0755, true);
        }

        // Properly escape the command for PHP string
        $escapedCommand = addslashes($command);

        // Create the task file content
        $content = "<?php\n\n";
        $content .= "use Crunz\\Schedule;\n\n";
        $content .= "\$schedule = new Schedule();\n";
        $content .= "\$task = \$schedule->run(\"{$escapedCommand}\")";

        switch ($frequency) {
            case 'daily':
                $content .= "->daily()";
                break;
            case 'hourly':
                $content .= "->hourly()";
                break;
            case 'everyMinute':
                $content .= "->everyMinute()";
                break;
            default:
                if (strpos($frequency, 'at:') === 0) {
                    $time = substr($frequency, 3);
                    $content .= "->at('{$time}')";
                }
        }

        if ($description) {
            $escapedDescription = addslashes($description);
            $content .= "->description(\"{$escapedDescription}\")";
        }

        $content .= ";\n\n";
        $content .= "return \$schedule;";

        file_put_contents($filename, $content);
    }

    public function removeTask(string $taskId): bool
    {
        $taskFile = $this->tasksDir . '/' . $taskId . '.php';

        if (file_exists($taskFile)) {
            return unlink($taskFile);
        }

        return false;
    }

    /**
     * Get the tasks directory path
     */
    public function getTasksDir(): string
    {
        return $this->tasksDir;
    }
}
