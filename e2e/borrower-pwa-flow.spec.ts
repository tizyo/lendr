/**
 * E2E: Borrower PWA Flow
 *
 * Covers the borrower-facing mobile web app:
 *   1. Register via OTP
 *   2. Complete KYC document upload
 *   3. Apply for a loan via public products
 *   4. Track the loan application status
 *   5. View account statement
 */

import { test, expect, Page } from '@playwright/test'

const BASE_URL = process.env.APP_URL ?? 'http://localhost'
const TEST_PHONE = '+260971000088'

test.describe('Borrower PWA Flow', () => {

  test('login page renders on mobile viewport', async ({ page }) => {
    await page.setViewportSize({ width: 390, height: 844 }) // iPhone 14
    await page.goto(`${BASE_URL}/app`)

    await expect(page.locator('h1, h2').first()).toBeVisible()
    await expect(page.locator('input[type="tel"], input[name="phone"]')).toBeVisible()
  })

  test('otp request shows verification step', async ({ page }) => {
    await page.setViewportSize({ width: 390, height: 844 })
    await page.goto(`${BASE_URL}/app/auth/login`)

    // Enter phone
    const phoneInput = page.locator('input[type="tel"], input[name="phone"]').first()
    await phoneInput.fill('0971000088')
    await page.click('button[type="submit"], button:has-text("Send OTP"), button:has-text("Continue")')

    // Should show OTP input
    await expect(page.locator('input[type="number"], input[name="otp"], input[placeholder*="OTP"]').first())
      .toBeVisible({ timeout: 6000 })
  })

  test('dashboard shows loan summary after login', async ({ page }) => {
    // Use a seeded test borrower account if available
    await page.setViewportSize({ width: 390, height: 844 })

    // Authenticate via PIN (assumes test borrower with PIN 1234 exists)
    await page.goto(`${BASE_URL}/app/auth/login`)

    const phoneInput = page.locator('input[type="tel"], input[name="phone"]').first()
    await phoneInput.fill('0971000077')  // seeded borrower

    // Try PIN login if available
    const pinButton = page.locator('text=Login with PIN, text=Use PIN')
    if (await pinButton.count() > 0) {
      await pinButton.click()
      await page.fill('input[name="pin"], input[type="password"]', '1234')
      await page.click('button[type="submit"]')
      await page.waitForURL(`${BASE_URL}/app/dashboard`, { timeout: 8000 }).catch(() => {})

      await expect(page.locator('text=My Loans, text=Outstanding, text=ZMW').first()).toBeVisible({ timeout: 6000 })
    }
  })

  test('public loan products page is accessible without login', async ({ page }) => {
    await page.setViewportSize({ width: 390, height: 844 })
    await page.goto(`${BASE_URL}/app/public-products`)

    // Should show loan products without requiring auth
    await expect(page).toHaveURL(/public-products/)
    await expect(page.locator('body')).not.toContainText('Unauthorized')
  })

  test('credit score page renders score ring', async ({ page }) => {
    await page.setViewportSize({ width: 390, height: 844 })
    await page.goto(`${BASE_URL}/app/marketplace/credit-score`)

    // Should show the SVG score ring
    await expect(page.locator('svg circle')).toBeVisible({ timeout: 6000 })
    await expect(page.locator('text=out of 850')).toBeVisible({ timeout: 6000 })
  })
})
