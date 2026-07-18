# Pre-Launch Verification (Phase 5.2 / 5.3)

Both items below require a live staging environment — a real deployed app, a real database, real mobile-money sandbox credentials — none of which exist in this dev/CI environment. This documents exactly what to run and how to judge the result, so it's a checklist rather than a research task when staging is ready.

---

## 5.2 — Load test

Three k6 scenarios already exist in `k6/` and are launch-ready as written — nothing needed fixing, they just need infra to run against:

| Script | Target endpoint | SLA |
|---|---|---|
| `k6/dashboard-kpis.js` | `GET /api/v1/dashboard/kpis` | p95 < 500ms, error rate < 1% |
| `k6/loan-list.js` | `GET /api/v1/loans` | p95 < 300ms, error rate < 1% |
| `k6/record-payment.js` | `POST /api/v1/payments` | p95 < 800ms, error rate < 2% |

All three ramp 0→100 concurrent users over ~80s (10s ramp-up, 60s sustained, 10s ramp-down).

### Run

```bash
k6 run --env APP_URL=https://staging.lendr.app \
       --env K6_STAFF_EMAIL=<staging admin email> \
       --env K6_STAFF_PASSWORD=<staging admin password> \
       k6/dashboard-kpis.js

# repeat for loan-list.js and record-payment.js
```

`record-payment.js` requires at least one active loan seeded in the staging tenant (`setup()` fetches up to 50 via `GET /api/v1/loans?status=active`) — seed staging with representative loan volume before running, not just one throwaway loan, or the test won't reflect real query-plan behavior under load.

### What to watch specifically

- **`record-payment.js` is the one that changed in Phase 2** — `PaymentService::record()` now takes `Loan::lockForUpdate()` before touching the balance (`app/Services/Payment/PaymentService.php`). Row locking serializes concurrent writes to the *same* loan; it does not serialize writes to *different* loans. Since the script picks a random loan ID per iteration from up to 50, contention should stay low — but if p95 spikes past the 800ms threshold specifically under this script and not the read-only ones, that's the signal the lock is queuing more than expected (check for a hot loan ID being hit disproportionately, or too few loans seeded relative to concurrency).
- Compare all three scenarios' error rates against `webhook_events`/`disbursement_logs` in the staging DB after each run — a k6 HTTP-level pass doesn't confirm the GL postings (`GlLedgerService::postPayment()`, wired in Phase 2.4) kept up; spot-check `GlLedgerService::trialBalance()` still nets to zero after a load-test run that recorded real payments.
- Record the actual numbers (p50/p95/p99, error rate, throughput) from each run's summary output — there's no prior baseline to compare against, so the first real run *is* the baseline. Save the raw k6 JSON output (`--out json=results.json`) alongside whatever number gets reported, so a future run has something to regress against.

---

## 5.3 — Staging soak test + secret rotation

### Soak test

Run staging continuously for several days with realistic traffic (either organic pilot users or a scripted low-volume loop) while exercising the full webhook → payment → GL path end-to-end against each mobile-money provider's **sandbox**, not production credentials:

- Airtel Money
- MTN MoMo
- Zamtel Kwacha
- PawaPay
- Flutterwave

For each provider: initiate a payment collection via the borrower-facing flow, let the provider's sandbox send the real webhook callback (don't synthesize the payload — the point is to catch real-world payload variations), and confirm it lands as a `Payment` row with a correct GL posting. Deliberately also test the failure path (a sandbox-declined payment) and confirm `MobileMoneyTransaction.status` and `intent.status` end up `failed`, not silently stuck.

Watch for, over the multi-day window:
- Any `WebhookEvent` rows stuck in `status = 'received'` (never reached `processed`/`failed`) — see `docs/incident-runbook.md` §1 for triage.
- Any `DisbursementLog` rows stuck in `initiated`/`processing` — see `docs/incident-runbook.md` §2. This is the scenario the runbook explicitly flags as having **no automated alert** yet, so the soak test is the only thing that will catch it before launch.
- Queue worker memory growth / restarts (`php artisan queue:work` process, if running under Supervisor — check its restart count over the soak window).
- Sentry error volume trending up rather than flat — a soak test should converge to zero new error types after the first day, not keep surfacing novel ones.

### Secret rotation

`.env.example` ships literal shared defaults for three values — safe as local-dev fallbacks (docker-compose interpolates them via `${VAR:-default}`, and CI uses its own ephemeral instance), but **must not** reach the real production `.env` unchanged:

| Variable | Current default (dev/CI only) | Action before launch |
|---|---|---|
| `DB_PASSWORD` | `lendr_secret` | Generate a real random password for the production MySQL user |
| `DB_ROOT_PASSWORD` | `root_secret` | Generate a real random password for the production MySQL root user |
| `REDIS_PASSWORD` | `redis_secret` | Generate a real random password for production Redis |

Confirm at the same time that every provider webhook secret is actually populated in production (`FLUTTERWAVE_WEBHOOK_SECRET`, `PAWAPAY_WEBHOOK_SECRET`, `LIPILA_WEBHOOK_SECRET`, and the Airtel/MTN/Zamtel equivalents read per-tenant from `TenantWallet`/`settings`) — since Phase 1.4 made signature verification fail **closed** when a secret is missing, an unset production secret doesn't leak, it just silently rejects every webhook from that provider. Verify each one is set, not just non-default.

Also rotate/verify: `APP_KEY` (must be a real `php artisan key:generate` output, not blank or a value that ever touched a lower environment), `HORIZON_BASIC_AUTH_PASSWORD`, `BACKUP_ARCHIVE_PASSWORD`, and any AWS/Cloudflare R2 access keys — none of these have insecure defaults in `.env.example` (they ship blank), but blank-in-example doesn't guarantee someone didn't copy a staging value into production at some point; confirm production's actual values were generated for production specifically.

### Sign-off

This phase's output is an operational log, not code — record: soak test start/end timestamps, any incidents hit and how they were resolved (cross-reference the runbook), confirmation each of the 5 provider sandboxes was exercised successfully, and confirmation each secret in the table above was rotated with the old value invalidated (not just the new value added alongside it).
