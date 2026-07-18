<?php

namespace App\Services\Migration;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Central service for VOZARA → LENDR data migration.
 *
 * Provides helpers used by all migration:vozara:* Artisan commands:
 *  - Legacy DB connection management
 *  - migration_log persistence
 *  - Idempotency checks
 *  - Batch chunking
 */
class MigrationService
{
    private string $tenantId;

    public function __construct(string $tenantId)
    {
        $this->tenantId = $tenantId;
    }

    // ─── Legacy connection ────────────────────────────────────────────────────

    /**
     * Returns a query builder against the VOZARA legacy database.
     * Reads from the 'vozara' connection defined in config/database.php.
     */
    public function legacy(): \Illuminate\Database\ConnectionInterface
    {
        return DB::connection('vozara');
    }

    // ─── Migration log ────────────────────────────────────────────────────────

    /**
     * Record a successful row migration.
     */
    public function logSuccess(string $table, int $legacyId, int $newId, string $notes = ''): void
    {
        DB::table('migration_log')->insert([
            'tenant_id' => $this->tenantId,
            'table_name' => $table,
            'legacy_id' => $legacyId,
            'new_id' => $newId,
            'status' => 'success',
            'notes' => $notes ?: null,
            'migrated_at' => now(),
        ]);
    }

    /**
     * Record a skipped row (already migrated or out-of-scope).
     */
    public function logSkipped(string $table, int $legacyId, string $reason = ''): void
    {
        DB::table('migration_log')->insert([
            'tenant_id' => $this->tenantId,
            'table_name' => $table,
            'legacy_id' => $legacyId,
            'new_id' => null,
            'status' => 'skipped',
            'notes' => $reason ?: null,
            'migrated_at' => now(),
        ]);
    }

    /**
     * Record a failed row migration.
     */
    public function logFailed(string $table, int $legacyId, string $reason): void
    {
        DB::table('migration_log')->insert([
            'tenant_id' => $this->tenantId,
            'table_name' => $table,
            'legacy_id' => $legacyId,
            'new_id' => null,
            'status' => 'failed',
            'notes' => $reason,
            'migrated_at' => now(),
        ]);

        Log::error("[Migration:{$table}] Failed on legacy_id={$legacyId}: {$reason}");
    }

    // ─── Idempotency ──────────────────────────────────────────────────────────

    /**
     * Returns true if the legacy row was already successfully migrated.
     */
    public function alreadyMigrated(string $table, int $legacyId): bool
    {
        return DB::table('migration_log')
            ->where('tenant_id', $this->tenantId)
            ->where('table_name', $table)
            ->where('legacy_id', $legacyId)
            ->where('status', 'success')
            ->exists();
    }

    /**
     * Look up the LENDR new_id for a previously migrated legacy record.
     */
    public function newId(string $table, int $legacyId): ?int
    {
        return DB::table('migration_log')
            ->where('tenant_id', $this->tenantId)
            ->where('table_name', $table)
            ->where('legacy_id', $legacyId)
            ->where('status', 'success')
            ->value('new_id');
    }

    // ─── Rollback ─────────────────────────────────────────────────────────────

    /**
     * Remove all migration_log entries for this tenant (used by rollback command).
     */
    public function clearLog(?string $table = null): int
    {
        $query = DB::table('migration_log')->where('tenant_id', $this->tenantId);

        if ($table) {
            $query->where('table_name', $table);
        }

        return $query->delete();
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    /**
     * Get migration progress summary for all steps.
     */
    public function progressSummary(): array
    {
        return DB::table('migration_log')
            ->where('tenant_id', $this->tenantId)
            ->selectRaw('table_name, status, COUNT(*) as count, MAX(migrated_at) as last_run')
            ->groupBy('table_name', 'status')
            ->orderBy('table_name')
            ->get()
            ->groupBy('table_name')
            ->map(fn ($rows) => $rows->keyBy('status'))
            ->toArray();
    }

    public function tenantId(): string
    {
        return $this->tenantId;
    }
}
