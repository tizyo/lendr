import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import axios from 'axios'
import { router } from '@inertiajs/vue3'

export const usePwaAuthStore = defineStore('pwa-auth', () => {
  const token   = ref(localStorage.getItem('pwa_token') || null)
  const borrower = ref(JSON.parse(localStorage.getItem('pwa_borrower') || 'null'))

  const isAuthenticated = computed(() => !!token.value)

  function setAuth(newToken, newBorrower) {
    token.value    = newToken
    borrower.value = newBorrower
    localStorage.setItem('pwa_token', newToken)
    localStorage.setItem('pwa_borrower', JSON.stringify(newBorrower))
    axios.defaults.headers.common['Authorization'] = `Bearer ${newToken}`
  }

  function clearAuth() {
    token.value    = null
    borrower.value = null
    localStorage.removeItem('pwa_token')
    localStorage.removeItem('pwa_borrower')
    delete axios.defaults.headers.common['Authorization']

    // Wipe the borrower's data cached for offline use (shared phones).
    navigator.serviceWorker?.controller?.postMessage('clear-cache')
  }

  /**
   * Sign out and go to login only when the session really expired (401).
   * Offline or server errors keep the borrower signed in, so cached data
   * stays viewable. Returns true when it redirected.
   */
  function handleAuthError(error) {
    if (error?.response?.status !== 401) return false

    clearAuth()
    router.visit(route('pwa.auth.login'))

    return true
  }

  // Restore axios header on page load
  if (token.value) {
    axios.defaults.headers.common['Authorization'] = `Bearer ${token.value}`
  }

  return { token, borrower, isAuthenticated, setAuth, clearAuth, handleAuthError }
})
