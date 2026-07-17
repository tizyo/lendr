import { defineStore } from 'pinia'
import { ref, watch } from 'vue'

export const useUiStore = defineStore('ui', () => {
  // ─── Dark mode ───────────────────────────────────────
  const darkMode = ref(localStorage.getItem('lendr-dark') === 'true')

  function toggleDarkMode() {
    darkMode.value = !darkMode.value
  }

  watch(darkMode, (val) => {
    localStorage.setItem('lendr-dark', val)
    document.documentElement.classList.toggle('dark', val)
  }, { immediate: true })

  // ─── Notifications (real-time count from Reverb — P7) ─
  const unreadCount = ref(0)

  function setUnreadCount(n) {
    unreadCount.value = n
  }

  function incrementUnread() {
    unreadCount.value++
  }

  function clearUnread() {
    unreadCount.value = 0
  }

  return { darkMode, toggleDarkMode, unreadCount, setUnreadCount, incrementUnread, clearUnread }
})
