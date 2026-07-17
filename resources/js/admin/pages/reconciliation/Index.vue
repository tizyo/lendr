<template>
  <AppLayout>
    <template #header>
      <div class="flex items-center justify-between">
        <div>
          <h1 class="text-xl font-bold text-neutral-900">Bank Reconciliation</h1>
          <p class="text-sm text-neutral-500 mt-0.5">Import bank statements and match transactions</p>
        </div>
        <button @click="showImportModal = true" class="lendr-btn-primary">+ Import Statement</button>
      </div>
    </template>

    <!-- Statements List -->
    <div class="lendr-card overflow-hidden mb-4">
      <div class="p-4 border-b">
        <span class="font-medium text-sm text-neutral-700">Bank Statements</span>
      </div>
      <div v-if="loadingStmts" class="p-10 text-center text-neutral-400">Loading…</div>
      <table v-else class="w-full text-sm">
        <thead class="bg-neutral-50 text-neutral-500 text-xs uppercase tracking-wide">
          <tr>
            <th class="px-4 py-3 text-left">Reference</th>
            <th class="px-4 py-3 text-left">Account</th>
            <th class="px-4 py-3 text-right">Transactions</th>
            <th class="px-4 py-3 text-right">Matched</th>
            <th class="px-4 py-3 text-right">Unmatched</th>
            <th class="px-4 py-3 text-center">Status</th>
            <th class="px-4 py-3"></th>
          </tr>
        </thead>
        <tbody class="divide-y divide-neutral-100">
          <tr v-if="statements.length === 0"><td colspan="7" class="px-4 py-10 text-center text-neutral-400">No statements imported yet.</td></tr>
          <tr v-for="s in statements" :key="s.id" class="hover:bg-neutral-50">
            <td class="px-4 py-3 font-mono text-xs">{{ s.filename }}</td>
            <td class="px-4 py-3 text-neutral-700">{{ s.bank_name ?? '—' }}</td>
            <td class="px-4 py-3 text-right">{{ s.total_rows }}</td>
            <td class="px-4 py-3 text-right text-green-600 font-medium">{{ s.matched_count }}</td>
            <td class="px-4 py-3 text-right text-red-500">{{ s.unmatched_count }}</td>
            <td class="px-4 py-3 text-center">
              <span :class="s.status === 'reconciled' ? 'lendr-badge-success' : s.status === 'partial' ? 'lendr-badge-warning' : 'lendr-badge-neutral'" class="lendr-badge text-xs capitalize">
                {{ s.status }}
              </span>
            </td>
            <td class="px-4 py-3 text-right">
              <button @click="openStatement(s)" class="text-primary-600 text-xs hover:underline mr-3">View</button>
              <button @click="reconcileStatement(s)" :disabled="reconciling === s.id" class="text-green-600 text-xs hover:underline">
                {{ reconciling === s.id ? 'Running…' : 'Auto-Match' }}
              </button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Unmatched Transactions -->
    <div v-if="activeStatement" class="lendr-card overflow-hidden">
      <div class="p-4 border-b flex justify-between items-center">
        <span class="font-medium text-sm text-neutral-700">Unmatched — {{ activeStatement.filename }}</span>
        <button @click="activeStatement = null" class="text-neutral-400 hover:text-neutral-700 text-xs">Close</button>
      </div>
      <div v-if="loadingUnmatched" class="p-8 text-center text-neutral-400">Loading…</div>
      <table v-else class="w-full text-sm">
        <thead class="bg-neutral-50 text-neutral-500 text-xs uppercase tracking-wide">
          <tr>
            <th class="px-4 py-3 text-left">Date</th>
            <th class="px-4 py-3 text-left">Description</th>
            <th class="px-4 py-3 text-right">Amount</th>
            <th class="px-4 py-3 text-left">Reference</th>
            <th class="px-4 py-3"></th>
          </tr>
        </thead>
        <tbody class="divide-y divide-neutral-100">
          <tr v-if="unmatched.length === 0"><td colspan="5" class="px-4 py-8 text-center text-neutral-400">All transactions matched.</td></tr>
          <tr v-for="tx in unmatched" :key="tx.id" class="hover:bg-neutral-50">
            <td class="px-4 py-3 text-xs text-neutral-500">{{ tx.transaction_date }}</td>
            <td class="px-4 py-3 text-neutral-700">{{ tx.description }}</td>
            <td class="px-4 py-3 text-right font-medium">{{ fmt(tx.amount) }}</td>
            <td class="px-4 py-3 font-mono text-xs">{{ tx.reference }}</td>
            <td class="px-4 py-3 text-right">
              <button @click="ignoreTransaction(tx)" class="text-neutral-400 text-xs hover:text-neutral-600">Ignore</button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Import Modal -->
    <div v-if="showImportModal" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4">
      <div class="bg-white rounded-2xl w-full max-w-lg p-6 space-y-4">
        <h2 class="font-bold text-lg">Import Bank Statement</h2>
        <div class="space-y-3">
          <div>
            <label class="lendr-label">CSV File *</label>
            <input ref="fileInput" type="file" accept=".csv,.txt" class="lendr-input w-full" @change="onFileChange" />
            <p class="text-xs text-neutral-400 mt-1">Upload a CSV bank statement (headers: date, description, amount, reference)</p>
          </div>
          <div>
            <label class="lendr-label">Bank Name</label>
            <input v-model="importForm.bank_name" class="lendr-input w-full" placeholder="e.g. Zanaco" />
          </div>
          <div class="grid grid-cols-2 gap-3">
            <div>
              <label class="lendr-label">Statement From</label>
              <input v-model="importForm.statement_from" type="date" class="lendr-input w-full" />
            </div>
            <div>
              <label class="lendr-label">Statement To</label>
              <input v-model="importForm.statement_to" type="date" class="lendr-input w-full" />
            </div>
          </div>
        </div>
        <p v-if="importError" class="text-red-500 text-sm">{{ importError }}</p>
        <div class="flex gap-3 justify-end">
          <button @click="showImportModal = false" class="lendr-btn-ghost">Cancel</button>
          <button @click="importStatement" :disabled="importing" class="lendr-btn-primary">{{ importing ? 'Importing…' : 'Import' }}</button>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import AppLayout from '@/admin/components/layout/AppLayout.vue'
import axios from 'axios'

const statements = ref([])
const loadingStmts = ref(false)
const activeStatement = ref(null)
const unmatched = ref([])
const loadingUnmatched = ref(false)
const reconciling = ref(null)

const showImportModal = ref(false)
const importing = ref(false)
const importError = ref('')
const importFile = ref(null)
const fileInput = ref(null)
const importForm = ref({ bank_name: '', statement_from: '', statement_to: '' })

function onFileChange(e) {
  importFile.value = e.target.files[0] ?? null
}

async function fetchStatements() {
  loadingStmts.value = true
  try {
    const { data } = await axios.get('/api/v1/reconciliation')
    statements.value = data.data ?? []
  } finally { loadingStmts.value = false }
}

async function openStatement(s) {
  activeStatement.value = s
  loadingUnmatched.value = true
  unmatched.value = []
  try {
    const { data } = await axios.get(`/api/v1/reconciliation/${s.id}/unmatched`)
    unmatched.value = data.data ?? []
  } finally { loadingUnmatched.value = false }
}

async function reconcileStatement(s) {
  reconciling.value = s.id
  try {
    await axios.post(`/api/v1/reconciliation/${s.id}/reconcile`)
    await fetchStatements()
    if (activeStatement.value?.id === s.id) await openStatement(s)
  } finally { reconciling.value = null }
}

async function ignoreTransaction(tx) {
  try {
    await axios.post(`/api/v1/reconciliation/transactions/${tx.id}/ignore`)
    unmatched.value = unmatched.value.filter(t => t.id !== tx.id)
  } catch {}
}

async function importStatement() {
  importError.value = ''
  if (!importFile.value) { importError.value = 'Please select a CSV file.'; return }
  importing.value = true
  try {
    const fd = new FormData()
    fd.append('file', importFile.value)
    if (importForm.value.bank_name) fd.append('bank_name', importForm.value.bank_name)
    if (importForm.value.statement_from) fd.append('statement_from', importForm.value.statement_from)
    if (importForm.value.statement_to) fd.append('statement_to', importForm.value.statement_to)
    await axios.post('/api/v1/reconciliation/import', fd, { headers: { 'Content-Type': 'multipart/form-data' } })
    await fetchStatements()
    showImportModal.value = false
    importFile.value = null
    importForm.value = { bank_name: '', statement_from: '', statement_to: '' }
    if (fileInput.value) fileInput.value.value = ''
  } catch (e) { importError.value = e.response?.data?.message ?? 'Import failed.' } finally { importing.value = false }
}

function fmt(n) { return 'K ' + Number(n ?? 0).toLocaleString() }

onMounted(() => fetchStatements())
</script>
