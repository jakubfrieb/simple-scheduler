<?php

namespace App\Adapter;

interface StorageInterface
{
    /**
     * Save task execution details.
     */
    public function saveExecutionDetails(string $taskId, string $status, string $output): void;

    /**
     * Retrieve task execution details.
     */
    public function getExecutionDetails(string $taskId): array;
}
