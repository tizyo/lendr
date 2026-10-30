<?php

use Illuminate\Support\Facades\Schema;

/*
 * MySQL DDL is not transactional, so a tenant can be left with part of a migration
 * applied and no record of it. Re-running must apply what's missing, not fail.
 */

dataset('retryable tenant migrations', [
    '2026_03_26_001000_add_fx_rate_to_loans',
    '2026_03_26_002000_create_auto_decision_tables',
    '2026_03_26_003000_create_investor_dividends_table',
    '2026_03_26_004000_create_compliance_events_table',
    '2026_04_01_000001_add_production_indexes',
    '2026_07_18_070005_add_unique_reference_index_to_disbursement_logs_table',
    '2026_10_27_120000_widen_disbursement_logs_status',
    '2026_10_28_100000_add_web_push_to_device_tokens',
    '2026_10_28_110000_encrypt_borrower_national_ids',
    '2026_10_28_130000_encrypt_guarantor_agent_investor_national_ids',
    '2026_10_28_140000_create_payment_proofs_table',
    '2026_10_30_100000_create_personal_access_tokens_table',
    '2026_10_30_100100_make_staff_password_nullable',
]);

it('can re-run a tenant migration that has already been applied', function (string $migration) {
    (require database_path("migrations/tenant/{$migration}.php"))->up();

    expect(true)->toBeTrue();
})->with('retryable tenant migrations');

it('adds only the missing column when the fx migration was cut short', function () {
    Schema::table('loans', fn ($table) => $table->dropColumn('base_currency'));

    (require database_path('migrations/tenant/2026_03_26_001000_add_fx_rate_to_loans.php'))->up();

    expect(Schema::hasColumns('loans', ['fx_rate', 'base_currency']))->toBeTrue();
});
