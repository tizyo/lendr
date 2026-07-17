/**
 * E2E: Staff Loan Flow
 *
 * Covers the full lending lifecycle from a staff perspective:
 *   1. Log in as loan officer
 *   2. Create a borrower
 *   3. Create a loan (select type, plan, amounts)
 *   4. Approve the loan
 *   5. Disburse the loan
 *   6. Record a payment
 *   7. Verify the receipt is downloadable
 */

import { test, expect, Page } from '@playwright/test'

const BASE_URL    = process.env.APP_URL ?? 'http://localhost'
const STAFF_EMAIL = process.env.E2E_STAFF_EMAIL    ?? 'officer@lendr.test'
const STAFF_PASS  = process.env.E2E_STAFF_PASSWORD ?? 'password'

async function staffLogin(page: Page) {
  await page.goto(`${BASE_URL}/login`)
  await page.fill('input[name="email"]',    STAFF_EMAIL)
  await page.fill('input[name="password"]', STAFF_PASS)
  await page.click('button[type="submit"]')
  await page.waitForURL(`${BASE_URL}/dashboard`)
}

test.describe('Staff Loan Lifecycle', () => {

  test.beforeEach(async ({ page }) => {
    await staffLogin(page)
  })

  test('can create a new borrower', async ({ page }) => {
    await page.goto(`${BASE_URL}/borrowers`)
    await page.click('text=Add Borrower')

    await page.fill('input[name="first_name"]', 'E2E')
    await page.fill('input[name="last_name"]',  'TestBorrower')
    await page.fill('input[name="phone"]',       '0971000099')
    await page.fill('input[name="national_id"]', 'NRC-E2E-001')

    await page.click('button[type="submit"]')

    await expect(page.locator('text=Borrower created')).toBeVisible({ timeout: 8000 })
  })

  test('can create and approve a loan', async ({ page }) => {
    await page.goto(`${BASE_URL}/loans/create`)

    // Select borrower
    await page.getByLabel('Borrower').fill('E2E')
    await page.click('text=E2E TestBorrower')

    // Fill loan details
    await page.selectOption('select[name="loan_type_id"]', { index: 1 })
    await page.selectOption('select[name="loan_plan_id"]', { index: 1 })
    await page.fill('input[name="principal_amount"]', '10000')
    await page.fill('input[name="duration_months"]',  '12')

    await page.click('button[type="submit"]')
    await expect(page.locator('text=Loan created')).toBeVisible({ timeout: 8000 })

    // Approve
    await page.click('text=Approve Loan')
    await expect(page.locator('text=approved')).toBeVisible({ timeout: 8000 })
  })

  test('can disburse an approved loan', async ({ page }) => {
    await page.goto(`${BASE_URL}/loans?status=approved`)
    await page.click('table tbody tr:first-child td:last-child a')

    await page.click('text=Disburse')
    await page.click('button:has-text("Confirm Disbursement")')

    await expect(page.locator('text=active')).toBeVisible({ timeout: 8000 })
  })

  test('can record a cash payment on an active loan', async ({ page }) => {
    await page.goto(`${BASE_URL}/loans?status=active`)
    await page.click('table tbody tr:first-child td:last-child a')

    await page.click('text=Record Payment')
    await page.fill('input[name="amount"]', '2000')
    await page.selectOption('select[name="payment_method"]', 'cash')

    await page.click('button:has-text("Save Payment")')
    await expect(page.locator('text=Payment recorded')).toBeVisible({ timeout: 8000 })
  })

  test('can download a payment receipt', async ({ page }) => {
    await page.goto(`${BASE_URL}/loans?status=active`)
    await page.click('table tbody tr:first-child td:last-child a')

    // Click the most recent payment receipt link
    const [ download ] = await Promise.all([
      page.waitForEvent('download'),
      page.click('text=Download Receipt'),
    ])

    expect(download.suggestedFilename()).toContain('receipt')
  })
})
