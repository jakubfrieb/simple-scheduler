<?php

namespace App\Adapter\Laravel;

use App\Adapter\SchedulerInterface;
use App\Adapter\TaskLoaderInterface;
use Illuminate\Console\Scheduling\Schedule;

class LaravelTaskLoader implements TaskLoaderInterface
{
    private string $kernelPath;

    public function __construct(?string $kernelPath = null)
    {
        $this->kernelPath = $kernelPath ?? __DIR__ . '/../../../data/Kernel.php';
    }

    /**
     * @throws \Exception
     */
    public function loadTasks(SchedulerInterface $scheduler): void
    {
        // todo task loader not working, tasks are loaded in ContainerBootstrap
        // The tasks are already loaded in the ContainerBootstrap
        // Count the tasks in the Kernel.php file
        if (!file_exists($this->kernelPath)){
            throw new \Exception("Kernel file not found at: " . $this->kernelPath);
        }
        // load dummy kernel as we don't have full laravel project
        require_once $this->kernelPath;

        // Create an instance of the Kernel (our tasks definitions)
        $kernel = new Kernel();
        $scheduler->addTasksFromKernel($kernel);
        $taskCount = count($scheduler->getEvents());

        // This method is just here to satisfy the interface
        echo "Tasks loaded from " . basename($this->kernelPath) . " ({$taskCount} tasks found)\n";
    }

    public function addTask(string $command, string $frequency, ?string $description = null): void
    {
        // For Laravel, we'd need to modify the Kernel.php file
        $kernelContent = file_get_contents($this->kernelPath);

        // Find the schedule method
        if (preg_match('/protected function schedule\(Schedule \$schedule\)\s*{(.*?)}/s', $kernelContent, $matches)) {
            $scheduleBody = $matches[1];

            // Create the new task definition
            $newTask = "\n        \$schedule->exec('{$command}')";

            switch ($frequency) {
                case 'daily':
                    $newTask .= "->daily()";
                    break;
                case 'hourly':
                    $newTask .= "->hourly()";
                    break;
                case 'everyMinute':
                    $newTask .= "->everyMinute()";
                    break;
                default:
                    if (strpos($frequency, 'at:') === 0) {
                        $time = substr($frequency, 3);
                        $newTask .= "->dailyAt('{$time}')";
                    }
            }

            if ($description) {
                $newTask .= "->description('{$description}')";
            }

            $newTask .= ";";

            // Add the new task to the schedule method
            $updatedScheduleBody = $scheduleBody . $newTask;
            $updatedKernelContent = str_replace($scheduleBody, $updatedScheduleBody, $kernelContent);

            file_put_contents($this->kernelPath, $updatedKernelContent);
        }
    }

    public function removeTask(string $taskId): bool
    {
        // For Laravel, we'd need to parse the Kernel.php file and remove the matching task
        // This is complex and would require more sophisticated parsing
        return false;
    }
}
