/**
 * k6 load test — POST /api/v1/payments
 *
 * Target SLA: p95 response time < 800 ms under 100 concurrent users.
 *
 * NOTE: This test requires at least one disbursed loan in the database.
 *       The test fetches active loan IDs in setup() and rotates through them.
 *
 * Run:
 *   k6 run --env APP_URL=https://app.lendr.app \
 *           --env K6_STAFF_EMAIL=admin@lendr.app \
 *           --env K6_STAFF_PASSWORD=secret \
 *           k6/record-payment.js
 */

import http        from 'k6/http'
import { check, sleep } from 'k6'
import { Trend, Rate, Counter } from 'k6/metrics'
import { loginAsStaff, authHeaders, BASE_URL } from './helpers.js'

export const options = {
  stages: [
    { duration: '10s', target: 10  },
    { duration: '60s', target: 100 },
    { duration: '10s', target: 0   },
  ],
  thresholds: {
    'http_req_duration{endpoint:payments}': ['p(95)<800'],
    'http_req_failed{endpoint:payments}':   ['rate<0.02'],
  },
}

const paymentDuration = new Trend('payment_response_ms', true)
const errorRate       = new Rate('payment_error_rate')
const paymentCount    = new Counter('payments_recorded')

export function setup() {
  const token = loginAsStaff()
  if (!token) throw new Error('Could not authenticate — aborting load test')

  // Fetch up to 50 active loan IDs to use as payment targets
  const loansRes = http.get(
    `${BASE_URL}/api/v1/loans?status=active&per_page=50`,
    { headers: authHeaders(token) }
  )

  if (loansRes.status !== 200) {
    throw new Error(`Could not fetch loans: ${loansRes.status}`)
  }

  const body = loansRes.json()
  const loanIds = (body.data || []).map((l) => l.id)

  if (loanIds.length === 0) {
    throw new Error('No active loans found — seed the database before running this test')
  }

  return { token, loanIds }
}

export default function (data) {
  const loanId = data.loanIds[Math.floor(Math.random() * data.loanIds.length)]

  const payload = JSON.stringify({
    loan_id:        loanId,
    amount:         '500.00',
    payment_method: 'cash',
    payment_date:   new Date().toISOString().slice(0, 10),
    reference:      `k6-${Date.now()}-${Math.random().toString(36).slice(2, 8)}`,
    notes:          'k6 load test payment',
  })

  const res = http.post(
    `${BASE_URL}/api/v1/payments`,
    payload,
    {
      headers: authHeaders(data.token),
      tags:    { endpoint: 'payments' },
    }
  )

  paymentDuration.add(res.timings.duration)

  const ok = check(res, {
    'status 200 or 201': (r) => r.status === 200 || r.status === 201,
    'has payment id':    (r) => r.json('data.id') !== undefined,
  })

  if (ok) paymentCount.add(1)
  errorRate.add(!ok)

  sleep(Math.random() * 2 + 1) // 1–3s think time (payments are write ops)
}
