<template>
  <PwaLayout title="My Profile" :show-back="true">
    <div class="px-4 py-6 space-y-5 max-w-lg mx-auto">

      <div v-if="loading" class="flex justify-center py-12">
        <svg class="w-8 h-8 animate-spin text-emerald-500" fill="none" viewBox="0 0 24 24">
          <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
          <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"/>
        </svg>
      </div>

      <template v-else>

        <!-- Avatar + name + KYC status -->
        <div class="flex flex-col items-center gap-3 py-4">
          <div class="w-20 h-20 rounded-full bg-emerald-100 flex items-center justify-center text-3xl font-bold text-emerald-700">
            {{ initials }}
          </div>
          <div class="text-center">
            <p class="text-lg font-bold text-gray-900">{{ profile.full_name }}</p>
            <p class="text-sm text-gray-400 mt-0.5">{{ profile.borrower_number }}</p>
            <span v-if="profile.kyc_verified" class="mt-1 inline-flex items-center gap-1 text-xs px-2.5 py-1 rounded-full bg-emerald-100 text-emerald-700 font-medium">
              <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
              </svg>
              KYC Verified
            </span>
            <span v-else class="mt-1 inline-flex text-xs px-2.5 py-1 rounded-full bg-amber-100 text-amber-700 font-medium">
              KYC Pending
            </span>
          </div>
        </div>

        <!-- Credit Score card -->
        <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-4">
          <div class="flex items-center justify-between mb-3">
            <p class="text-sm font-semibold text-gray-800">Credit Score</p>
            <span v-if="creditScore.score" class="text-xs font-mono text-gray-400">
              Updated {{ creditScore.updated_at ?? 'recently' }}
            </span>
          </div>
          <div v-if="loadingScore" class="h-16 flex items-center justify-center">
            <svg class="w-5 h-5 animate-spin text-emerald-400" fill="none" viewBox="0 0 24 24">
              <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
              <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"/>
            </svg>
          </div>
          <div v-else-if="!creditScore.score" class="text-sm text-gray-400 italic text-center py-2">
            No score yet — complete KYC and make a loan payment to build your score.
          </div>
          <div v-else>
            <!-- Score number + band -->
            <div class="flex items-end gap-3 mb-3">
              <span class="text-4xl font-black" :class="scoreColor(creditScore.band)">{{ creditScore.score }}</span>
              <div class="mb-1">
                <span class="text-xs font-semibold uppercase tracking-wide px-2 py-0.5 rounded-full" :class="scoreBadge(creditScore.band)">
                  {{ creditScore.band }}
                </span>
                <p class="text-xs text-gray-400 mt-0.5">out of 850</p>
              </div>
            </div>
            <!-- Score bar -->
            <div class="h-2.5 w-full bg-gray-100 rounded-full overflow-hidden">
              <div
                class="h-full rounded-full transition-all duration-700"
                :class="scoreBarColor(creditScore.band)"
                :style="{ width: Math.min(100, Math.round(((creditScore.score - 300) / 550) * 100)) + '%' }"
              />
            </div>
            <div class="flex justify-between text-xs text-gray-400 mt-1">
              <span>Poor (300)</span>
              <span>Excellent (850)</span>
            </div>
          </div>
        </div>

        <!-- View mode -->
        <div v-if="!editing" class="space-y-4">
          <div class="bg-white rounded-xl border border-gray-100 shadow-sm divide-y divide-gray-50">
            <div v-for="row in rows" :key="row.label" class="flex items-center justify-between px-4 py-3">
              <span class="text-sm text-gray-500">{{ row.label }}</span>
              <span class="text-sm font-medium text-gray-900">{{ row.value || '—' }}</span>
            </div>
          </div>
          <button
            @click="startEdit"
            class="w-full py-3.5 rounded-xl border border-emerald-500 text-emerald-700 font-semibold text-sm hover:bg-emerald-50 transition"
          >
            Edit Profile
          </button>
        </div>

        <!-- Edit form -->
        <div v-else class="space-y-4">
          <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-5 space-y-4">
            <p class="text-sm font-semibold text-gray-800 mb-2">Personal Details</p>

            <div class="grid grid-cols-2 gap-3">
              <div>
                <label class="block text-xs font-medium text-gray-500 mb-1">First Name</label>
                <input v-model="editForm.first_name" type="text" class="field" />
              </div>
              <div>
                <label class="block text-xs font-medium text-gray-500 mb-1">Last Name</label>
                <input v-model="editForm.last_name" type="text" class="field" />
              </div>
            </div>

            <div>
              <label class="block text-xs font-medium text-gray-500 mb-1">Email</label>
              <input v-model="editForm.email" type="email" class="field" />
            </div>

            <div class="grid grid-cols-2 gap-3">
              <div>
                <label class="block text-xs font-medium text-gray-500 mb-1">Gender</label>
                <select v-model="editForm.gender" class="field">
                  <option value="">— select —</option>
                  <option value="male">Male</option>
                  <option value="female">Female</option>
                  <option value="other">Other</option>
                </select>
              </div>
              <div>
                <label class="block text-xs font-medium text-gray-500 mb-1">Date of Birth</label>
                <input v-model="editForm.date_of_birth" type="date" class="field" />
              </div>
            </div>

            <div>
              <label class="block text-xs font-medium text-gray-500 mb-1">Address</label>
              <input v-model="editForm.address" type="text" class="field" />
            </div>

            <div class="grid grid-cols-2 gap-3">
              <div>
                <label class="block text-xs font-medium text-gray-500 mb-1">City</label>
                <input v-model="editForm.city" type="text" class="field" />
              </div>
              <div>
                <label class="block text-xs font-medium text-gray-500 mb-1">Province</label>
                <input v-model="editForm.province" type="text" class="field" />
              </div>
            </div>

            <p class="text-sm font-semibold text-gray-800 mt-2 pt-2 border-t border-gray-100">Employment</p>

            <div class="grid grid-cols-2 gap-3">
              <div>
                <label class="block text-xs font-medium text-gray-500 mb-1">Occupation</label>
                <input v-model="editForm.occupation" type="text" class="field" />
              </div>
              <div>
                <label class="block text-xs font-medium text-gray-500 mb-1">Employer</label>
                <input v-model="editForm.employer" type="text" class="field" />
              </div>
            </div>

            <p class="text-sm font-semibold text-gray-800 mt-2 pt-2 border-t border-gray-100">Next of Kin</p>

            <div>
              <label class="block text-xs font-medium text-gray-500 mb-1">Full Name</label>
              <input v-model="editForm.next_of_kin_name" type="text" class="field" />
            </div>
            <div class="grid grid-cols-2 gap-3">
              <div>
                <label class="block text-xs font-medium text-gray-500 mb-1">Phone</label>
                <input v-model="editForm.next_of_kin_phone" type="tel" class="field" />
              </div>
              <div>
                <label class="block text-xs font-medium text-gray-500 mb-1">Relationship</label>
                <input v-model="editForm.next_of_kin_relationship" type="text" class="field" placeholder="e.g. Spouse" />
              </div>
            </div>
          </div>

          <!-- Edit errors -->
          <div v-if="editError" class="bg-red-50 border border-red-200 rounded-xl p-3 text-sm text-red-700">
            {{ editError }}
          </div>

          <div class="flex gap-3">
            <button
              @click="editing = false"
              class="flex-1 py-3.5 rounded-xl border border-gray-200 text-gray-600 font-medium text-sm hover:bg-gray-50 transition"
            >
              Cancel
            </button>
            <button
              @click="saveProfile"
              :disabled="saving"
              class="flex-1 py-3.5 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-semibold text-sm transition disabled:opacity-50 flex items-center justify-center gap-2"
            >
              <svg v-if="saving" class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"/>
              </svg>
              {{ saving ? 'Saving…' : 'Save Changes' }}
            </button>
          </div>
        </div>

        <!-- Static actions -->
        <div class="space-y-3">
          <button
            v-if="!profile.kyc_verified"
            @click="$inertia.visit(route('pwa.kyc.onboarding'))"
            class="w-full py-3.5 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-semibold text-sm transition"
          >
            Complete KYC Verification
          </button>
          <button
            @click="logout"
            :disabled="loggingOut"
            class="w-full py-3.5 rounded-xl border border-gray-200 text-gray-700 font-medium text-sm hover:bg-gray-50 transition disabled:opacity-50"
          >
            {{ loggingOut ? 'Logging out…' : 'Log Out' }}
          </button>
        </div>
      </template>
    </div>
  </PwaLayout>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue'
import { router } from '@inertiajs/vue3'
import axios from 'axios'
import PwaLayout from '@/pwa/components/PwaLayout.vue'
import { usePwaAuthStore } from '@/pwa/stores/auth.js'

const auth = usePwaAuthStore()

const profile     = ref({})
const creditScore = ref({})
const loading     = ref(false)
const loadingScore = ref(false)
const loggingOut  = ref(false)
const editing     = ref(false)
const saving      = ref(false)
const editError   = ref('')

const editForm = reactive({
  first_name: '', last_name: '', email: '',
  gender: '', date_of_birth: '', address: '', city: '', province: '',
  occupation: '', employer: '',
  next_of_kin_name: '', next_of_kin_phone: '', next_of_kin_relationship: '',
})

const initials = computed(() => {
  const name = profile.value.full_name ?? ''
  return name.split(' ').slice(0, 2).map(w => w[0]).join('').toUpperCase() || '?'
})

const rows = computed(() => [
  { label: 'Phone',           value: profile.value.phone },
  { label: 'Email',           value: profile.value.email },
  { label: 'Active Loans',    value: String(profile.value.active_loans_count ?? 0) },
  { label: 'Total Borrowed',  value: profile.value.total_borrowed ? `K ${profile.value.total_borrowed}` : null },
  { label: 'Outstanding',     value: profile.value.outstanding_balance ? `K ${profile.value.outstanding_balance}` : null },
])

function scoreColor(band) {
  return { poor: 'text-red-500', fair: 'text-amber-500', good: 'text-blue-500', excellent: 'text-emerald-500' }[band] ?? 'text-gray-400'
}
function scoreBadge(band) {
  return {
    poor:      'bg-red-100 text-red-700',
    fair:      'bg-amber-100 text-amber-700',
    good:      'bg-blue-100 text-blue-700',
    excellent: 'bg-emerald-100 text-emerald-700',
  }[band] ?? 'bg-gray-100 text-gray-500'
}
function scoreBarColor(band) {
  return { poor: 'bg-red-400', fair: 'bg-amber-400', good: 'bg-blue-400', excellent: 'bg-emerald-500' }[band] ?? 'bg-gray-300'
}

function startEdit() {
  Object.assign(editForm, {
    first_name:                profile.value.first_name ?? '',
    last_name:                 profile.value.last_name  ?? '',
    email:                     profile.value.email ?? '',
    gender:                    profile.value.gender ?? '',
    date_of_birth:             profile.value.date_of_birth ?? '',
    address:                   profile.value.address ?? '',
    city:                      profile.value.city ?? '',
    province:                  profile.value.province ?? '',
    occupation:                profile.value.occupation ?? '',
    employer:                  profile.value.employer ?? '',
    next_of_kin_name:          profile.value.next_of_kin_name ?? '',
    next_of_kin_phone:         profile.value.next_of_kin_phone ?? '',
    next_of_kin_relationship:  profile.value.next_of_kin_relationship ?? '',
  })
  editError.value = ''
  editing.value = true
}

async function saveProfile() {
  saving.value = true
  editError.value = ''
  try {
    const res = await axios.put('/api/v1/me/profile', editForm)
    // Re-fetch profile to get updated full_name
    const meRes = await axios.get('/api/v1/me')
    profile.value = meRes.data.data ?? {}
    auth.setAuth(auth.token, profile.value)
    editing.value = false
  } catch (e) {
    editError.value = e.response?.data?.message ?? 'Failed to save. Please try again.'
  } finally {
    saving.value = false
  }
}

onMounted(async () => {
  loading.value = true
  loadingScore.value = true
  try {
    const [meRes, scoreRes] = await Promise.all([
      axios.get('/api/v1/me'),
      axios.get('/api/v1/me/credit-score'),
    ])
    profile.value     = meRes.data.data ?? {}
    creditScore.value = scoreRes.data.data ?? {}
  } catch {
    auth.clearAuth()
    router.visit(route('pwa.auth.login'))
  } finally {
    loading.value      = false
    loadingScore.value = false
  }
})

async function logout() {
  loggingOut.value = true
  try {
    await axios.post('/api/v1/auth/logout')
  } finally {
    auth.clearAuth()
    router.visit(route('pwa.auth.login'))
  }
}
</script>

<style scoped>
@reference "../../../css/app.css";
.field {
  @apply w-full px-3 py-2.5 border border-gray-300 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-emerald-400;
}
</style>
