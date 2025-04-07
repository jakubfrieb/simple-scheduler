<?php

namespace App\Adapter\Mutex;

use App\Adapter\MutexInterface;
use PDO;

class DatabaseMutex implements MutexInterface
{
    private string $mutexType = 'database';

    private PDO $db;

    public function __construct(?string $dbPath = null)
    {
        $dbPath = $dbPath ?? __DIR__ . '/../../../data/scheduler.sqlite';
        $this->db = new PDO('sqlite:' . $dbPath);
        $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->initDatabase();
    }

    public function getMutexType(): string
    {
        return $this->mutexType;
    }

    private function initDatabase(): void
    {
        $this->db->exec('
            CREATE TABLE IF NOT EXISTS task_locks (
                task_id TEXT PRIMARY KEY,
                acquired_at INTEGER NOT NULL
            );
        ');
    }

    public function acquire(string $taskId): bool
    {
        if ($this->exists($taskId)) {
            return false;
        }

        try {
            $stmt = $this->db->prepare('
                INSERT INTO task_locks (task_id, acquired_at)
                VALUES (:task_id, :acquired_at)
            ');

            return $stmt->execute([
                ':task_id' => $taskId,
                ':acquired_at' => time()
            ]);
        } catch (\Exception $e) {
            return false;
        }
    }

    public function release(string $taskId): bool
    {
        try {
            $stmt = $this->db->prepare('
                DELETE FROM task_locks
                WHERE task_id = :task_id
            ');

            return $stmt->execute([':task_id' => $taskId]);
        } catch (\Exception $e) {
            return false;
        }
    }

    public function exists(string $taskId): bool
    {
        $stmt = $this->db->prepare('
            SELECT COUNT(*) FROM task_locks
            WHERE task_id = :task_id
        ');

        $stmt->execute([':task_id' => $taskId]);
        return (int)$stmt->fetchColumn() > 0;
    }
}
