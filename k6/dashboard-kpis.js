/**
 * k6 load test — GET /api/v1/dashboard/kpis
 *
 * Target SLA: p95 response time < 500 ms under 100 concurrent users.
 *
 * Run:
 *   k6 run --env APP_URL=https://app.lendr.app \
 *           --env K6_STAFF_EMAIL=admin@lendr.app \
 *           --env K6_STAFF_PASSWORD=secret \
 *           k6/dashboard-kpis.js
 */

import http        from 'k6/http'
import { check, sleep } from 'k6'
import { Trend, Rate } from 'k6/metrics'
import { loginAsStaff, authHeaders, BASE_URL } from './helpers.js'

export const options = {
  stages: [
    { duration: '10s', target: 20  },   // ramp up
    { duration: '60s', target: 100 },   // sustained load
    { duration: '10s', target: 0   },   // ramp down
  ],
  thresholds: {
    'http_req_duration{endpoint:kpis}': ['p(95)<500'],
    'http_req_failed{endpoint:kpis}':   ['rate<0.01'],
  },
}

const kpiDuration = new Trend('kpi_response_ms', true)
const errorRate   = new Rate('kpi_error_rate')

let token = null

export function setup() {
  token = loginAsStaff()
  if (!token) throw new Error('Could not authenticate — aborting load test')
  return { token }
}

export default function (data) {
  const res = http.get(
    `${BASE_URL}/api/v1/dashboard/kpis`,
    {
      headers: authHeaders(data.token),
      tags:    { endpoint: 'kpis' },
    }
  )

  kpiDuration.add(res.timings.duration)

  const ok = check(res, {
    'status 200':         (r) => r.status === 200,
    'has active_loans':   (r) => r.json('data.active_loans') !== undefined,
    'has fund.available': (r) => r.json('data.fund.available_balance') !== undefined,
  })

  errorRate.add(!ok)

  sleep(Math.random() * 1 + 0.5) // 0.5–1.5s think time
}
