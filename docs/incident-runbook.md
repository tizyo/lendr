# LENDR Incident Runbook

Operational steps for the two payment-path failure modes most likely to page someone: a webhook signature/idempotency incident, and a stuck or failed disbursement. Written for an on-call engineer with `php artisan tinker` access to the affected tenant and read access to Sentry/logs — no prior context assumed.

---

## 1. Webhook signature / idempotency incident

### What it looks like

- Sentry: a `warning`-level log from `[Webhook:{provider}]` — either `Source IP not in configured allowlist`, `Invalid signature`, or a `Processing error` on `BaseWebhookController::handle()` (`app/Http/Controllers/Webhook/BaseWebhookController.php:49-83`).
- Support/borrower report: "I paid but my loan still shows the old balance" (signature rejected, payment never landed) or "I was charged twice" (duplicate processed as two payments).
- A spike in `webhook_events.status = 'failed'` for one provider.

### First 5 minutes — triage

1. Identify the provider and window from Sentry, then pull recent events for that tenant:
   ```php
   \App\Models\Tenant\WebhookEvent::where('provider', '{provider}')
       ->where('created_at', '>=', now()->subHours(2))
       ->orderByDesc('created_at')->get(['id','event_id','event_type','status','created_at']);
   ```
2. `status = 'received'` stuck (never reached `processed`/`failed`) means the request never got a chance to finish — check for an app-level 500 around that timestamp, not a webhook-specific bug.
3. `status = 'failed'` — read `payload` and cross-reference the error in Sentry's `Processing error` log line (it includes `ref` = the LENDR internal reference).

### Signature / IP rejections (401 / 403, no `WebhookEvent` row at all)

These never reach `logEvent()`, so there's nothing to query — the request is rejected before any row is written. Confirm from the web server / Sentry log alone:
- **401 Invalid signature** — check whether the provider rotated their signing secret, or whether the payload arrived with a body-parsing difference (some providers re-sign on retry with a different content-encoding). Compare against the tenant's configured secret in `TenantWallet`/`settings` before assuming an attack.
- **403 IP not allowlisted** — check `settings` for `{provider}_webhook_ip_allowlist` on the tenant; the provider may have added a new source IP/CIDR. Cross-reference against the provider's published IP list before widening the allowlist.

If the rejection is legitimate (real payment, wrongly rejected), the provider's own retry mechanism will resend — most mobile money providers retry failed webhooks for 24–72h. Fix the secret/allowlist and let the retry land naturally rather than manually replaying the original request unless the provider confirms it won't retry.

### Duplicate processing (`WebhookEvent` idempotency didn't catch it)

`logEvent()` (`BaseWebhookController.php:256-285`) keys on `event_id` from the provider payload. A true duplicate `Payment` can only happen if two *different* `event_id`s carried the same underlying transaction (provider bug) — the idempotency check itself can't be bypassed by a simple retry.

1. Find both payments:
   ```php
   \App\Models\Tenant\Payment::where('loan_id', $loanId)
       ->where('reference', $internalRef)->get();
   ```
   If genuinely two rows exist for one real-world payment, `momo_transaction_id` will differ (two provider transaction IDs referencing the same money movement) — confirm with the provider's own dashboard/API before reversing anything.
2. **Reversing a duplicate payment**: `PaymentService` and `GlLedgerService` have no built-in reversal method (`app/Services/GlLedgerService.php`) — reversal is manual, equal-and-opposite:
   - Do **not** delete the duplicate `Payment` row (breaks the audit trail and the loan's paid-installments history).
   - Post an offsetting GL entry: `app(\App\Services\GlLedgerService::class)->post('Reversal of duplicate payment #{id}', [reversed lines with debit/credit swapped from the original entry], $payment)`. Pull the original entry's lines from `GlJournalEntry::where('source_type', Payment::class)->where('source_id', $payment->id)->first()->lines` to mirror amounts exactly.
   - Adjust `loans.outstanding_balance` back up by the duplicated amount, and mark the duplicate `Payment` with a `notes` entry documenting the reversal (add a `voided_at`/status field to `payments` if this becomes a recurring need — today it's a manual note).
   - Re-run the tenant's `GlLedgerService::trialBalance()` afterward and confirm it still nets to zero.

### Prevention check

Confirm `verifySignature()` fails **closed** for the affected provider (`app/Http/Controllers/Webhook/{Provider}WebhookController.php`) — every provider controller was hardened in Phase 1.4 to reject when no secret is configured, rather than silently accepting. If a new provider integration was added since, verify it follows the same pattern before enabling it in production.

---

## 2. Stuck or failed disbursement

### What it looks like

- Sentry: `[AutoDisburse] Failed` error log with `loan_id`, `gateway`, and the provider's error message (`app/Services/Payment/AutoDisbursementService.php:122-126`).
- A loan sits in `disbursement_pending`/similar status with no money having moved, past the point the borrower expected funds.
- Support report: "approved loan, no money received."

### First 5 minutes — triage

1. Pull the disbursement log for the loan:
   ```php
   \App\Models\Tenant\DisbursementLog::where('loan_id', $loanId)->latest()->first();
   ```
2. Read `status`:
   - **`initiated`** and stuck (created more than a few minutes ago, never progressed) — the gateway call itself likely never completed (crashed mid-request, timeout not caught). Check Sentry for an unhandled exception around that timestamp; the `try/catch` in `disburse()` should have caught anything short of a fatal PHP error or an infra-level timeout that killed the request before the catch ran.
   - **`processing`** — the gateway accepted the request (`provider_reference` is set) but LENDR hasn't seen a completion webhook yet. This is normal for the first few minutes; treat as stuck only past the provider's typical settlement window (check the specific gateway's docs — mobile money payouts are usually seconds to a few minutes, bank rails can be hours).
   - **`failed`** — read `failure_reason`. Common causes: bad credentials (`TenantWallet.api_key`/`api_secret` wrong or expired), insufficient float balance at the provider, invalid/unregistered recipient MSISDN.
3. Check whether `RunReconciliationCommand` (`lendr:run-reconciliation`, scheduled daily at 04:00 — `routes/console.php`) should have already caught this. It only reconciles `BankStatement` rows, **not** `DisbursementLog` — it will not surface a stuck disbursement on its own. Treat any `DisbursementLog` older than ~30 minutes in `initiated`/`processing` as needing manual attention until a dedicated disbursement-reconciler exists (see Follow-up below).

### Recovery

**If `failed` and the underlying cause is fixed (e.g. credentials rotated, float topped up):**
Re-run through the same service — it reuses the existing log row via the reference-reuse path, it will **not** create a second payout record or double-disburse:
```php
$loan = \App\Models\Tenant\Loan::find($loanId);
$wallet = \App\Models\Landlord\TenantWallet::where('tenant_id', tenant('id'))->first();
app(\App\Services\Payment\AutoDisbursementService::class)->disburse($loan, $wallet);
```
This is safe to run multiple times — `disburse()` keys off the deterministic reference `LENDR-DISB-{loan_id}` plus a unique DB index on `disbursement_logs.reference`, so a second call either reuses the failed row for retry or, if it succeeded in the meantime, returns the existing non-failed log without calling the gateway again.

**If `processing` past the expected settlement window with no webhook received:**
Check the provider's own transaction status API/dashboard directly using `provider_reference` before assuming it's lost — the webhook may simply not have arrived (network issue on the provider's side) while the payout itself succeeded. If the provider confirms success, manually mark the log:
```php
\App\Models\Tenant\DisbursementLog::find($logId)->update(['status' => 'completed']);
```
If the provider confirms failure or the transaction is unknown to them, mark `failed` with a `failure_reason` noting the manual investigation, then retry per the step above.

**If `initiated` and truly stuck (no provider record at all — the request never left LENDR):**
Safe to retry directly; nothing was sent to the gateway yet, so there's no double-payout risk. Same retry call as above.

### Prevention check

`AutoDisbursementService::disburse()` was hardened (Phase 2.3) specifically against duplicate payouts from a re-dispatched job or concurrent request — verify `disbursement_logs.reference` still has its unique index (`database/migrations/tenant/2026_07_18_070005_add_unique_reference_index_to_disbursement_logs_table.php`) before assuming a "duplicate payout" report is even possible from normal retry paths; if it happened anyway, that index is the first thing to check for regression.

### Follow-up (not yet built)

There is currently no scheduled job that alerts on `DisbursementLog` rows stuck in `initiated`/`processing` past a threshold — `RunReconciliationCommand` only covers bank statements. Until one exists, this class of incident depends on Sentry's `[AutoDisburse] Failed` log or a support report to surface it; a stuck-but-not-yet-`failed` disbursement (still `processing`, gateway never called back) has no automated alert path at all.
