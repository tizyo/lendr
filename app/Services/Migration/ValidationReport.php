<?php

namespace App\Services\Migration;

/**
 * Immutable value object returned by the validation suite.
 */
final class ValidationReport
{
    public const STATUS_PASSED = 'PASSED';

    public const STATUS_WARNING = 'WARNING';

    public const STATUS_FAILED = 'FAILED';

    /** @var array<string, array{status: string, detail: string}> */
    private array $checks = [];

    public function addCheck(string $name, string $status, string $detail): self
    {
        $clone = clone $this;
        $clone->checks[$name] = ['status' => $status, 'detail' => $detail];

        return $clone;
    }

    public function passed(string $name, string $detail): self
    {
        return $this->addCheck($name, self::STATUS_PASSED, $detail);
    }

    public function warning(string $name, string $detail): self
    {
        return $this->addCheck($name, self::STATUS_WARNING, $detail);
    }

    public function failed(string $name, string $detail): self
    {
        return $this->addCheck($name, self::STATUS_FAILED, $detail);
    }

    public function checks(): array
    {
        return $this->checks;
    }

    public function overallPassed(): bool
    {
        foreach ($this->checks as $check) {
            if ($check['status'] === self::STATUS_FAILED) {
                return false;
            }
        }

        return true;
    }

    public function hasFailures(): bool
    {
        return ! $this->overallPassed();
    }

    public function countByStatus(string $status): int
    {
        return count(array_filter(
            $this->checks,
            fn ($c) => $c['status'] === $status,
        ));
    }
}
