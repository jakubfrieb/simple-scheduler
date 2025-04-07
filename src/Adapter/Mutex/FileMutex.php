<?php

namespace App\Adapter\Mutex;

use App\Adapter\MutexInterface;

class FileMutex implements MutexInterface
{
    private string $mutexType = 'file';
    private string $lockDir;

    // todo missing methods from laravel interface ... create, exists and forget

    public function __construct(string $lockDir = null)
    {
        $this->lockDir = $lockDir ?? __DIR__ . '/../../../data/locks';

        if (!file_exists($this->lockDir)) {
            mkdir($this->lockDir, 0755, true);
        }
    }

    public function getMutexType(): string
    {
        return $this->mutexType;
    }

    public function acquire(string $taskId): bool
    {
        $lockFile = $this->getLockFile($taskId);

        if (file_exists($lockFile)) {
            return false;
        }

        return file_put_contents($lockFile, time()) !== false;
    }

    public function release(string $taskId): bool
    {
        $lockFile = $this->getLockFile($taskId);

        if (!file_exists($lockFile)) {
            return true;
        }

        return unlink($lockFile);
    }

    public function exists(string $taskId): bool
    {
        return file_exists($this->getLockFile($taskId));
    }

    private function getLockFile(string $taskId): string
    {
        return $this->lockDir . '/' . md5($taskId) . '.lock';
    }
}
