import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import axios from 'axios'

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
  }

  // Restore axios header on page load
  if (token.value) {
    axios.defaults.headers.common['Authorization'] = `Bearer ${token.value}`
  }

  return { token, borrower, isAuthenticated, setAuth, clearAuth }
})
