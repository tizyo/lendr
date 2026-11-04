<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The Approvals screen saved workflows as "loan_disburse" / "loan_approve" while
 * the code looked for "loan_disbursement", so no workflow ever applied. Rename
 * existing rows to the names the code enforces (see ApprovalWorkflow::TYPES).
 */
return new class extends Migration
{
    private const RENAMES = [
        'loan_disburse' => 'loan_disbursement',
        'loan_approve' => 'loan_approval',
        'writeoff' => 'write_off',
    ];

    public function up(): void
    {
        foreach (['approval_workflows', 'approval_requests'] as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }
            foreach (self::RENAMES as $from => $to) {
                DB::table($table)->where('entity_type', $from)->update(['entity_type' => $to]);
            }
        }
    }

    public function down(): void
    {
        // Renamed values are the correct ones; nothing to undo.
    }
};
