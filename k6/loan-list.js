/**
 * k6 load test — GET /api/v1/loans
 *
 * Target SLA: p95 response time < 300 ms under 100 concurrent users.
 *
 * Run:
 *   k6 run --env APP_URL=https://app.lendr.app \
 *           --env K6_STAFF_EMAIL=admin@lendr.app \
 *           --env K6_STAFF_PASSWORD=secret \
 *           k6/loan-list.js
 */

import http        from 'k6/http'
import { check, sleep } from 'k6'
import { Trend, Rate } from 'k6/metrics'
import { loginAsStaff, authHeaders, BASE_URL } from './helpers.js'

export const options = {
  stages: [
    { duration: '10s', target: 20  },
    { duration: '60s', target: 100 },
    { duration: '10s', target: 0   },
  ],
  thresholds: {
    'http_req_duration{endpoint:loans}': ['p(95)<300'],
    'http_req_failed{endpoint:loans}':   ['rate<0.01'],
  },
}

const loanDuration = new Trend('loan_list_response_ms', true)
const errorRate    = new Rate('loan_list_error_rate')

// Cycle through common filter combinations to exercise index coverage
const FILTERS = [
  '?status=active&per_page=20',
  '?status=overdue&per_page=20',
  '?status=completed&per_page=20',
  '?per_page=20',
  '?status=active&per_page=50',
]

export function setup() {
  const token = loginAsStaff()
  if (!token) throw new Error('Could not authenticate — aborting load test')
  return { token }
}

export default function (data) {
  const filter = FILTERS[Math.floor(Math.random() * FILTERS.length)]
  const res = http.get(
    `${BASE_URL}/api/v1/loans${filter}`,
    {
      headers: authHeaders(data.token),
      tags:    { endpoint: 'loans' },
    }
  )

  loanDuration.add(res.timings.duration)

  const ok = check(res, {
    'status 200':    (r) => r.status === 200,
    'has data key':  (r) => r.json('data') !== undefined,
    'paginated':     (r) => r.json('meta') !== undefined || r.json('data') !== undefined,
  })

  errorRate.add(!ok)

  sleep(Math.random() * 1 + 0.3)
}
