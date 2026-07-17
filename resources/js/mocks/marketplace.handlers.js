/**
 * MSW (Mock Service Worker) handlers for the Marketplace feature.
 *
 * These intercept API calls when `VITE_MOCK_MARKETPLACE=true` is set
 * in .env.local — allowing the full Vue UI to work without a running
 * backend. All responses mirror the production API envelope:
 *   { success: true, data: ..., message: '' }
 */

import { http, HttpResponse } from 'msw'

// ─── Mock data ──────────────────────────────────────────────────────────────

const MOCK_LISTINGS = [
  {
    id: 1,
    title: 'Business expansion — buy 2 more taxis',
    description: 'I run a small taxi business with 3 vehicles. Looking to purchase 2 more to expand operations and employ 2 more drivers.',
    purpose: 'business',
    amount_requested: 85000,
    interest_rate_offered: 8.5,
    tenure_months: 18,
    status: 'active',
    views_count: 34,
    interests_count: 3,
    published_at: '2026-03-15T09:00:00Z',
    expires_at: '2026-06-15T09:00:00Z',
    borrower: {
      id: 101,
      name: 'Moses Banda',
      credit_score: 712,
      score_band: 'good',
    },
    interests: [
      { id: 1, user: 'First Finance Ltd', amount_offered: 85000, interest_rate: 9, message: 'We can fund the full amount.', status: 'pending' },
      { id: 2, user: 'Savannah Capital', amount_offered: 70000, interest_rate: 8, message: 'Partial funding available immediately.', status: 'pending' },
    ],
  },
  {
    id: 2,
    title: 'School fees for children — Form 5 & Grade 9',
    description: 'Need funds to cover school fees, uniforms and textbooks for both children this academic year.',
    purpose: 'education',
    amount_requested: 12000,
    interest_rate_offered: 6,
    tenure_months: 6,
    status: 'active',
    views_count: 18,
    interests_count: 1,
    published_at: '2026-03-20T12:00:00Z',
    expires_at: '2026-05-20T12:00:00Z',
    borrower: {
      id: 102,
      name: 'Grace Mwale',
      credit_score: 634,
      score_band: 'good',
    },
    interests: [
      { id: 3, user: 'Community Micro Finance', amount_offered: 12000, interest_rate: 6.5, message: '', status: 'pending' },
    ],
  },
  {
    id: 3,
    title: 'Medical bills — hospital stay and surgery',
    description: 'Unexpected medical emergency. Need to cover hospital bills and post-surgery care costs.',
    purpose: 'medical',
    amount_requested: 22000,
    interest_rate_offered: null,
    tenure_months: 12,
    status: 'active',
    views_count: 9,
    interests_count: 0,
    published_at: '2026-03-28T08:00:00Z',
    expires_at: '2026-06-28T08:00:00Z',
    borrower: {
      id: 103,
      name: 'John Phiri',
      credit_score: 578,
      score_band: 'fair',
    },
    interests: [],
  },
]

const MOCK_MY_INTERESTS = [
  {
    id: 1,
    listing: { id: 1, title: 'Business expansion — buy 2 more taxis', amount_requested: 85000, status: 'active' },
    amount_offered: 85000,
    interest_rate: 9,
    message: 'We can fund the full amount.',
    status: 'pending',
  },
]

const MOCK_CREDIT_SCORE = {
  score: 712,
  score_band: 'good',
  repayment_history_score: 88,
  debt_load_score: 72,
  history_length_score: 55,
  account_mix_score: 50,
  new_credit_score: 90,
  total_loans: 4,
  total_completed: 3,
  total_defaulted: 0,
  last_updated: '2026-03-30T10:00:00Z',
}

const MOCK_BORROWER_LISTINGS = [
  {
    id: 1,
    title: 'Business expansion — buy 2 more taxis',
    purpose: 'business',
    amount_requested: 85000,
    tenure_months: 18,
    status: 'active',
    interests_count: 3,
    published_at: '2026-03-15T09:00:00Z',
    expires_at: '2026-06-15T09:00:00Z',
  },
]

const MOCK_REVIEWS = {
  listing_id: 1,
  average_rating: 4.5,
  reviews: [
    { id: 1, rating: 5, comment: 'Excellent repayment record, highly recommend.', reviewer: 'First Finance Ltd', created_at: '2026-02-10T10:00:00Z' },
    { id: 2, rating: 4, comment: 'Prompt communication and reliable borrower.', reviewer: 'Savannah Capital', created_at: '2026-01-22T14:30:00Z' },
  ],
}

// ─── API envelope helper ────────────────────────────────────────────────────

const ok = (data, message = '') => HttpResponse.json({ success: true, data, message })

// ─── Handlers ───────────────────────────────────────────────────────────────

export const marketplaceHandlers = [

  // GET /api/v1/marketplace/listings
  http.get('/api/v1/marketplace/listings', ({ request }) => {
    const url      = new URL(request.url)
    const purpose  = url.searchParams.get('purpose')
    const minAmt   = parseFloat(url.searchParams.get('min_amount') ?? '0')
    const maxAmt   = parseFloat(url.searchParams.get('max_amount') ?? '999999999')

    let data = MOCK_LISTINGS
    if (purpose) data = data.filter(l => l.purpose === purpose)
    if (minAmt)  data = data.filter(l => l.amount_requested >= minAmt)
    if (maxAmt < 999999999) data = data.filter(l => l.amount_requested <= maxAmt)

    return ok({
      data: data,
      pagination: { total: data.length, per_page: 20, current_page: 1, last_page: 1 },
    })
  }),

  // GET /api/v1/marketplace/listings/:id
  http.get('/api/v1/marketplace/listings/:id', ({ params }) => {
    const listing = MOCK_LISTINGS.find(l => l.id === parseInt(params.id))
    if (!listing) return HttpResponse.json({ success: false, message: 'Not found' }, { status: 404 })
    return ok(listing)
  }),

  // POST /api/v1/marketplace/listings/:id/express-interest
  http.post('/api/v1/marketplace/listings/:id/express-interest', async ({ request, params }) => {
    const body = await request.json()
    return ok({
      id: Date.now(),
      listing_id: parseInt(params.id),
      status: 'pending',
      ...body,
    }, 'Interest expressed.')
  }),

  // GET /api/v1/marketplace/my-interests
  http.get('/api/v1/marketplace/my-interests', () => {
    return ok({
      data: MOCK_MY_INTERESTS,
      pagination: { total: 1, per_page: 20, current_page: 1, last_page: 1 },
    })
  }),

  // GET /api/v1/marketplace/reviews/:id
  http.get('/api/v1/marketplace/reviews/:id', () => ok(MOCK_REVIEWS)),

  // POST /api/v1/marketplace/reviews
  http.post('/api/v1/marketplace/reviews', async ({ request }) => {
    const body = await request.json()
    return ok({ id: Date.now(), ...body }, 'Review posted.')
  }),

  // GET /api/v1/borrower/marketplace/listings
  http.get('/api/v1/borrower/marketplace/listings', () => ok(MOCK_BORROWER_LISTINGS)),

  // POST /api/v1/borrower/marketplace/listings  (create)
  http.post('/api/v1/borrower/marketplace/listings', async ({ request }) => {
    const body = await request.json()
    return ok({
      id: Date.now(),
      status: 'draft',
      ...body,
    }, 'Listing created.', { status: 201 })
  }),

  // PUT /api/v1/borrower/marketplace/listings/:id/withdraw
  http.put('/api/v1/borrower/marketplace/listings/:id/withdraw', ({ params }) => {
    return ok({ id: parseInt(params.id), status: 'withdrawn' }, 'Listing withdrawn.')
  }),

  // POST /api/v1/borrower/marketplace/interests/:id/accept
  http.post('/api/v1/borrower/marketplace/interests/:id/accept', ({ params }) => {
    return ok({ interest_id: parseInt(params.id), status: 'accepted' }, 'Offer accepted. A loan will be created in the lender\'s system.')
  }),

  // GET /api/v1/borrower/credit-score
  http.get('/api/v1/borrower/credit-score', () => ok(MOCK_CREDIT_SCORE)),

  // PUT /api/v1/marketplace/listings/:id  (withdraw by admin)
  http.put('/api/v1/marketplace/listings/:id/withdraw', ({ params }) => {
    return ok({ id: parseInt(params.id), status: 'withdrawn' }, 'Listing withdrawn.')
  }),
]
