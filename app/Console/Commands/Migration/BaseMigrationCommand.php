<?php

namespace App\Console\Commands\Migration;

use App\Services\Migration\MigrationResult;
use App\Services\Migration\MigrationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Base class for all migration:vozara:* commands.
 *
 * Provides:
 *  - --dry-run flag (report without writing)
 *  - --batch=100 flag (chunk size)
 *  - --tenant= flag (which tenant to migrate into)
 *  - Shared progress output helpers
 *  - MigrationService factory
 */
abstract class BaseMigrationCommand extends Command
{
    // ─── Options every command inherits ──────────────────────────────────────

    protected function getOptions(): array
    {
        return [
            ['dry-run', null, \Symfony\Component\Console\Input\InputOption::VALUE_NONE,    'Report what would be migrated without writing to DB'],
            ['batch',   null, \Symfony\Component\Console\Input\InputOption::VALUE_OPTIONAL, 'Chunk size for processing records', 100],
            ['tenant',  null, \Symfony\Component\Console\Input\InputOption::VALUE_OPTIONAL, 'Tenant ID to migrate into (defaults to first tenant)', null],
        ];
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────

    protected function isDryRun(): bool
    {
        return (bool) $this->option('dry-run');
    }

    protected function batchSize(): int
    {
        return (int) ($this->option('batch') ?? 100);
    }

    protected function resolveTenantId(): string
    {
        $tid = $this->option('tenant');

        if ($tid) {
            return $tid;
        }

        // Default: first tenant in the central tenants table
        $first = DB::table('tenants')->orderBy('id')->value('id');

        if (! $first) {
            $this->error('No tenants found. Create a tenant first or pass --tenant=<id>.');
            exit(1);
        }

        return (string) $first;
    }

    protected function makeService(): MigrationService
    {
        return new MigrationService($this->resolveTenantId());
    }

    // ─── Output helpers ───────────────────────────────────────────────────────

    protected function printResult(MigrationResult $result): void
    {
        $tag = $result->dryRun ? '[DRY-RUN]' : '';
        $ok  = $result->isSuccess() ? '<fg=green>OK</>' : '<fg=red>FAIL</>';

        $this->line(sprintf(
            '%s %s %s  migrated=%d  skipped=%d  failed=%d',
            $tag,
            $ok,
            strtoupper($result->step),
            $result->migrated,
            $result->skipped,
            $result->failed,
        ));

        foreach ($result->errors as $err) {
            $this->warn("  ! {$err}");
        }
    }

    /**
     * Safely cast a VOZARA user_type integer to a LENDR UserRole value.
     * VOZARA convention: 1=admin, 2=manager, 3=officer, 4=cashier, 5=accountant, 6=auditor
     */
    protected function mapUserRole(int $userType): string
    {
        return match($userType) {
            1       => 'super_admin',
            2       => 'branch_manager',
            3       => 'loan_officer',
            4       => 'cashier',
            5       => 'accountant',
            6       => 'auditor',
            default => 'loan_officer',
        };
    }

    /**
     * Safely map a VOZARA loan status integer to a LENDR LoanStatus enum value.
     * VOZARA convention: 0=pending, 1=active, 2=completed, 3=defaulted, 4=written_off, 5=denied
     */
    protected function mapLoanStatus(int $status): string
    {
        return match($status) {
            0       => 'submitted',
            1       => 'active',
            2       => 'completed',
            3       => 'defaulted',
            4       => 'written_off',
            5       => 'denied',
            default => 'active',
        };
    }

    /**
     * Map a VOZARA payment_method varchar to a LENDR PaymentMethod enum value.
     */
    protected function mapPaymentMethod(string $method): string
    {
        $method = strtolower(trim($method));

        return match(true) {
            str_contains($method, 'airtel')              => 'airtel_money',
            str_contains($method, 'mtn')                 => 'mtn_momo',
            str_contains($method, 'zamtel')              => 'zamtel_kwacha',
            str_contains($method, 'bank')                => 'bank_transfer',
            str_contains($method, 'cheque')
                || str_contains($method, 'check')        => 'cheque',
            str_contains($method, 'flutter')             => 'flutterwave',
            str_contains($method, 'pawa')                => 'pawapay',
            default                                       => 'cash',
        };
    }
}
