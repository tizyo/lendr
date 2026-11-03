import { reactive } from 'vue'

/**
 * App-wide toasts. Server flash messages arrive through FlashMessage.vue; pages
 * call useToast() directly for results of their own axios calls:
 *
 *   const toast = useToast()
 *   toast.success('Loan type created.')
 *   toast.error(e.response?.data?.message ?? 'Something went wrong.')
 */
const state = reactive({ items: [] })
let nextId = 0

// Errors stay up longer: they usually need reading, not just noticing.
const DURATION = { success: 4500, info: 5000, warning: 7000, error: 8000 }

export function pushToast(type, text, { title = null, duration = null } = {}) {
  if (!text) return

  // The same message twice in a row (e.g. a double click) shows once.
  if (state.items.some((t) => t.type === type && t.text === text)) return

  state.items.push({ id: ++nextId, type, text, title, duration: duration ?? DURATION[type] ?? 5000 })

  // Keep the stack short.
  if (state.items.length > 4) state.items.shift()
}

export function dismissToast(id) {
  const i = state.items.findIndex((t) => t.id === id)
  if (i !== -1) state.items.splice(i, 1)
}

export function useToast() {
  return {
    items: state.items,
    success: (text, opts) => pushToast('success', text, opts),
    error: (text, opts) => pushToast('error', text, opts),
    warning: (text, opts) => pushToast('warning', text, opts),
    info: (text, opts) => pushToast('info', text, opts),
    dismiss: dismissToast,
  }
}
