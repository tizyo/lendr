<template>
  <LandlordLayout :title="tenant?.name ?? 'Tenant'">
    <Link :href="route('landlord.tenants')" class="inline-block text-sm text-neutral-500 hover:text-neutral-800 mb-4">← All tenants</Link>

    <!-- Loading -->
    <div v-if="loading" class="flex justify-center py-16">
      <div class="w-8 h-8 border-4 border-primary-500 border-t-transparent rounded-full animate-spin"></div>
    </div>

    <div v-else-if="loadError" class="p-4 bg-red-50 border border-red-200 rounded-xl text-sm text-red-700">{{ loadError }}</div>

    <div v-else class="space-y-6">
      <!-- Identity -->
      <div class="flex flex-wrap items-center gap-2">
        <span :class="planBadge(tenant.plan)" class="inline-block px-2 py-0.5 rounded text-xs font-semibold capitalize">{{ tenant.plan }}</span>
        <span :class="statusBadge(tenant.status)" class="inline-block px-2 py-0.5 rounded text-xs font-semibold capitalize">{{ tenant.status }}</span>
        <span class="text-xs text-neutral-400 font-mono">{{ tenant.slug }}</span>
      </div>

      <!-- Stats -->
      <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="bg-white rounded-xl border border-neutral-200 p-4">
          <p class="text-xs font-semibold text-neutral-500 uppercase tracking-wide">Staff</p>
          <p class="text-2xl font-semibold text-neutral-900 mt-1">{{ stats.staff_active }} <span class="text-sm font-normal text-neutral-400">active of {{ stats.staff_total }}</span></p>
        </div>
        <div class="bg-white rounded-xl border border-neutral-200 p-4">
          <p class="text-xs font-semibold text-neutral-500 uppercase tracking-wide">Borrowers</p>
          <p class="text-2xl font-semibold text-neutral-900 mt-1">{{ stats.borrowers.toLocaleString() }}</p>
        </div>
        <div class="bg-white rounded-xl border border-neutral-200 p-4">
          <p class="text-xs font-semibold text-neutral-500 uppercase tracking-wide">Active loans</p>
          <p class="text-2xl font-semibold text-neutral-900 mt-1">{{ stats.active_loans.toLocaleString() }}</p>
        </div>
        <div class="bg-white rounded-xl border border-neutral-200 p-4">
          <p class="text-xs font-semibold text-neutral-500 uppercase tracking-wide">Outstanding</p>
          <p class="text-2xl font-semibold text-neutral-900 mt-1 truncate">{{ tenant.currency }} {{ formatMoney(stats.outstanding_balance) }}</p>
        </div>
      </div>

      <!-- Details -->
      <div class="bg-white rounded-xl border border-neutral-200 p-6">
        <h3 class="font-semibold text-neutral-900 mb-4">Workspace</h3>
        <dl class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-x-6 gap-y-4 text-sm">
          <div>
            <dt class="text-xs text-neutral-500">Staff login</dt>
            <dd class="text-neutral-900 break-all">{{ tenant.login_url }}</dd>
          </div>
          <div>
            <dt class="text-xs text-neutral-500">Domain</dt>
            <dd class="text-neutral-900 break-all">{{ tenant.domains.join(', ') || 'Shared portal' }}</dd>
          </div>
          <div>
            <dt class="text-xs text-neutral-500">Admin email</dt>
            <dd class="text-neutral-900 break-all">{{ tenant.admin_email ?? '—' }}</dd>
          </div>
          <div>
            <dt class="text-xs text-neutral-500">Currency / timezone</dt>
            <dd class="text-neutral-900">{{ tenant.currency }} · {{ tenant.timezone }}</dd>
          </div>
          <div>
            <dt class="text-xs text-neutral-500">Created</dt>
            <dd class="text-neutral-900">{{ formatDate(tenant.created_at) }}</dd>
          </div>
          <div v-if="tenant.trial_ends_at">
            <dt class="text-xs text-neutral-500">Trial ends</dt>
            <dd class="text-neutral-900">{{ formatDate(tenant.trial_ends_at) }}</dd>
          </div>
        </dl>
      </div>

      <!-- Notice -->
      <div v-if="notice" class="p-3 bg-emerald-50 border border-emerald-200 rounded-lg text-sm text-emerald-700">{{ notice }}</div>

      <!-- Users -->
      <div class="bg-white rounded-xl border border-neutral-200 overflow-hidden">
        <div class="px-6 py-4 border-b border-neutral-200 flex items-center justify-between gap-3">
          <h3 class="font-semibold text-neutral-900">Users</h3>
          <button @click="openAdd" class="bg-primary-600 hover:bg-primary-700 text-white text-sm font-semibold px-4 py-2 rounded-lg whitespace-nowrap">
            + Add User
          </button>
        </div>
        <div class="overflow-x-auto">
          <table class="min-w-full text-sm">
            <thead>
              <tr class="bg-neutral-50 border-b border-neutral-200">
                <th class="px-4 py-3 text-left text-xs font-semibold text-neutral-500 uppercase tracking-wide">Name</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-neutral-500 uppercase tracking-wide">Role</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-neutral-500 uppercase tracking-wide">Status</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-neutral-500 uppercase tracking-wide">2FA</th>
                <th class="px-4 py-3 text-right text-xs font-semibold text-neutral-500 uppercase tracking-wide">Actions</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-neutral-100">
              <tr v-if="users.length === 0">
                <td colspan="5" class="px-4 py-10 text-center text-neutral-400">No users yet.</td>
              </tr>
              <tr v-for="u in users" :key="u.id" class="hover:bg-neutral-50">
                <td class="px-4 py-3">
                  <p class="font-medium text-neutral-900">{{ u.name }}</p>
                  <p class="text-xs text-neutral-400">{{ u.email }}</p>
                </td>
                <td class="px-4 py-3 text-neutral-600">{{ u.role_label ?? '—' }}</td>
                <td class="px-4 py-3">
                  <span :class="userBadge(u.status)" class="inline-block px-2 py-0.5 rounded text-xs font-semibold capitalize">{{ u.status }}</span>
                </td>
                <td class="px-4 py-3 text-neutral-500">{{ u.two_factor ? 'On' : 'Off' }}</td>
                <td class="px-4 py-3 text-right space-x-2 whitespace-nowrap">
                  <template v-if="u.status === 'active'">
                    <button @click="openImpersonate(u)" class="text-xs text-indigo-600 hover:underline font-medium">Sign in as</button>
                    <button @click="openUserAction(u, 'suspend')" class="text-xs text-amber-600 hover:underline font-medium">Suspend</button>
                  </template>
                  <button v-else-if="u.status === 'suspended'" @click="openUserAction(u, 'activate')" class="text-xs text-emerald-600 hover:underline font-medium">Reactivate</button>
                  <button v-else @click="resendInvite(u)" :disabled="resending === u.id" class="text-xs text-blue-600 hover:underline font-medium disabled:opacity-40">
                    {{ resending === u.id ? 'Sending…' : 'Resend invite' }}
                  </button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Support access history -->
      <div class="bg-white rounded-xl border border-neutral-200 overflow-hidden">
        <div class="px-6 py-4 border-b border-neutral-200">
          <h3 class="font-semibold text-neutral-900">Support access history</h3>
          <p class="text-xs text-neutral-500 mt-0.5">The last 20 "sign in as" sessions for this workspace.</p>
        </div>
        <div class="overflow-x-auto">
          <table class="min-w-full text-sm">
            <thead>
              <tr class="bg-neutral-50 border-b border-neutral-200">
                <th class="px-4 py-3 text-left text-xs font-semibold text-neutral-500 uppercase tracking-wide">When</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-neutral-500 uppercase tracking-wide">By</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-neutral-500 uppercase tracking-wide">Signed in as</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-neutral-500 uppercase tracking-wide">Reason</th>
                <th class="px-4 py-3 text-right text-xs font-semibold text-neutral-500 uppercase tracking-wide">Status</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-neutral-100">
              <tr v-if="sessions.length === 0">
                <td colspan="5" class="px-4 py-10 text-center text-neutral-400">No support sessions yet.</td>
              </tr>
              <tr v-for="s in sessions" :key="s.id">
                <td class="px-4 py-3 text-neutral-500 whitespace-nowrap">{{ formatDateTime(s.started_at ?? s.created_at) }}</td>
                <td class="px-4 py-3 text-neutral-600">{{ s.landlord_email }}</td>
                <td class="px-4 py-3 text-neutral-600">{{ s.target_name }}</td>
                <td class="px-4 py-3 text-neutral-600 min-w-48">{{ s.reason }}</td>
                <td class="px-4 py-3 text-right whitespace-nowrap">
                  <button v-if="s.status === 'active'" @click="endSession(s)" class="text-xs text-red-600 hover:underline font-medium mr-2">End now</button>
                  <span :class="sessionBadge(s.status)" class="inline-block px-2 py-0.5 rounded text-xs font-semibold capitalize">{{ s.status }}</span>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- Add User Modal -->
    <div v-if="showAdd" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4">
      <div class="bg-white rounded-xl shadow-xl w-full max-w-md">
        <div class="px-6 py-4 border-b border-neutral-200 flex items-center justify-between">
          <h3 class="font-semibold text-neutral-900">Add User</h3>
          <button @click="showAdd = false" class="text-neutral-400 hover:text-neutral-600">✕</button>
        </div>
        <div class="p-6 space-y-4">
          <p class="text-sm text-neutral-600">They'll get an email invitation to set their own password.</p>
          <div>
            <label class="block text-sm font-medium text-neutral-700 mb-1">Full Name <span class="text-red-500">*</span></label>
            <input v-model="addForm.name" type="text" class="w-full border border-neutral-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary-500" />
          </div>
          <div>
            <label class="block text-sm font-medium text-neutral-700 mb-1">Email <span class="text-red-500">*</span></label>
            <input v-model="addForm.email" type="email" class="w-full border border-neutral-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary-500" />
          </div>
          <div>
            <label class="block text-sm font-medium text-neutral-700 mb-1">Phone</label>
            <input v-model="addForm.phone" type="text" class="w-full border border-neutral-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary-500" />
          </div>
          <div>
            <label class="block text-sm font-medium text-neutral-700 mb-1">Role <span class="text-red-500">*</span></label>
            <select v-model="addForm.role" class="w-full border border-neutral-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary-500">
              <option v-for="r in roles" :key="r.value" :value="r.value">{{ r.label }}</option>
            </select>
          </div>
          <p v-if="addError" class="text-sm text-red-600">{{ addError }}</p>
        </div>
        <div class="px-6 py-4 border-t border-neutral-200 flex justify-end gap-3">
          <button @click="showAdd = false" class="px-4 py-2 text-sm text-neutral-600 hover:text-neutral-800">Cancel</button>
          <button @click="submitAdd" :disabled="adding" class="px-4 py-2 text-sm bg-primary-600 hover:bg-primary-700 text-white rounded-lg font-semibold disabled:opacity-50">
            {{ adding ? 'Sending…' : 'Send Invitation' }}
          </button>
        </div>
      </div>
    </div>

    <!-- Suspend / Reactivate Modal -->
    <div v-if="actionTarget" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4">
      <div class="bg-white rounded-xl shadow-xl w-full max-w-sm p-6">
        <h3 class="font-semibold text-neutral-900 mb-2">{{ userAction === 'suspend' ? 'Suspend' : 'Reactivate' }} {{ actionTarget.name }}?</h3>
        <p class="text-sm text-neutral-600 mb-4">
          <template v-if="userAction === 'suspend'">They'll be signed out straight away and won't be able to log in until reactivated.</template>
          <template v-else>They'll be able to log in again with their existing password.</template>
        </p>
        <p v-if="actionError" class="text-sm text-red-600 mb-4">{{ actionError }}</p>
        <div class="flex justify-end gap-3">
          <button @click="actionTarget = null" class="px-4 py-2 text-sm text-neutral-600 hover:text-neutral-800">Cancel</button>
          <button
            @click="executeUserAction"
            :disabled="actioning"
            :class="userAction === 'suspend' ? 'bg-amber-500 hover:bg-amber-600' : 'bg-emerald-600 hover:bg-emerald-700'"
            class="px-4 py-2 text-sm text-white rounded-lg font-semibold disabled:opacity-50"
          >
            {{ actioning ? 'Please wait…' : userAction === 'suspend' ? 'Suspend' : 'Reactivate' }}
          </button>
        </div>
      </div>
    </div>

    <!-- Sign In As Modal -->
    <div v-if="impersonateTarget" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4">
      <div class="bg-white rounded-xl shadow-xl w-full max-w-md">
        <div class="px-6 py-4 border-b border-neutral-200 flex items-center justify-between">
          <h3 class="font-semibold text-neutral-900">Sign in as {{ impersonateTarget.name }}</h3>
          <button @click="impersonateTarget = null" class="text-neutral-400 hover:text-neutral-600">✕</button>
        </div>
        <div class="p-6 space-y-4">
          <p class="text-sm text-neutral-600">
            Opens {{ tenant.name }}'s workspace in a new tab with this user's access, for up to 30 minutes.
            Anything you do there is real and is recorded against both you and this user.
          </p>
          <div>
            <label class="block text-sm font-medium text-neutral-700 mb-1">Reason <span class="text-red-500">*</span></label>
            <textarea
              v-model="impersonateReason"
              rows="3"
              placeholder="e.g. Ticket #142: can't see the disbursement screen."
              class="w-full border border-neutral-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-primary-500 resize-none"
            ></textarea>
            <p class="text-xs text-neutral-400 mt-1">This workspace's admins can read this in their audit log.</p>
          </div>
          <p v-if="impersonateError" class="text-sm text-red-600">{{ impersonateError }}</p>
          <a v-if="impersonateUrl" :href="impersonateUrl" target="_blank" rel="noopener noreferrer" class="block text-sm text-primary-600 hover:underline">
            Your browser blocked the new tab. Click here to open the session (link works for 60 seconds).
          </a>
        </div>
        <div class="px-6 py-4 border-t border-neutral-200 flex justify-end gap-3">
          <button @click="impersonateTarget = null" class="px-4 py-2 text-sm text-neutral-600 hover:text-neutral-800">Cancel</button>
          <button @click="startImpersonation" :disabled="impersonating || impersonateReason.trim().length < 5" class="px-4 py-2 text-sm bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg font-semibold disabled:opacity-50">
            {{ impersonating ? 'Starting…' : 'Start Session' }}
          </button>
        </div>
      </div>
    </div>
  </LandlordLayout>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue'
import { Link, router } from '@inertiajs/vue3'
import axios from 'axios'
import LandlordLayout from '@/landlord/components/LandlordLayout.vue'
import { useLandlordAuth } from '@/landlord/stores/auth.js'

const props = defineProps({
  tenantId: { type: String, required: true },
})

const auth = useLandlordAuth()
const api  = `/api/v1/landlord/tenants/${props.tenantId}`

// ─── State ───────────────────────────────────────────────────────────────────
const loading   = ref(true)
const loadError = ref('')
const notice    = ref('')
const tenant    = ref(null)
const stats     = ref(null)
const roles     = ref([])
const users     = ref([])
const sessions  = ref([])
const resending = ref(null)

// Add user
const showAdd  = ref(false)
const adding   = ref(false)
const addError = ref('')
const addForm  = reactive({ name: '', email: '', phone: '', role: 'loan_officer' })

// Suspend / reactivate
const actionTarget = ref(null)
const userAction   = ref('')
const actioning    = ref(false)
const actionError  = ref('')

// Sign in as
const impersonateTarget = ref(null)
const impersonateReason = ref('')
const impersonateError  = ref('')
const impersonateUrl    = ref('')
const impersonating     = ref(false)

// ─── Lifecycle ────────────────────────────────────────────────────────────────
onMounted(async () => {
  if (!auth.isAuthenticated) {
    router.visit(route('landlord.login'))
    return
  }
  try {
    await Promise.all([fetchOverview(), fetchUsers(), fetchSessions()])
  } catch (e) {
    if (e.response?.status === 401) {
      auth.clearAuth()
      router.visit(route('landlord.login'))
      return
    }
    loadError.value = e.response?.data?.message ?? 'Failed to load this tenant.'
  } finally {
    loading.value = false
  }
})

// ─── Data ─────────────────────────────────────────────────────────────────────
async function fetchOverview() {
  const { data } = await axios.get(`${api}/overview`)
  tenant.value = data.data.tenant
  stats.value  = data.data.stats
  roles.value  = data.data.roles
}

async function fetchUsers() {
  const { data } = await axios.get(`${api}/users`)
  users.value = data.data
}

async function fetchSessions() {
  const { data } = await axios.get(`${api}/impersonations`)
  sessions.value = data.data
}

// ─── Add user ─────────────────────────────────────────────────────────────────
function openAdd() {
  Object.assign(addForm, { name: '', email: '', phone: '', role: 'loan_officer' })
  addError.value = ''
  showAdd.value  = true
}

async function submitAdd() {
  adding.value   = true
  addError.value = ''
  try {
    const { data } = await axios.post(`${api}/users`, addForm)
    showAdd.value = false
    notice.value  = data.message
    await Promise.all([fetchUsers(), fetchOverview()])
  } catch (e) {
    addError.value = e.response?.data?.message ?? 'Failed to add this user.'
  } finally {
    adding.value = false
  }
}

async function resendInvite(user) {
  resending.value = user.id
  try {
    const { data } = await axios.post(`${api}/users/${user.id}/resend-invite`)
    notice.value = data.message
  } catch (e) {
    notice.value = e.response?.data?.message ?? 'Failed to resend the invitation.'
  } finally {
    resending.value = null
  }
}

// ─── Suspend / reactivate ─────────────────────────────────────────────────────
function openUserAction(user, action) {
  actionTarget.value = user
  userAction.value   = action
  actionError.value  = ''
}

async function executeUserAction() {
  actioning.value   = true
  actionError.value = ''
  try {
    const { data } = await axios.post(`${api}/users/${actionTarget.value.id}/${userAction.value}`)
    actionTarget.value = null
    notice.value       = data.message
    await Promise.all([fetchUsers(), fetchOverview()])
  } catch (e) {
    actionError.value = e.response?.data?.message ?? 'That did not work. Please try again.'
  } finally {
    actioning.value = false
  }
}

// ─── Sign in as ───────────────────────────────────────────────────────────────
function openImpersonate(user) {
  impersonateTarget.value = user
  impersonateReason.value = ''
  impersonateError.value  = ''
  impersonateUrl.value    = ''
}

async function startImpersonation() {
  impersonating.value    = true
  impersonateError.value = ''
  impersonateUrl.value   = ''

  // Opened before the request: browsers block tabs opened after an await.
  const tab = window.open('', '_blank')

  try {
    const { data } = await axios.post(`${api}/users/${impersonateTarget.value.id}/impersonate`, {
      reason: impersonateReason.value,
    })

    if (tab) {
      tab.opener = null
      tab.location.href = data.data.url
      impersonateTarget.value = null
    } else {
      impersonateUrl.value = data.data.url
    }
    await fetchSessions()
  } catch (e) {
    tab?.close()
    impersonateError.value = e.response?.data?.message ?? 'Failed to start the session.'
  } finally {
    impersonating.value = false
  }
}

async function endSession(session) {
  try {
    const { data } = await axios.post(`/api/v1/landlord/impersonations/${session.id}/end`)
    notice.value = data.message
    await fetchSessions()
  } catch (e) {
    notice.value = e.response?.data?.message ?? 'Failed to end the session.'
  }
}

// ─── Helpers ──────────────────────────────────────────────────────────────────
function formatDate(iso) {
  if (!iso) return '—'
  return new Date(iso).toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' })
}

function formatDateTime(iso) {
  if (!iso) return '—'
  return new Date(iso).toLocaleString('en-GB', { day: '2-digit', month: 'short', hour: '2-digit', minute: '2-digit' })
}

function formatMoney(amount) {
  return Number(amount).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
}

function statusBadge(status) {
  return {
    active:    'bg-emerald-100 text-emerald-800',
    trial:     'bg-amber-100 text-amber-800',
    suspended: 'bg-red-100 text-red-800',
    expired:   'bg-orange-100 text-orange-800',
    cancelled: 'bg-neutral-100 text-neutral-600',
  }[status] ?? 'bg-neutral-100 text-neutral-600'
}

function planBadge(plan) {
  return {
    starter:    'bg-blue-100 text-blue-800',
    growth:     'bg-violet-100 text-violet-800',
    enterprise: 'bg-neutral-800 text-white',
  }[plan] ?? 'bg-neutral-100 text-neutral-600'
}

function userBadge(status) {
  return {
    active:    'bg-emerald-100 text-emerald-800',
    invited:   'bg-blue-100 text-blue-800',
    suspended: 'bg-red-100 text-red-800',
  }[status] ?? 'bg-neutral-100 text-neutral-600'
}

function sessionBadge(status) {
  return {
    active:  'bg-red-100 text-red-800',
    pending: 'bg-amber-100 text-amber-800',
  }[status] ?? 'bg-neutral-100 text-neutral-600'
}
</script>
