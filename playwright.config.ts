import { defineConfig, devices } from '@playwright/test'

export default defineConfig({
  testDir:   './e2e',
  timeout:   30_000,
  retries:   process.env.CI ? 2 : 0,
  workers:   process.env.CI ? 1 : 2,
  reporter:  [['html', { open: 'never' }], ['list']],

  use: {
    baseURL:        process.env.APP_URL ?? 'http://localhost',
    trace:          'on-first-retry',
    screenshot:     'only-on-failure',
    video:          'retain-on-failure',
    actionTimeout:  10_000,
  },

  projects: [
    { name: 'chromium', use: { ...devices['Desktop Chrome'] } },
    { name: 'mobile-chrome', use: { ...devices['Pixel 7'] } },
  ],
})
