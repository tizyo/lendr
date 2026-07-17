<template>
  <AppLayout>
    <template #header>
      <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
          <h1 class="text-2xl font-bold text-neutral-900">Branches</h1>
          <p class="text-sm text-neutral-500 mt-0.5">{{ branches.total.toLocaleString() }} branch{{ branches.total !== 1 ? 'es' : '' }}</p>
        </div>
        <button @click="openCreate" class="btn-primary">+ Add Branch</button>
      </div>
    </template>

    <!-- Filters -->
    <div class="lendr-card p-4 mb-4 flex flex-col sm:flex-row gap-3">
      <input
        v-model="search"
        type="search"
        placeholder="Search by name, code or city…"
        class="input flex-1"
        @input="debouncedSearch"
      />
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
              <th class="text-left px-5 py-3 text-xs font-semibold text-neutral-500 uppercase tracking-wide">Branch</th>
              <th class="text-left px-5 py-3 text-xs font-semibold text-neutral-500 uppercase tracking-wide hidden md:table-cell">Location</th>
              <th class="text-left px-5 py-3 text-xs font-semibold text-neutral-500 uppercase tracking-wide hidden lg:table-cell">Manager</th>
              <th class="text-left px-5 py-3 text-xs font-semibold text-neutral-500 uppercase tracking-wide hidden md:table-cell">Contact</th>
              <th class="text-left px-5 py-3 text-xs font-semibold text-neutral-500 uppercase tracking-wide">Status</th>
              <th class="text-right px-5 py-3 text-xs font-semibold text-neutral-500 uppercase tracking-wide">Actions</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-neutral-50">
            <tr v-for="branch in branches.data" :key="branch.id" class="hover:bg-neutral-50 transition">
              <td class="px-5 py-3.5">
                <p class="font-medium text-neutral-900">{{ branch.name }}</p>
                <p class="text-xs text-neutral-500 font-mono">{{ branch.code }}</p>
              </td>
              <td class="px-5 py-3.5 text-neutral-600 hidden md:table-cell">
                {{ [branch.city, branch.country].filter(Boolean).join(', ') || '—' }}
              </td>
              <td class="px-5 py-3.5 text-neutral-600 hidden lg:table-cell">
                {{ branch.manager?.name || '—' }}
              </td>
              <td class="px-5 py-3.5 hidden md:table-cell">
                <p class="text-neutral-600">{{ branch.phone || '—' }}</p>
                <p class="text-xs text-neutral-400">{{ branch.email || '' }}</p>
              </td>
              <td class="px-5 py-3.5">
                <span :class="branch.is_active ? 'lendr-badge-success' : 'lendr-badge-neutral'">
                  {{ branch.is_active ? 'Active' : 'Inactive' }}
                </span>
              </td>
              <td class="px-5 py-3.5 text-right">
                <div class="flex items-center justify-end gap-2">
                  <button @click="openEdit(branch)" class="text-xs text-primary-600 hover:text-primary-800 font-medium">Edit</button>
                  <button @click="confirmDelete(branch)" class="text-xs text-red-500 hover:text-red-700 font-medium">Delete</button>
                </div>
              </td>
            </tr>
            <tr v-if="!branches.data.length">
              <td colspan="6" class="px-5 py-12 text-center text-neutral-400">No branches found.</td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Pagination -->
      <div v-if="branches.last_page > 1" class="px-5 py-3 border-t border-neutral-100 flex items-center justify-between text-sm">
        <p class="text-neutral-500">Showing {{ branches.from }}–{{ branches.to }} of {{ branches.total }}</p>
        <div class="flex gap-1">
          <Link
            v-for="link in branches.links"
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
            <h2 class="text-lg font-semibold text-neutral-900">{{ drawer.editing ? 'Edit Branch' : 'Add Branch' }}</h2>
            <button @click="closeDrawer" class="text-neutral-400 hover:text-neutral-700 text-2xl leading-none">&times;</button>
          </div>

          <form @submit.prevent="submitDrawer" class="flex-1 overflow-y-auto px-6 py-5 space-y-4">
            <div>
              <label class="label">Branch Name *</label>
              <input v-model="form.name" type="text" class="input w-full" required />
              <p v-if="errors.name" class="text-xs text-red-600 mt-1">{{ errors.name }}</p>
            </div>
            <div>
              <label class="label">Branch Code *</label>
              <input v-model="form.code" type="text" class="input w-full font-mono uppercase" maxlength="20" required />
              <p v-if="errors.code" class="text-xs text-red-600 mt-1">{{ errors.code }}</p>
            </div>
            <div>
              <label class="label">Address</label>
              <input v-model="form.address" type="text" class="input w-full" />
            </div>
            <div class="grid grid-cols-2 gap-3">
              <div>
                <label class="label">City</label>
                <input v-model="form.city" type="text" class="input w-full" />
              </div>
              <div>
                <label class="label">Country</label>
                <input v-model="form.country" type="text" class="input w-full" />
              </div>
            </div>
            <div>
              <label class="label">Phone</label>
              <input v-model="form.phone" type="tel" class="input w-full" />
            </div>
            <div>
              <label class="label">Email</label>
              <input v-model="form.email" type="email" class="input w-full" />
            </div>
            <div>
              <label class="label">Branch Manager</label>
              <select v-model="form.manager_id" class="input w-full">
                <option value="">None</option>
                <option v-for="m in managers" :key="m.id" :value="m.id">{{ m.name }}</option>
              </select>
            </div>
            <div>
              <label class="label">Notes</label>
              <textarea v-model="form.notes" rows="3" class="input w-full resize-none" />
            </div>
            <div class="flex items-center gap-2">
              <input v-model="form.is_active" type="checkbox" id="is_active" class="rounded text-primary-600" />
              <label for="is_active" class="text-sm text-neutral-700">Active</label>
            </div>
          </form>

          <div class="px-6 py-4 border-t border-neutral-100 flex justify-end gap-3">
            <button type="button" @click="closeDrawer" class="btn-ghost">Cancel</button>
            <button @click="submitDrawer" class="btn-primary" :disabled="submitting">
              {{ submitting ? 'Saving…' : (drawer.editing ? 'Save Changes' : 'Create Branch') }}
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
  branches: Object,
  managers: Array,
  filters:  Object,
})

const search = ref(props.filters?.search || '')
const status = ref(props.filters?.status || '')

const drawer     = reactive({ open: false, editing: false, branchId: null })
const form       = reactive({ name: '', code: '', address: '', city: '', country: '', phone: '', email: '', manager_id: '', notes: '', is_active: true })
const errors     = reactive({})
const submitting = ref(false)

let searchTimeout = null
function debouncedSearch() {
  clearTimeout(searchTimeout)
  searchTimeout = setTimeout(() => applyFilters(), 400)
}

function applyFilters() {
  router.get(route('branches.index'), {
    search: search.value || undefined,
    status: status.value || undefined,
  }, { preserveState: true, replace: true })
}

function openCreate() {
  Object.assign(form, { name: '', code: '', address: '', city: '', country: '', phone: '', email: '', manager_id: '', notes: '', is_active: true })
  Object.assign(errors, {})
  drawer.editing  = false
  drawer.branchId = null
  drawer.open     = true
}

function openEdit(branch) {
  Object.assign(form, {
    name:       branch.name,
    code:       branch.code,
    address:    branch.address   || '',
    city:       branch.city      || '',
    country:    branch.country   || '',
    phone:      branch.phone     || '',
    email:      branch.email     || '',
    manager_id: branch.manager?.id || '',
    notes:      branch.notes     || '',
    is_active:  branch.is_active,
  })
  Object.assign(errors, {})
  drawer.editing  = true
  drawer.branchId = branch.id
  drawer.open     = true
}

function closeDrawer() { drawer.open = false }

function submitDrawer() {
  submitting.value = true
  const payload = { ...form }
  const options = {
    preserveScroll: true,
    onSuccess: () => { closeDrawer(); submitting.value = false },
    onError:   (errs) => { Object.assign(errors, errs); submitting.value = false },
  }

  if (drawer.editing) {
    router.put(route('branches.update', drawer.branchId), payload, options)
  } else {
    router.post(route('branches.store'), payload, options)
  }
}

function confirmDelete(branch) {
  if (confirm(`Delete branch "${branch.name}"? This cannot be undone.`)) {
    router.delete(route('branches.destroy', branch.id), { preserveScroll: true })
  }
}
</script>

<style scoped>
.slide-enter-active, .slide-leave-active { transition: opacity 0.25s; }
.slide-enter-from, .slide-leave-to { opacity: 0; }
</style>
