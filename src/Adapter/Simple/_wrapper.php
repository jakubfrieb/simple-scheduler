<?php
// this is a wrapper (part of simple-scheduler) script that runs a command and logs its output
// it also updates the database with the command's status and output
// it is intended to be called from the scheduler

// TODO implement configurable timeout (in case command is like `ping www.site.com` which is never ending...)

namespace App\Adapter\Simple;

require __DIR__ . '/../../../vendor/autoload.php';

use App\Adapter\Mutex\DatabaseMutex;
use App\Adapter\Mutex\FileMutex;
use App\Adapter\Mutex\SymfonyLockMutex;
use App\Adapter\MutexInterface;
use Exception;
use PDO;

// Hardcoded paths
$dbPath = __DIR__ . '/../../../data/scheduler.sqlite';
$logDir = __DIR__ . '/../../../logs';
$logFile = $logDir . '/wrapper.log';

// Ensure log directory exists
if (!file_exists($logDir)) {
    mkdir($logDir, 0755, true);
}

// Get command from arguments
if ($argc < 4) {
    file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] ERROR: No command provided\n", FILE_APPEND);
    fwrite(STDERR, "Usage: php wrapper.php <task_id> <record_id> <mutex_type> <command>\n");
    exit(1);
}

$taskId = $argv[1];
$recordId = $argv[2];
$mutexType = $argv[3];
$cmdParts = array_slice($argv, 4);
$command = implode(' ', $cmdParts);

try {
    $wrapper = new CommandWrapper($dbPath, $logFile, $argv, $mutexType);
    $wrapper->runCommand($command, (string)$recordId, (string)$taskId);
} catch (Exception $e) {
    file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] ERROR: " . $e->getMessage() . "\n", FILE_APPEND);
    exit(2);
}

class CommandWrapper
{
    private $pdo;
    private string $logFile;
    private MutexInterface $mutex;

    public function __construct(string $dbPath, string $logFile, array $argv, string $mutexType)
    {
        $this->logFile = $logFile;
        $this->pdo = new PDO("sqlite:$dbPath");
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->log("Wrapper started with arguments: " . implode(' ', $argv));

        switch ($mutexType) {
            case 'file':
                $this->mutex = new FileMutex();
                break;
            case 'database':
                $this->mutex = new DatabaseMutex();
                break;
            case 'symfony':
                $this->mutex = new SymfonyLockMutex();
                break;
            default:
                throw new \Exception("Unknown mutex type: {$mutexType}");
        }
    }

    /**
     * @param string $command
     * @param string $recordId - identifier for current run
     * @param string $taskId   - unique task identifier
     * @return void
     */
    public function runCommand(string $command, string $recordId, string $taskId): void
    {
        $start = microtime(true);
        $startMem = memory_get_usage();

        // Log command start
        $this->log("Starting command execution for ID: $recordId");
        $this->log("Command: $command");

        // Run command and capture output
        $outputFile = sys_get_temp_dir() . "/task_" . $recordId . ".out";
        $cmdToRun = "$command > $outputFile 2>&1 & echo $!";
        $output = [];
        $this->updateDb($recordId, 'fetching_pid', '', 0, 0, null);
        exec($cmdToRun, $output);

        $pid = isset($output[0]) ? (int)$output[0] : null;

        // Update DB with running status and PID
        $this->log("PID: $pid");
        $this->updateDb($recordId, 'running', '', 0, 0, $pid);
        $this->log("Task marked as running with PID: $pid");

        // Wait for process to complete
        if ($pid) {
            $this->log("Waiting for process $pid to complete...");

            // Check process status every second
            while ($this->isProcessRunning($pid)) {
                sleep(1);
            }

            // Process completed, get output
            $outputText = file_exists($outputFile) ? file_get_contents($outputFile) : '';
            @unlink($outputFile); // Clean up

            // Calculate stats
            $end = microtime(true);
            $endMem = memory_get_peak_usage();
            $duration = round($end - $start, 4);
            $memoryMB = round(($endMem - $startMem) / 1024 / 1024, 2);

            // Update DB with completed status
            $this->log("Process completed. Duration: {$duration}s");
            $this->updateDb($recordId, 'completed', $outputText, $duration, $memoryMB, $pid);
            $this->log("Task marked as completed in database");
            if (!$this->mutex->release($taskId)) {
                $this->log("Failed to release mutex for task $taskId / run $recordId");
            }
        } else {
            $this->log("Failed to get PID, cannot track process");
            $this->updateDb($recordId, 'error', 'Failed to start process', 0, 0, null);
        }
    }

    private function isProcessRunning(int $pid): bool
    {
        // For Linux/Unix
        if (PHP_OS_FAMILY !== 'Windows') {
            exec("ps -p $pid -o pid=", $output);
            return count($output) > 0;
        }

        // For Windows
        exec("tasklist /FI \"PID eq $pid\" /NH", $output);
        return count($output) > 1;
    }

    private function updateDb(int $runId, string $status, string $output, float $duration, float $memoryMB, ?int $pid): void
    {
        // Update task_executions table
        $stmt = $this->pdo->prepare("
            UPDATE task_executions 
            SET status = :status,
                output = :output,
                duration = :duration,
                memory_usage_mb = :memory,
                pid = :pid,
                updated_at = DATETIME('now')
            WHERE run_id = :run_id
        ");
        $stmt->execute([
            'status' => $status,
            'output' => $output,
            'duration' => $duration,
            'memory' => $memoryMB,
            'pid' => $pid,
            'run_id' => $runId,
        ]);

        // Get the task_id for this run
        $stmt = $this->pdo->prepare("
            SELECT task_id FROM task_executions WHERE run_id = :run_id
        ");
        $stmt->execute(['run_id' => $runId]);
        $taskId = $stmt->fetchColumn();

        // Also update the tasks table with the latest status
        if ($taskId) {
            $stmt = $this->pdo->prepare("
                UPDATE tasks 
                SET status = :status,
                    executed_at = DATETIME('now')
                WHERE id = :task_id
            ");
            $stmt->execute([
                'status' => $status,
                'task_id' => $taskId,
            ]);
        }
    }

    private function log(string $message): void
    {
        file_put_contents($this->logFile, "[" . date('Y-m-d H:i:s') . "] $message\n", FILE_APPEND);
    }
}
