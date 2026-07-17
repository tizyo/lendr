/**
 * k6 shared helpers — authentication and common utilities.
 *
 * Usage:
 *   import { loginAsStaff, authHeaders, BASE_URL } from './helpers.js'
 */

export const BASE_URL = __ENV.APP_URL || 'http://localhost'

/**
 * Log in as a staff user and return the Bearer token.
 * Requires env vars: K6_STAFF_EMAIL, K6_STAFF_PASSWORD
 */
export function loginAsStaff() {
  const res = http.post(
    `${BASE_URL}/api/v1/auth/login`,
    JSON.stringify({
      email:    __ENV.K6_STAFF_EMAIL    || 'admin@lendr.test',
      password: __ENV.K6_STAFF_PASSWORD || 'password',
    }),
    { headers: { 'Content-Type': 'application/json' } }
  )

  if (res.status !== 200) {
    console.error(`Login failed: ${res.status} ${res.body}`)
    return null
  }

  return res.json('data.token')
}

/**
 * Return common JSON + auth headers.
 */
export function authHeaders(token) {
  return {
    'Content-Type': 'application/json',
    'Accept':       'application/json',
    'Authorization': `Bearer ${token}`,
  }
}
