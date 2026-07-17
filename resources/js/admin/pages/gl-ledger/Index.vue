<template>
  <div class="max-w-6xl mx-auto py-8 px-4 space-y-8">
    <div class="flex items-center justify-between">
      <h1 class="text-2xl font-bold">General Ledger</h1>
      <div class="flex gap-3">
        <button @click="seedAccounts" :disabled="seeding" class="text-sm border rounded px-4 py-2 hover:bg-gray-50 disabled:opacity-50">
          {{ seeding ? 'Seeding…' : 'Seed Default Accounts' }}
        </button>
        <button @click="activeTab = 'entries'" :class="tabClass('entries')">Journal Entries</button>
        <button @click="activeTab = 'accounts'" :class="tabClass('accounts')">Chart of Accounts</button>
        <button @click="loadTrialBalance(); activeTab = 'trial-balance'" :class="tabClass('trial-balance')">Trial Balance</button>
      </div>
    </div>

    <!-- Journal Entries Tab -->
    <div v-if="activeTab === 'entries'" class="space-y-4">
      <!-- Filters -->
      <div class="flex gap-3 items-end flex-wrap">
        <div>
          <label class="block text-xs font-medium mb-1">Account Code</label>
          <input v-model="filters.account_code" @keyup.enter="loadEntries" placeholder="e.g. 1100" class="border rounded px-3 py-2 text-sm w-32" />
        </div>
        <div>
          <label class="block text-xs font-medium mb-1">From</label>
          <input v-model="filters.date_from" type="date" class="border rounded px-3 py-2 text-sm" />
        </div>
        <div>
          <label class="block text-xs font-medium mb-1">To</label>
          <input v-model="filters.date_to" type="date" class="border rounded px-3 py-2 text-sm" />
        </div>
        <button @click="loadEntries" class="px-4 py-2 text-sm bg-indigo-600 text-white rounded hover:bg-indigo-700">Filter</button>
        <button @click="openEntryModal" class="px-4 py-2 text-sm border border-indigo-600 text-indigo-600 rounded hover:bg-indigo-50">+ New Entry</button>
      </div>

      <div v-if="entriesLoading" class="text-gray-500 text-sm">Loading…</div>
      <div v-else class="border rounded-lg overflow-hidden">
        <table class="w-full text-sm">
          <thead class="bg-gray-50 text-xs uppercase tracking-wide text-gray-500">
            <tr>
              <th class="px-4 py-3 text-left">Reference</th>
              <th class="px-4 py-3 text-left">Date</th>
              <th class="px-4 py-3 text-left">Description</th>
              <th class="px-4 py-3 text-right">Debits</th>
              <th class="px-4 py-3 text-right">Credits</th>
              <th class="px-4 py-3 text-left">Lines</th>
            </tr>
          </thead>
          <tbody class="divide-y">
            <tr v-for="entry in entries.data" :key="entry.id" class="hover:bg-gray-50 cursor-pointer" @click="expandedEntry = expandedEntry === entry.id ? null : entry.id">
              <td class="px-4 py-3 font-mono text-xs text-indigo-700">{{ entry.reference }}</td>
              <td class="px-4 py-3 text-gray-600">{{ entry.entry_date }}</td>
              <td class="px-4 py-3">{{ entry.description }}</td>
              <td class="px-4 py-3 text-right font-mono">{{ formatAmount(sumSide(entry.lines, 'debit')) }}</td>
              <td class="px-4 py-3 text-right font-mono">{{ formatAmount(sumSide(entry.lines, 'credit')) }}</td>
              <td class="px-4 py-3 text-gray-400">{{ entry.lines.length }} lines</td>
            </tr>
            <!-- Expanded lines -->
            <template v-for="entry in entries.data" :key="`exp-${entry.id}`">
              <tr v-if="expandedEntry === entry.id">
                <td colspan="6" class="bg-gray-50 px-8 pb-3">
                  <table class="w-full text-xs mt-2">
                    <thead>
                      <tr class="text-gray-500">
                        <th class="text-left py-1 w-24">Code</th>
                        <th class="text-left py-1">Account</th>
                        <th class="text-right py-1 w-32">Debit</th>
                        <th class="text-right py-1 w-32">Credit</th>
                      </tr>
                    </thead>
                    <tbody>
                      <tr v-for="line in entry.lines" :key="line.account_code" class="border-t">
                        <td class="py-1 font-mono text-indigo-600">{{ line.account_code }}</td>
                        <td class="py-1">{{ line.account_name }}</td>
                        <td class="py-1 text-right font-mono">{{ line.side === 'debit' ? formatAmount(line.amount) : '' }}</td>
                        <td class="py-1 text-right font-mono">{{ line.side === 'credit' ? formatAmount(line.amount) : '' }}</td>
                      </tr>
                    </tbody>
                  </table>
                </td>
              </tr>
            </template>
          </tbody>
        </table>
        <div v-if="!entries.data?.length" class="text-center py-8 text-gray-400 text-sm">No journal entries found.</div>
      </div>

      <!-- Pagination -->
      <div v-if="entries.last_page > 1" class="flex gap-2 justify-end text-sm">
        <button v-for="page in entries.last_page" :key="page" @click="loadEntries(page)"
          :class="page === entries.current_page ? 'bg-indigo-600 text-white' : 'border hover:bg-gray-50'"
          class="px-3 py-1 rounded">{{ page }}</button>
      </div>
    </div>

    <!-- Chart of Accounts Tab -->
    <div v-if="activeTab === 'accounts'" class="space-y-4">
      <div class="flex justify-end">
        <button @click="openAccountModal" class="text-sm px-4 py-2 border border-indigo-600 text-indigo-600 rounded hover:bg-indigo-50">+ New Account</button>
      </div>
      <div v-if="accountsLoading" class="text-gray-500 text-sm">Loading…</div>
      <div v-else class="border rounded-lg overflow-hidden">
        <table class="w-full text-sm">
          <thead class="bg-gray-50 text-xs uppercase tracking-wide text-gray-500">
            <tr>
              <th class="px-4 py-3 text-left">Code</th>
              <th class="px-4 py-3 text-left">Name</th>
              <th class="px-4 py-3 text-left">Type</th>
              <th class="px-4 py-3 text-right">Balance</th>
              <th class="px-4 py-3 text-center">Active</th>
            </tr>
          </thead>
          <tbody class="divide-y">
            <tr v-for="account in accounts" :key="account.id" class="hover:bg-gray-50">
              <td class="px-4 py-3 font-mono text-indigo-700">{{ account.code }}</td>
              <td class="px-4 py-3">{{ account.name }}</td>
              <td class="px-4 py-3 capitalize text-gray-600">{{ account.type }}</td>
              <td class="px-4 py-3 text-right font-mono" :class="account.balance < 0 ? 'text-red-600' : 'text-gray-900'">
                {{ formatAmount(account.balance) }}
              </td>
              <td class="px-4 py-3 text-center">
                <span v-if="account.is_active" class="text-green-600 text-xs">Yes</span>
                <span v-else class="text-gray-400 text-xs">No</span>
              </td>
            </tr>
          </tbody>
        </table>
        <div v-if="!accounts.length" class="text-center py-8 text-gray-400 text-sm">No accounts. Click "Seed Default Accounts" to start.</div>
      </div>
    </div>

    <!-- Trial Balance Tab -->
    <div v-if="activeTab === 'trial-balance'" class="space-y-4">
      <div v-if="tbLoading" class="text-gray-500 text-sm">Loading…</div>
      <div v-else>
        <div class="border rounded-lg overflow-hidden">
          <table class="w-full text-sm">
            <thead class="bg-gray-50 text-xs uppercase tracking-wide text-gray-500">
              <tr>
                <th class="px-4 py-3 text-left">Code</th>
                <th class="px-4 py-3 text-left">Account</th>
                <th class="px-4 py-3 text-left">Type</th>
                <th class="px-4 py-3 text-right">Debit Balance</th>
                <th class="px-4 py-3 text-right">Credit Balance</th>
              </tr>
            </thead>
            <tbody class="divide-y">
              <tr v-for="row in trialBalance.accounts" :key="row.code" class="hover:bg-gray-50">
                <td class="px-4 py-3 font-mono text-indigo-700">{{ row.code }}</td>
                <td class="px-4 py-3">{{ row.name }}</td>
                <td class="px-4 py-3 capitalize text-gray-600">{{ row.type }}</td>
                <td class="px-4 py-3 text-right font-mono">
                  {{ ['asset', 'expense'].includes(row.type) ? formatAmount(row.balance) : '' }}
                </td>
                <td class="px-4 py-3 text-right font-mono">
                  {{ ['liability', 'equity', 'income'].includes(row.type) ? formatAmount(row.balance) : '' }}
                </td>
              </tr>
            </tbody>
            <tfoot class="bg-gray-50 font-semibold">
              <tr>
                <td colspan="3" class="px-4 py-3 text-right">Totals</td>
                <td class="px-4 py-3 text-right font-mono">{{ formatAmount(trialBalance.total_debits) }}</td>
                <td class="px-4 py-3 text-right font-mono">{{ formatAmount(trialBalance.total_credits) }}</td>
              </tr>
            </tfoot>
          </table>
        </div>
        <p :class="trialBalance.is_balanced ? 'text-green-600' : 'text-red-600'" class="text-sm font-medium mt-2">
          {{ trialBalance.is_balanced ? '✓ Trial balance is balanced.' : '✗ Trial balance is NOT balanced.' }}
        </p>
      </div>
    </div>

    <!-- New Account Modal -->
    <div v-if="accountModal.open" class="fixed inset-0 bg-black/40 flex items-center justify-center z-50">
      <div class="bg-white rounded-xl shadow-lg w-full max-w-md p-6 space-y-4">
        <h2 class="text-lg font-semibold">New GL Account</h2>
        <div>
          <label class="block text-sm font-medium mb-1">Code <span class="text-red-500">*</span></label>
          <input v-model="accountModal.form.code" type="text" placeholder="e.g. 1005" class="w-full border rounded px-3 py-2 text-sm" />
        </div>
        <div>
          <label class="block text-sm font-medium mb-1">Name <span class="text-red-500">*</span></label>
          <input v-model="accountModal.form.name" type="text" placeholder="e.g. Petty Cash" class="w-full border rounded px-3 py-2 text-sm" />
        </div>
        <div>
          <label class="block text-sm font-medium mb-1">Type <span class="text-red-500">*</span></label>
          <select v-model="accountModal.form.type" class="w-full border rounded px-3 py-2 text-sm">
            <option value="">Select type…</option>
            <option value="asset">Asset</option>
            <option value="liability">Liability</option>
            <option value="equity">Equity</option>
            <option value="income">Income</option>
            <option value="expense">Expense</option>
          </select>
        </div>
        <div>
          <label class="block text-sm font-medium mb-1">Description</label>
          <input v-model="accountModal.form.description" type="text" class="w-full border rounded px-3 py-2 text-sm" />
        </div>
        <div v-if="accountModal.error" class="text-sm text-red-600">{{ accountModal.error }}</div>
        <div class="flex gap-3 justify-end pt-2">
          <button @click="accountModal.open = false" class="px-4 py-2 text-sm border rounded hover:bg-gray-50">Cancel</button>
          <button @click="saveAccount" :disabled="accountModal.saving" class="px-4 py-2 text-sm bg-indigo-600 text-white rounded hover:bg-indigo-700 disabled:opacity-50">
            {{ accountModal.saving ? 'Saving…' : 'Create Account' }}
          </button>
        </div>
      </div>
    </div>

    <!-- New Journal Entry Modal -->
    <div v-if="entryModal.open" class="fixed inset-0 bg-black/40 flex items-center justify-center z-50">
      <div class="bg-white rounded-xl shadow-lg w-full max-w-2xl p-6 space-y-4 max-h-screen overflow-y-auto">
        <h2 class="text-lg font-semibold">New Journal Entry</h2>
        <div class="grid grid-cols-2 gap-4">
          <div class="col-span-2">
            <label class="block text-sm font-medium mb-1">Description <span class="text-red-500">*</span></label>
            <input v-model="entryModal.form.description" type="text" class="w-full border rounded px-3 py-2 text-sm" />
          </div>
          <div>
            <label class="block text-sm font-medium mb-1">Entry Date <span class="text-red-500">*</span></label>
            <input v-model="entryModal.form.entry_date" type="date" class="w-full border rounded px-3 py-2 text-sm" />
          </div>
        </div>

        <!-- Lines -->
        <div>
          <div class="flex justify-between mb-2">
            <label class="text-sm font-medium">Lines <span class="text-red-500">*</span></label>
            <button @click="addLine" class="text-xs text-indigo-600 hover:underline">+ Add line</button>
          </div>
          <div v-for="(line, idx) in entryModal.form.lines" :key="idx" class="grid grid-cols-12 gap-2 mb-2 items-center">
            <select v-model="line.account_code" class="col-span-4 border rounded px-2 py-1.5 text-sm">
              <option value="">Account…</option>
              <option v-for="acc in accounts" :key="acc.code" :value="acc.code">{{ acc.code }} — {{ acc.name }}</option>
            </select>
            <select v-model="line.side" class="col-span-3 border rounded px-2 py-1.5 text-sm">
              <option value="debit">Debit</option>
              <option value="credit">Credit</option>
            </select>
            <input v-model="line.amount" type="number" step="0.01" min="0.01" placeholder="0.00" class="col-span-4 border rounded px-2 py-1.5 text-sm" />
            <button @click="removeLine(idx)" class="col-span-1 text-red-400 hover:text-red-600 text-lg leading-none">×</button>
          </div>

          <div class="text-xs text-gray-500 mt-1 flex gap-6">
            <span>Debits: <strong>{{ formatAmount(entryDebits) }}</strong></span>
            <span>Credits: <strong>{{ formatAmount(entryCredits) }}</strong></span>
            <span :class="Math.abs(entryDebits - entryCredits) < 0.01 ? 'text-green-600' : 'text-red-500'">
              {{ Math.abs(entryDebits - entryCredits) < 0.01 ? '✓ Balanced' : '✗ Not balanced' }}
            </span>
          </div>
        </div>

        <div v-if="entryModal.error" class="text-sm text-red-600">{{ entryModal.error }}</div>
        <div class="flex gap-3 justify-end pt-2">
          <button @click="entryModal.open = false" class="px-4 py-2 text-sm border rounded hover:bg-gray-50">Cancel</button>
          <button @click="saveEntry" :disabled="entryModal.saving" class="px-4 py-2 text-sm bg-indigo-600 text-white rounded hover:bg-indigo-700 disabled:opacity-50">
            {{ entryModal.saving ? 'Posting…' : 'Post Entry' }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import axios from 'axios'

const activeTab     = ref('entries')
const entries       = ref({ data: [], last_page: 1, current_page: 1 })
const accounts      = ref([])
const trialBalance  = ref({ accounts: [], total_debits: 0, total_credits: 0, is_balanced: true })
const entriesLoading = ref(false)
const accountsLoading = ref(false)
const tbLoading     = ref(false)
const seeding       = ref(false)
const expandedEntry = ref(null)

const filters = ref({ account_code: '', date_from: '', date_to: '' })

const accountModal = ref({ open: false, saving: false, error: '', form: { code: '', name: '', type: '', description: '' } })
const entryModal   = ref({
  open: false, saving: false, error: '',
  form: { description: '', entry_date: '', lines: [emptyLine(), emptyLine()] },
})

function emptyLine() { return { account_code: '', side: 'debit', amount: '' } }

const entryDebits  = computed(() => entryModal.value.form.lines.filter(l => l.side === 'debit').reduce((s, l) => s + (parseFloat(l.amount) || 0), 0))
const entryCredits = computed(() => entryModal.value.form.lines.filter(l => l.side === 'credit').reduce((s, l) => s + (parseFloat(l.amount) || 0), 0))

function tabClass(tab) {
  return activeTab.value === tab
    ? 'text-sm px-4 py-2 bg-indigo-600 text-white rounded'
    : 'text-sm px-4 py-2 border rounded hover:bg-gray-50'
}

function formatAmount(val) {
  return Number(val || 0).toLocaleString('en-ZM', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
}

function sumSide(lines, side) {
  return lines.filter(l => l.side === side).reduce((s, l) => s + l.amount, 0)
}

async function loadEntries(page = 1) {
  entriesLoading.value = true
  const params = { page, ...Object.fromEntries(Object.entries(filters.value).filter(([, v]) => v)) }
  const { data } = await axios.get('/api/v1/gl/entries', { params })
  entries.value = data.data.entries
  entriesLoading.value = false
}

async function loadAccounts() {
  accountsLoading.value = true
  const { data } = await axios.get('/api/v1/gl/accounts')
  accounts.value = data.data.accounts
  accountsLoading.value = false
}

async function loadTrialBalance() {
  tbLoading.value = true
  const { data } = await axios.get('/api/v1/gl/trial-balance')
  trialBalance.value = data.data
  tbLoading.value = false
}

async function seedAccounts() {
  seeding.value = true
  await axios.post('/api/v1/gl/seed-accounts')
  await loadAccounts()
  seeding.value = false
}

function openAccountModal() {
  accountModal.value = { open: true, saving: false, error: '', form: { code: '', name: '', type: '', description: '' } }
}

async function saveAccount() {
  accountModal.value.saving = true
  accountModal.value.error  = ''
  try {
    await axios.post('/api/v1/gl/accounts', accountModal.value.form)
    accountModal.value.open = false
    await loadAccounts()
  } catch (e) {
    accountModal.value.error   = e.response?.data?.message ?? 'Failed to save account.'
    accountModal.value.saving  = false
  }
}

function openEntryModal() {
  entryModal.value = { open: true, saving: false, error: '', form: { description: '', entry_date: '', lines: [emptyLine(), emptyLine()] } }
}

function addLine() { entryModal.value.form.lines.push(emptyLine()) }
function removeLine(idx) { entryModal.value.form.lines.splice(idx, 1) }

async function saveEntry() {
  entryModal.value.saving = true
  entryModal.value.error  = ''
  try {
    await axios.post('/api/v1/gl/entries', entryModal.value.form)
    entryModal.value.open = false
    await loadEntries()
  } catch (e) {
    entryModal.value.error   = e.response?.data?.message ?? 'Failed to post entry.'
    entryModal.value.saving  = false
  }
}

onMounted(async () => {
  await Promise.all([loadEntries(), loadAccounts()])
})
</script>
