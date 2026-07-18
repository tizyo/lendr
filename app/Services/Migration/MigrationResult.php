<?php

namespace App\Services\Migration;

/**
 * Immutable value object returned by each migration step.
 */
final class MigrationResult
{
    public function __construct(
        public readonly string $step,
        public readonly int $migrated = 0,
        public readonly int $skipped = 0,
        public readonly int $failed = 0,
        public readonly bool $dryRun = false,
        public readonly array $errors = [],
    ) {}

    public function isSuccess(): bool
    {
        return $this->failed === 0;
    }

    public function total(): int
    {
        return $this->migrated + $this->skipped + $this->failed;
    }

    public function toArray(): array
    {
        return [
            'step' => $this->step,
            'migrated' => $this->migrated,
            'skipped' => $this->skipped,
            'failed' => $this->failed,
            'dry_run' => $this->dryRun,
            'errors' => $this->errors,
            'success' => $this->isSuccess(),
        ];
    }
}
