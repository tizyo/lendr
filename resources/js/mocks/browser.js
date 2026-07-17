/**
 * MSW browser setup.
 *
 * Activated when VITE_MOCK_MARKETPLACE=true in your .env.local.
 * Import and call `startMockServiceWorker()` in bootstrap.js.
 */

import { setupWorker } from 'msw/browser'
import { marketplaceHandlers } from './marketplace.handlers.js'

export const worker = setupWorker(...marketplaceHandlers)

export async function startMockServiceWorker() {
  if (import.meta.env.VITE_MOCK_MARKETPLACE !== 'true') return

  await worker.start({
    onUnhandledRequest: 'bypass', // let non-mocked requests pass through
    serviceWorker: {
      url: '/mockServiceWorker.js',
    },
  })

  console.info('[MSW] Mock Service Worker active — marketplace API calls are intercepted.')
}
