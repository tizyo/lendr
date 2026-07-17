<template>
  <AppLayout>
    <template #header>
      <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
          <h1 class="text-2xl font-bold text-neutral-900">Staff</h1>
          <p class="text-sm text-neutral-500 mt-0.5">{{ staff.total.toLocaleString() }} team members</p>
        </div>
        <button @click="openCreate" class="btn-primary">+ Add Staff</button>
      </div>
    </template>

    <!-- Filters -->
    <div class="lendr-card p-4 mb-4 flex flex-col sm:flex-row gap-3">
      <input
        v-model="search"
        type="search"
        placeholder="Search by name or email…"
        class="input flex-1"
        @input="debouncedSearch"
      />
      <select v-model="role" @change="applyFilters" class="input w-full sm:w-44">
        <option value="">All roles</option>
        <option v-for="r in roles" :key="r.value" :value="r.value">{{ r.label }}</option>
      </select>
      <select v-model="status" @change="applyFilters" class="input w-full sm:w-36">
        <option value="">All statuses</option>
        <option value="active">Active</option>
        <option value="inactive">Inactive</option>
      </select>
    </div>

    <!-- Table -->
    <div class="lendr-card overflow-hidden">
      <div class="overflow-x-auto">
        <table class="w-full text-sm">
          <thead>
            <tr class="border-b border-neutral-100 bg-neutral-50">
              <th class="text-left px-5 py-3 text-xs font-semibold text-neutral-500 uppercase tracking-wide">Name</th>
              <th class="text-left px-5 py-3 text-xs font-semibold text-neutral-500 uppercase tracking-wide hidden md:table-cell">Email</th>
              <th class="text-left px-5 py-3 text-xs font-semibold text-neutral-500 uppercase tracking-wide">Role</th>
              <th class="text-left px-5 py-3 text-xs font-semibold text-neutral-500 uppercase tracking-wide hidden lg:table-cell">Department</th>
              <th class="text-left px-5 py-3 text-xs font-semibold text-neutral-500 uppercase tracking-wide">Status</th>
              <th class="text-right px-5 py-3 text-xs font-semibold text-neutral-500 uppercase tracking-wide">Actions</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-neutral-50">
            <tr v-for="member in staff.data" :key="member.id" class="hover:bg-neutral-50 transition">
              <td class="px-5 py-3.5">
                <div class="flex items-center gap-3">
                  <div class="w-8 h-8 rounded-full bg-primary-100 text-primary-700 flex items-center justify-center text-xs font-bold shrink-0">
                    {{ initials(member.name) }}
                  </div>
                  <div>
                    <p class="font-medium text-neutral-900">{{ member.name }}</p>
                    <p class="text-xs text-neutral-500">{{ member.username || '—' }}</p>
                  </div>
                </div>
              </td>
              <td class="px-5 py-3.5 text-neutral-700 hidden md:table-cell">{{ member.email }}</td>
              <td class="px-5 py-3.5">
                <span class="lendr-badge" :class="roleBadgeClass(member.role)">
                  {{ member.role?.replace('_', ' ') }}
                </span>
              </td>
              <td class="px-5 py-3.5 text-neutral-600 hidden lg:table-cell">{{ member.department || '—' }}</td>
              <td class="px-5 py-3.5">
                <span :class="member.is_active ? 'lendr-badge-success' : 'lendr-badge-neutral'">
                  {{ member.is_active ? 'Active' : 'Inactive' }}
                </span>
              </td>
              <td class="px-5 py-3.5 text-right">
                <div class="flex items-center justify-end gap-2">
                  <button @click="openEdit(member)" class="text-xs text-primary-600 hover:text-primary-800 font-medium">Edit</button>
                  <button @click="confirmToggle(member)" class="text-xs font-medium"
                    :class="member.is_active ? 'text-amber-600 hover:text-amber-800' : 'text-green-600 hover:text-green-800'">
                    {{ member.is_active ? 'Deactivate' : 'Activate' }}
                  </button>
                  <button @click="confirmResetPassword(member)" class="text-xs text-neutral-500 hover:text-neutral-800 font-medium">Reset PW</button>
                </div>
              </td>
            </tr>
            <tr v-if="!staff.data.length">
              <td colspan="6" class="px-5 py-12 text-center text-neutral-400">No staff found.</td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Pagination -->
      <div v-if="staff.last_page > 1" class="px-5 py-3 border-t border-neutral-100 flex items-center justify-between text-sm">
        <p class="text-neutral-500">Showing {{ staff.from }}–{{ staff.to }} of {{ staff.total }}</p>
        <div class="flex gap-1">
          <Link
            v-for="link in staff.links"
            :key="link.label"
            :href="link.url || '#'"
            class="px-3 py-1 rounded text-sm"
            :class="link.active ? 'bg-primary-600 text-white' : link.url ? 'text-neutral-600 hover:bg-neutral-100' : 'text-neutral-300 cursor-default'"
            v-html="link.label"
          />
        </div>
      </div>
    </div>

    <!-- Create / Edit Drawer -->
    <Transition name="slide">
      <div v-if="drawer.open" class="fixed inset-0 z-50 flex">
        <div class="absolute inset-0 bg-black/40" @click="closeDrawer" />
        <div class="relative ml-auto w-full max-w-md bg-white h-full shadow-2xl flex flex-col">
          <div class="flex items-center justify-between px-6 py-4 border-b border-neutral-100">
            <h2 class="text-lg font-semibold text-neutral-900">{{ drawer.editing ? 'Edit Staff' : 'Add Staff' }}</h2>
            <button @click="closeDrawer" class="text-neutral-400 hover:text-neutral-700 text-2xl leading-none">&times;</button>
          </div>

          <form @submit.prevent="submitDrawer" class="flex-1 overflow-y-auto px-6 py-5 space-y-4">
            <div>
              <label class="label">Full Name *</label>
              <input v-model="form.name" type="text" class="input w-full" required />
              <p v-if="errors.name" class="text-xs text-red-600 mt-1">{{ errors.name }}</p>
            </div>
            <div>
              <label class="label">Email *</label>
              <input v-model="form.email" type="email" class="input w-full" required />
              <p v-if="errors.email" class="text-xs text-red-600 mt-1">{{ errors.email }}</p>
            </div>
            <div>
              <label class="label">Username</label>
              <input v-model="form.username" type="text" class="input w-full" />
            </div>
            <div>
              <label class="label">Phone</label>
              <input v-model="form.phone" type="tel" class="input w-full" />
            </div>
            <div>
              <label class="label">Role *</label>
              <select v-model="form.role" class="input w-full" required>
                <option value="">Select role…</option>
                <option v-for="r in roles" :key="r.value" :value="r.value">{{ r.label }}</option>
              </select>
              <p v-if="errors.role" class="text-xs text-red-600 mt-1">{{ errors.role }}</p>
            </div>
            <div>
              <label class="label">Department</label>
              <input v-model="form.department" type="text" class="input w-full" />
            </div>
            <div v-if="!drawer.editing">
              <label class="label">Password *</label>
              <input v-model="form.password" type="password" class="input w-full" required minlength="8" />
              <p v-if="errors.password" class="text-xs text-red-600 mt-1">{{ errors.password }}</p>
            </div>
          </form>

          <div class="px-6 py-4 border-t border-neutral-100 flex justify-end gap-3">
            <button type="button" @click="closeDrawer" class="btn-ghost">Cancel</button>
            <button @click="submitDrawer" class="btn-primary" :disabled="submitting">
              {{ submitting ? 'Saving…' : (drawer.editing ? 'Save Changes' : 'Create Staff') }}
            </button>
          </div>
        </div>
      </div>
    </Transition>
  </AppLayout>
</template>

<script setup>
import { ref, reactive } from 'vue'
import { Link, router } from '@inertiajs/vue3'
import AppLayout from '@/admin/components/layout/AppLayout.vue'

const props = defineProps({
  staff:   Object,
  filters: Object,
  roles:   Array,
})

const search = ref(props.filters?.search || '')
const role   = ref(props.filters?.role   || '')
const status = ref(props.filters?.status || '')

const drawer    = reactive({ open: false, editing: false, staffId: null })
const form      = reactive({ name: '', email: '', username: '', phone: '', role: '', department: '', password: '' })
const errors    = reactive({})
const submitting = ref(false)

// ─── Search / filter ────────────────────────────────

let searchTimeout = null
function debouncedSearch() {
  clearTimeout(searchTimeout)
  searchTimeout = setTimeout(() => applyFilters(), 400)
}

function applyFilters() {
  router.get(route('staff.index'), {
    search: search.value || undefined,
    role:   role.value   || undefined,
    status: status.value || undefined,
  }, { preserveState: true, replace: true })
}

// ─── Drawer ─────────────────────────────────────────

function openCreate() {
  Object.assign(form, { name: '', email: '', username: '', phone: '', role: '', department: '', password: '' })
  Object.assign(errors, {})
  drawer.editing = false
  drawer.staffId = null
  drawer.open    = true
}

function openEdit(member) {
  Object.assign(form, {
    name:       member.name,
    email:      member.email,
    username:   member.username || '',
    phone:      member.phone    || '',
    role:       member.role     || '',
    department: member.department || '',
    password:   '',
  })
  Object.assign(errors, {})
  drawer.editing = true
  drawer.staffId = member.id
  drawer.open    = true
}

function closeDrawer() { drawer.open = false }

function submitDrawer() {
  submitting.value = true
  const payload    = { ...form }
  const options    = {
    preserveScroll: true,
    onSuccess: () => { closeDrawer(); submitting.value = false },
    onError:   (errs) => { Object.assign(errors, errs); submitting.value = false },
  }

  if (drawer.editing) {
    router.put(route('staff.update', drawer.staffId), payload, options)
  } else {
    router.post(route('staff.store'), payload, options)
  }
}

// ─── Actions ────────────────────────────────────────

function confirmToggle(member) {
  const action = member.is_active ? 'deactivate' : 'activate'
  if (confirm(`${action.charAt(0).toUpperCase() + action.slice(1)} ${member.name}?`)) {
    router.put(route('staff.toggle-status', member.id), {}, { preserveScroll: true })
  }
}

function confirmResetPassword(member) {
  if (confirm(`Send password reset email to ${member.email}?`)) {
    router.post(route('staff.reset-password', member.id), {}, { preserveScroll: true })
  }
}

// ─── Helpers ────────────────────────────────────────

function initials(name) {
  return name?.split(' ').map(w => w[0]).slice(0, 2).join('').toUpperCase() || '?'
}

const ROLE_CLASSES = {
  super_admin:  'bg-red-100 text-red-700',
  manager:      'bg-orange-100 text-orange-700',
  loan_officer: 'bg-blue-100 text-blue-700',
  cashier:      'bg-green-100 text-green-700',
  accounts:     'bg-purple-100 text-purple-700',
  read_only:    'bg-neutral-100 text-neutral-600',
}

function roleBadgeClass(role) {
  return ROLE_CLASSES[role] || 'bg-neutral-100 text-neutral-600'
}
</script>

<style scoped>
.slide-enter-active, .slide-leave-active { transition: opacity 0.25s; }
.slide-enter-from, .slide-leave-to { opacity: 0; }
.slide-enter-active .relative, .slide-leave-active .relative { transition: transform 0.25s ease; }
.slide-enter-from .relative, .slide-leave-to .relative { transform: translateX(100%); }
</style>
