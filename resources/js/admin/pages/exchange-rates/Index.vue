<template>
  <AppLayout>
    <template #header>
      <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
          <h1 class="text-2xl font-bold text-neutral-900">Exchange Rates</h1>
          <p class="text-sm text-neutral-500 mt-0.5">{{ rates.total.toLocaleString() }} rate{{ rates.total !== 1 ? 's' : '' }}</p>
        </div>
        <button @click="openCreate" class="btn-primary">+ Add Rate</button>
      </div>
    </template>

    <!-- Filters -->
    <div class="lendr-card p-4 mb-4 flex flex-col sm:flex-row gap-3">
      <input
        v-model="filterFrom"
        type="text"
        placeholder="From (e.g. USD)"
        maxlength="3"
        class="input w-full sm:w-32 uppercase"
        @input="applyFilters"
      />
      <input
        v-model="filterTo"
        type="text"
        placeholder="To (e.g. ZMW)"
        maxlength="3"
        class="input w-full sm:w-32 uppercase"
        @input="applyFilters"
      />
    </div>

    <!-- Table -->
    <div class="lendr-card overflow-hidden">
      <div class="overflow-x-auto">
        <table class="w-full text-sm">
          <thead>
            <tr class="border-b border-neutral-100 bg-neutral-50">
              <th class="text-left px-5 py-3 text-xs font-semibold text-neutral-500 uppercase tracking-wide">Pair</th>
              <th class="text-left px-5 py-3 text-xs font-semibold text-neutral-500 uppercase tracking-wide">Rate</th>
              <th class="text-left px-5 py-3 text-xs font-semibold text-neutral-500 uppercase tracking-wide">Effective Date</th>
              <th class="text-right px-5 py-3 text-xs font-semibold text-neutral-500 uppercase tracking-wide">Actions</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-neutral-50">
            <tr v-if="rates.data.length === 0">
              <td colspan="4" class="px-5 py-10 text-center text-neutral-400">No exchange rates found.</td>
            </tr>
            <tr v-for="rate in rates.data" :key="rate.id" class="hover:bg-neutral-50 transition">
              <td class="px-5 py-3.5">
                <span class="font-semibold font-mono text-neutral-900">{{ rate.from_currency }}</span>
                <span class="mx-1 text-neutral-400">→</span>
                <span class="font-semibold font-mono text-neutral-900">{{ rate.to_currency }}</span>
              </td>
              <td class="px-5 py-3.5 font-mono text-neutral-700">{{ Number(rate.rate).toFixed(4) }}</td>
              <td class="px-5 py-3.5 text-neutral-600">{{ rate.effective_date }}</td>
              <td class="px-5 py-3.5 text-right">
                <button @click="openEdit(rate)" class="text-primary-600 hover:underline mr-3 text-xs">Edit</button>
                <button @click="confirmDelete(rate)" class="text-red-500 hover:underline text-xs">Delete</button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Pagination -->
      <div v-if="rates.last_page > 1" class="px-5 py-3 border-t border-neutral-100 flex items-center justify-between text-sm">
        <span class="text-neutral-500">Page {{ rates.current_page }} of {{ rates.last_page }}</span>
        <div class="flex gap-2">
          <Link
            v-if="rates.prev_page_url"
            :href="rates.prev_page_url"
            class="px-3 py-1 rounded border border-neutral-200 hover:bg-neutral-50"
          >← Prev</Link>
          <Link
            v-if="rates.next_page_url"
            :href="rates.next_page_url"
            class="px-3 py-1 rounded border border-neutral-200 hover:bg-neutral-50"
          >Next →</Link>
        </div>
      </div>
    </div>

    <!-- Create / Edit Drawer -->
    <Teleport to="body">
      <div v-if="drawer.open" class="fixed inset-0 z-50 flex">
        <div class="absolute inset-0 bg-black/30" @click="closeDrawer" />
        <div class="relative ml-auto w-full max-w-md bg-white shadow-xl flex flex-col h-full">
          <div class="flex items-center justify-between px-6 py-4 border-b border-neutral-100">
            <h2 class="font-semibold text-lg">{{ drawer.editing ? 'Edit Rate' : 'Add Rate' }}</h2>
            <button @click="closeDrawer" class="text-neutral-400 hover:text-neutral-700 text-2xl leading-none">&times;</button>
          </div>

          <form @submit.prevent="submitForm" class="flex-1 overflow-y-auto px-6 py-5 space-y-4">
            <div v-if="!drawer.editing" class="grid grid-cols-2 gap-4">
              <div>
                <label class="label">From Currency</label>
                <input v-model="form.from_currency" type="text" maxlength="3" placeholder="USD" class="input uppercase" required />
                <p v-if="errors.from_currency" class="text-xs text-red-500 mt-1">{{ errors.from_currency }}</p>
              </div>
              <div>
                <label class="label">To Currency</label>
                <input v-model="form.to_currency" type="text" maxlength="3" placeholder="ZMW" class="input uppercase" required />
                <p v-if="errors.to_currency" class="text-xs text-red-500 mt-1">{{ errors.to_currency }}</p>
              </div>
            </div>
            <div v-else class="text-sm text-neutral-500 font-mono bg-neutral-50 rounded px-3 py-2">
              {{ drawer.rate.from_currency }} → {{ drawer.rate.to_currency }}
            </div>

            <div>
              <label class="label">Exchange Rate</label>
              <input v-model="form.rate" type="number" step="0.000001" min="0.000001" placeholder="26.500000" class="input" required />
              <p v-if="errors.rate" class="text-xs text-red-500 mt-1">{{ errors.rate }}</p>
            </div>

            <div>
              <label class="label">Effective Date</label>
              <input v-model="form.effective_date" type="date" class="input" required />
              <p v-if="errors.effective_date" class="text-xs text-red-500 mt-1">{{ errors.effective_date }}</p>
            </div>
          </form>

          <div class="px-6 py-4 border-t border-neutral-100 flex justify-end gap-3">
            <button type="button" @click="closeDrawer" class="btn-outline">Cancel</button>
            <button type="button" @click="submitForm" :disabled="submitting" class="btn-primary">
              {{ submitting ? 'Saving…' : 'Save Rate' }}
            </button>
          </div>
        </div>
      </div>
    </Teleport>

    <!-- Delete Confirm Modal -->
    <Teleport to="body">
      <div v-if="deleteTarget" class="fixed inset-0 z-50 flex items-center justify-center">
        <div class="absolute inset-0 bg-black/30" @click="deleteTarget = null" />
        <div class="relative bg-white rounded-xl shadow-xl p-6 w-full max-w-sm mx-4">
          <h3 class="font-semibold text-lg mb-2">Delete Rate?</h3>
          <p class="text-sm text-neutral-500 mb-5">
            This will permanently remove the <strong>{{ deleteTarget.from_currency }}/{{ deleteTarget.to_currency }}</strong>
            rate for <strong>{{ deleteTarget.effective_date }}</strong>.
          </p>
          <div class="flex justify-end gap-3">
            <button @click="deleteTarget = null" class="btn-outline">Cancel</button>
            <button @click="doDelete" :disabled="submitting" class="btn-danger">
              {{ submitting ? 'Deleting…' : 'Delete' }}
            </button>
          </div>
        </div>
      </div>
    </Teleport>
  </AppLayout>
</template>

<script setup>
import { ref, reactive } from 'vue'
import { Link, router } from '@inertiajs/vue3'
import AppLayout from '@/admin/components/layout/AppLayout.vue'

const props = defineProps({
  rates: Object,
  filters: Object,
})

const filterFrom = ref(props.filters?.from ?? '')
const filterTo   = ref(props.filters?.to ?? '')

function applyFilters() {
  router.get(route('exchange-rates.index'), {
    from: filterFrom.value || undefined,
    to:   filterTo.value   || undefined,
  }, { preserveState: true, replace: true })
}

// ─── Drawer ──────────────────────────────────────────────────────────────────

const drawer = reactive({ open: false, editing: false, rate: null })
const submitting = ref(false)

const form = reactive({
  from_currency:  '',
  to_currency:    '',
  rate:           '',
  effective_date: '',
})

const errors = reactive({})

function openCreate() {
  Object.assign(form, { from_currency: '', to_currency: '', rate: '', effective_date: '' })
  Object.assign(errors, {})
  drawer.editing = false
  drawer.rate    = null
  drawer.open    = true
}

function openEdit(rate) {
  Object.assign(form, {
    from_currency:  rate.from_currency,
    to_currency:    rate.to_currency,
    rate:           rate.rate,
    effective_date: rate.effective_date,
  })
  Object.assign(errors, {})
  drawer.editing = true
  drawer.rate    = rate
  drawer.open    = true
}

function closeDrawer() {
  drawer.open = false
}

function submitForm() {
  submitting.value = true
  Object.assign(errors, {})

  if (drawer.editing) {
    router.put(route('exchange-rates.update', drawer.rate.id), form, {
      onSuccess: closeDrawer,
      onError:   errs => Object.assign(errors, errs),
      onFinish:  () => { submitting.value = false },
    })
  } else {
    router.post(route('exchange-rates.store'), form, {
      onSuccess: closeDrawer,
      onError:   errs => Object.assign(errors, errs),
      onFinish:  () => { submitting.value = false },
    })
  }
}

// ─── Delete ──────────────────────────────────────────────────────────────────

const deleteTarget = ref(null)

function confirmDelete(rate) {
  deleteTarget.value = rate
}

function doDelete() {
  submitting.value = true
  router.delete(route('exchange-rates.destroy', deleteTarget.value.id), {
    onSuccess: () => { deleteTarget.value = null },
    onFinish:  () => { submitting.value = false },
  })
}
</script>
