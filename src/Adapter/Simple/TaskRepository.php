<?php

namespace App\Adapter\Simple;

use PDO;

class TaskRepository
{
    private PDO $db;

    public function __construct(?string $dbPath = null)
    {
        $dbPath = $dbPath ?? __DIR__ . '/../../../data/scheduler.sqlite';
        $dirPath = dirname($dbPath);

        if (!file_exists($dirPath)) {
            mkdir($dirPath, 0755, true);
        }

        $this->db = new PDO('sqlite:' . $dbPath);
        $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->initDatabase();
    }

    private function initDatabase(): void
    {
        $this->db->exec('
            CREATE TABLE IF NOT EXISTS tasks (
                id TEXT PRIMARY KEY,
                command TEXT NOT NULL,
                expression TEXT NOT NULL,
                description TEXT NULL,
                status TEXT DEFAULT "pending",
                executed_at DATETIME
            );
            
            CREATE TABLE IF NOT EXISTS task_executions (
                run_id INTEGER PRIMARY KEY AUTOINCREMENT,
                task_id TEXT,
                executed_at TEXT,
                status INTEGER,
                output TEXT,
                duration FLOAT,
                memory_usage_mb FLOAT,
                pid INTEGER,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
            );
            ');
    }

    public function save(Task $task): void
    {


        $stmt = $this->db->prepare('
            INSERT OR REPLACE INTO tasks (id, command, expression, description)
            VALUES (:id, :command, :expression, :description)
        ');

        $stmt->execute([
            ':id' => $task->getId(),
            ':command' => $task->getCommand(),
            ':expression' => $task->getExpression(),
            ':description' => $task->getDescription()
        ]);

        //catch if there is error
        if ($stmt->errorCode() !== '00000') {
            throw new \Exception('Failed to save task: ' . $stmt->errorInfo()[2]);
        }

    }

    public function findAll(): array
    {
        $stmt = $this->db->query('SELECT * FROM tasks');
        $tasks = [];

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $task = new Task($row['command']);
            $this->hydrateTask($task, $row);
            $tasks[] = $task;
        }

        return $tasks;
    }

    public function findDue(\DateTimeInterface $now): array
    {
        $tasks = $this->findAll();
        return array_filter($tasks, fn (Task $task) => $task->isDue($now));
    }

    public function recordExecution(string $taskId, \DateTimeInterface $executedAt, ?int $status, string $output): int
    {
        $stmt = $this->db->prepare('
            INSERT INTO task_executions (task_id, executed_at, status, output)
            VALUES (:task_id, :executed_at, :status, :output)
        ');

        $stmt->execute([
            ':task_id' => $taskId,
            ':executed_at' => $executedAt->format('Y-m-d H:i:s'),
            ':status' => $status,
            ':output' => $output
        ]);

        return (int)$this->db->lastInsertId();
    }

    public function findById(string $id): ?Task
    {
        $stmt = $this->db->prepare('SELECT * FROM tasks WHERE id = :id');
        $stmt->execute([':id' => $id]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            return null;
        }

        $task = new Task($row['command']);
        $this->hydrateTask($task, $row);

        return $task;
    }

    public function delete(string $id): bool
    {
        // First delete task executions
        $stmt = $this->db->prepare('DELETE FROM task_executions WHERE task_id = :id');
        $stmt->execute([':id' => $id]);

        // Then delete the task
        $stmt = $this->db->prepare('DELETE FROM tasks WHERE id = :id');
        $stmt->execute([':id' => $id]);

        return $stmt->rowCount() > 0;
    }

    private function hydrateTask(Task $task, array $data): void
    {
        // Use reflection to set private properties
        $reflectionClass = new \ReflectionClass(Task::class);

        $idProperty = $reflectionClass->getProperty('id');
        $idProperty->setAccessible(true);
        $idProperty->setValue($task, $data['id']);

        $expressionProperty = $reflectionClass->getProperty('expression');
        $expressionProperty->setAccessible(true);
        $expressionProperty->setValue($task, $data['expression']);

        if (isset($data['description']) && $data['description']) {
            $task->description($data['description']);
        }
    }

    public function markTaskCompleted(int $runId, int $status, string $output): void
    {
        $stmt = $this->db->prepare('
            UPDATE task_executions 
            SET status = :status, output = :output 
            WHERE run_id = :run_id 
            AND status IS NULL
        ');

        $stmt->execute([
            ':run_id' => $runId,
            ':status' => $status,
            ':output' => $output
        ]);
    }

    public function cleanupStaleTasks(int $hoursThreshold = 24): void
    {
        $threshold = new \DateTime("-{$hoursThreshold} hours");

        $stmt = $this->db->prepare('
            UPDATE task_executions 
            SET status = "error", output = "Task marked as failed - exceeded maximum runtime" 
            WHERE status IS NULL 
            AND executed_at < :threshold
        ');

        $stmt->execute([
            ':threshold' => $threshold->format('Y-m-d H:i:s')
        ]);
    }

    public function isTaskRunning(string $taskId): bool
    {
        $stmt = $this->db->prepare('
            SELECT COUNT(*) FROM task_executions 
            WHERE task_id = :task_id 
            AND status IS NULL
        ');

        $stmt->execute([':task_id' => $taskId]);
        return (int)$stmt->fetchColumn() > 0;
    }

    /**
     * Clears all records from the task_executions table
     *
     * @return bool True if successful, false otherwise
     */
    public function clearExecutions(): bool
    {
        try {
            $stmt = $this->db->prepare('DELETE FROM task_executions');
            return $stmt->execute();
        } catch (\Exception $e) {
            error_log('Failed to clear task_executions table: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Get the current status of a task from the tasks table
     *
     * @param string $taskId The ID of the task
     * @return string|null The status of the task, or null if not found
     */
    public function getTaskStatus(string $taskId): ?string
    {
        $stmt = $this->db->prepare('
            SELECT status FROM tasks 
            WHERE id = :task_id
        ');

        $stmt->execute([':task_id' => $taskId]);
        $result = $stmt->fetchColumn();

        return $result !== false ? $result : null;
    }
}
