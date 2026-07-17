import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import axios from 'axios'

export const useLandlordAuth = defineStore('landlord-auth', () => {
  const token = ref(localStorage.getItem('landlord_token') ?? '')
  const email = ref(localStorage.getItem('landlord_email') ?? '')

  const isAuthenticated = computed(() => !!token.value)

  function setToken(t, e) {
    token.value = t
    email.value = e
    localStorage.setItem('landlord_token', t)
    localStorage.setItem('landlord_email', e)
    axios.defaults.headers.common['Authorization'] = `Bearer ${t}`
  }

  function clearAuth() {
    token.value = ''
    email.value = ''
    localStorage.removeItem('landlord_token')
    localStorage.removeItem('landlord_email')
    delete axios.defaults.headers.common['Authorization']
  }

  async function logout() {
    try {
      await axios.post('/api/v1/landlord/auth/logout')
    } catch { /* ignore */ }
    clearAuth()
  }

  // Restore auth header on page load
  if (token.value) {
    axios.defaults.headers.common['Authorization'] = `Bearer ${token.value}`
  }

  return { token, email, isAuthenticated, setToken, clearAuth, logout }
})
