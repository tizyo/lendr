import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import { usePage } from '@inertiajs/vue3'

export const useAuthStore = defineStore('auth', () => {
  const page = usePage()

  const user        = computed(() => page.props.auth?.user ?? null)
  const permissions = computed(() => user.value?.permissions ?? [])
  const role        = computed(() => user.value?.role ?? null)

  function can(permission) {
    return permissions.value.includes(permission)
  }

  function hasRole(r) {
    return role.value === r
  }

  function isSuperAdmin() {
    return role.value === 'super_admin'
  }

  return { user, permissions, role, can, hasRole, isSuperAdmin }
})
