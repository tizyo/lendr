<template>
  <AppLayout title="Loan Types">
    <div class="space-y-6">

      <!-- Header -->
      <div class="flex items-center justify-between">
        <div>
          <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Loan Types &amp; Plans</h1>
          <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
            Configure loan products — types define requirements, plans define rates and terms.
          </p>
        </div>
        <button @click="openTypeDrawer(null)" class="btn-primary flex items-center gap-2">
          <PlusIcon class="h-4 w-4" />
          Add Loan Type
        </button>
      </div>

      <!-- Flash messages -->
      <div v-if="flash.success" class="rounded-md bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 px-4 py-3 text-sm text-green-800 dark:text-green-300">
        {{ flash.success }}
      </div>
      <div v-if="flash.error" class="rounded-md bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 px-4 py-3 text-sm text-red-800 dark:text-red-300">
        {{ flash.error }}
      </div>

      <!-- Types list -->
      <div v-if="types.length === 0" class="rounded-lg border border-dashed border-gray-300 dark:border-gray-600 p-10 text-center">
        <TagIcon class="mx-auto h-10 w-10 text-gray-400" />
        <p class="mt-3 text-sm text-gray-500 dark:text-gray-400">No loan types yet. Add one to get started.</p>
      </div>

      <div v-else class="space-y-4">
        <div
          v-for="type in localTypes"
          :key="type.id"
          class="rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 shadow-sm overflow-hidden"
        >
          <!-- Type header row -->
          <div class="flex items-center justify-between px-5 py-4">
            <div class="flex items-center gap-3 min-w-0">
              <button
                @click="toggleExpand(type.id)"
                class="flex-shrink-0 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 transition-colors"
              >
                <ChevronDownIcon v-if="expanded.has(type.id)" class="h-5 w-5 transition-transform duration-200" />
                <ChevronRightIcon v-else class="h-5 w-5 transition-transform duration-200" />
              </button>
              <div class="min-w-0">
                <div class="flex items-center gap-2 flex-wrap">
                  <span class="font-semibold text-gray-900 dark:text-white">{{ type.name }}</span>
                  <span class="font-mono text-xs text-gray-500 dark:text-gray-400 bg-gray-100 dark:bg-gray-700 px-1.5 py-0.5 rounded">{{ type.code }}</span>
                  <span
                    :class="type.is_active ? 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400' : 'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-400'"
                    class="text-xs px-2 py-0.5 rounded-full font-medium"
                  >{{ type.is_active ? 'Active' : 'Inactive' }}</span>
                </div>
                <p v-if="type.description" class="mt-0.5 text-xs text-gray-500 dark:text-gray-400 truncate max-w-xl">{{ type.description }}</p>
                <div class="mt-1 flex items-center gap-3 text-xs text-gray-400 dark:text-gray-500">
                  <span v-if="type.requires_collateral" class="text-amber-600 dark:text-amber-400">Requires collateral</span>
                  <span v-if="type.requires_guarantor" class="text-amber-600 dark:text-amber-400">Requires guarantor</span>
                  <span>{{ type.plans.length }} plan{{ type.plans.length !== 1 ? 's' : '' }}</span>
                </div>
              </div>
            </div>
            <div class="flex items-center gap-2 flex-shrink-0 ml-4">
              <button @click="openPlanDrawer(null, type)" class="btn-secondary text-xs flex items-center gap-1">
                <PlusIcon class="h-3.5 w-3.5" />
                Add Plan
              </button>
              <button @click="openTypeDrawer(type)" class="p-1.5 text-gray-400 hover:text-blue-600 dark:hover:text-blue-400 transition-colors rounded">
                <PencilIcon class="h-4 w-4" />
              </button>
              <button @click="confirmDeleteType(type)" class="p-1.5 text-gray-400 hover:text-red-600 dark:hover:text-red-400 transition-colors rounded">
                <TrashIcon class="h-4 w-4" />
              </button>
            </div>
          </div>

          <!-- Expanded plans sub-list -->
          <Transition name="slide-down">
            <div v-if="expanded.has(type.id)" class="border-t border-gray-100 dark:border-gray-700">
              <div v-if="type.plans.length === 0" class="px-8 py-5 text-sm text-gray-400 dark:text-gray-500 italic">
                No plans yet for this loan type.
              </div>
              <table v-else class="w-full text-sm">
                <thead>
                  <tr class="bg-gray-50 dark:bg-gray-700">
                    <th class="px-5 py-2.5 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Plan</th>
                    <th class="px-4 py-2.5 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Rate</th>
                    <th class="px-4 py-2.5 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Tenure</th>
                    <th class="px-4 py-2.5 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Amount</th>
                    <th class="px-4 py-2.5 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Schedule</th>
                    <th class="px-4 py-2.5 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Status</th>
                    <th class="px-4 py-2.5 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Actions</th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                  <tr
                    v-for="plan in type.plans"
                    :key="plan.id"
                    class="hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors"
                  >
                    <td class="px-5 py-3">
                      <div class="font-medium text-gray-900 dark:text-white">{{ plan.name }}</div>
                      <div class="text-xs font-mono text-gray-400">{{ plan.code }}</div>
                    </td>
                    <td class="px-4 py-3 text-gray-700 dark:text-gray-300">
                      {{ plan.interest_rate }}% /
                      <span class="text-xs text-gray-500">{{ plan.interest_period }}</span>
                      <div class="text-xs text-gray-400">{{ interestTypeLabel(plan.interest_type) }}</div>
                    </td>
                    <td class="px-4 py-3 text-gray-700 dark:text-gray-300">
                      {{ plan.min_tenure }}–{{ plan.max_tenure }} {{ plan.tenure_type }}
                    </td>
                    <td class="px-4 py-3 text-gray-700 dark:text-gray-300">
                      {{ fmtAmount(plan.min_amount) }} – {{ fmtAmount(plan.max_amount) }}
                    </td>
                    <td class="px-4 py-3 text-gray-700 dark:text-gray-300 capitalize">
                      {{ plan.repayment_schedule?.replace('_', ' ') }}
                    </td>
                    <td class="px-4 py-3">
                      <span
                        :class="plan.is_active ? 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400' : 'bg-gray-100 text-gray-500 dark:bg-gray-700 dark:text-gray-400'"
                        class="text-xs px-2 py-0.5 rounded-full font-medium"
                      >{{ plan.is_active ? 'Active' : 'Inactive' }}</span>
                    </td>
                    <td class="px-4 py-3 text-right">
                      <div class="flex items-center justify-end gap-1">
                        <button @click="openPlanDrawer(plan, type)" class="p-1.5 text-gray-400 hover:text-blue-600 dark:hover:text-blue-400 transition-colors rounded">
                          <PencilIcon class="h-3.5 w-3.5" />
                        </button>
                        <button @click="confirmDeletePlan(plan, type)" class="p-1.5 text-gray-400 hover:text-red-600 dark:hover:text-red-400 transition-colors rounded">
                          <TrashIcon class="h-3.5 w-3.5" />
                        </button>
                      </div>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
          </Transition>
        </div>
      </div>
    </div>

    <!-- ── Loan Type Drawer ───────────────────────────────────────────────── -->
    <Teleport to="body">
      <Transition name="fade">
        <div v-if="typeDrawer.open" class="fixed inset-0 z-40 bg-black/40" @click="typeDrawer.open = false" />
      </Transition>
      <Transition name="slide-right">
        <div v-if="typeDrawer.open" class="fixed inset-y-0 right-0 z-50 w-full max-w-lg bg-white dark:bg-gray-900 shadow-xl flex flex-col">
          <div class="flex items-center justify-between border-b border-gray-200 dark:border-gray-700 px-6 py-4">
            <h2 class="text-lg font-semibold text-gray-900 dark:text-white">
              {{ typeDrawer.editing ? 'Edit Loan Type' : 'New Loan Type' }}
            </h2>
            <button @click="typeDrawer.open = false" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
              <XMarkIcon class="h-5 w-5" />
            </button>
          </div>
          <div class="flex-1 overflow-y-auto px-6 py-5 space-y-4">
            <!-- Name & Code -->
            <div class="grid grid-cols-2 gap-4">
              <div>
                <label class="form-label">Name <span class="text-red-500">*</span></label>
                <input v-model="typeForm.name" type="text" class="form-input" placeholder="Personal Loan" />
                <p v-if="typeErrors.name" class="form-error">{{ typeErrors.name }}</p>
              </div>
              <div>
                <label class="form-label">Code <span class="text-red-500">*</span></label>
                <input v-model="typeForm.code" type="text" class="form-input font-mono" placeholder="PERSONAL" />
                <p v-if="typeErrors.code" class="form-error">{{ typeErrors.code }}</p>
              </div>
            </div>
            <!-- Description -->
            <div>
              <label class="form-label">Description</label>
              <textarea v-model="typeForm.description" rows="2" class="form-input" placeholder="Optional description…" />
            </div>
            <!-- Sort Order -->
            <div>
              <label class="form-label">Sort Order</label>
              <input v-model.number="typeForm.sort_order" type="number" min="0" class="form-input w-28" />
            </div>
            <!-- Required Documents -->
            <div>
              <label class="form-label">Required Documents</label>
              <div class="flex gap-2 mb-2">
                <input v-model="newDoc" @keydown.enter.prevent="addDoc" type="text" class="form-input flex-1" placeholder="e.g. National ID" />
                <button @click="addDoc" type="button" class="btn-secondary text-sm">Add</button>
              </div>
              <div class="flex flex-wrap gap-2">
                <span
                  v-for="(doc, i) in typeForm.required_documents"
                  :key="i"
                  class="inline-flex items-center gap-1 text-xs bg-blue-100 dark:bg-blue-900/30 text-blue-700 dark:text-blue-300 px-2 py-1 rounded-full"
                >
                  {{ doc }}
                  <button @click="removeDoc(i)" class="text-blue-400 hover:text-blue-600">
                    <XMarkIcon class="h-3 w-3" />
                  </button>
                </span>
              </div>
            </div>
            <!-- Flags -->
            <div class="space-y-2">
              <label class="flex items-center gap-2 cursor-pointer">
                <input v-model="typeForm.requires_collateral" type="checkbox" class="form-checkbox" />
                <span class="text-sm text-gray-700 dark:text-gray-300">Requires collateral</span>
              </label>
              <label class="flex items-center gap-2 cursor-pointer">
                <input v-model="typeForm.requires_guarantor" type="checkbox" class="form-checkbox" />
                <span class="text-sm text-gray-700 dark:text-gray-300">Requires guarantor</span>
              </label>
              <label class="flex items-center gap-2 cursor-pointer">
                <input v-model="typeForm.is_active" type="checkbox" class="form-checkbox" />
                <span class="text-sm text-gray-700 dark:text-gray-300">Active</span>
              </label>
            </div>
          </div>
          <div class="border-t border-gray-200 dark:border-gray-700 px-6 py-4 flex justify-end gap-3">
            <button @click="typeDrawer.open = false" class="btn-secondary">Cancel</button>
            <button @click="saveType" :disabled="typeDrawer.saving" class="btn-primary flex items-center gap-2">
              <ArrowPathIcon v-if="typeDrawer.saving" class="h-4 w-4 animate-spin" />
              {{ typeDrawer.editing ? 'Save Changes' : 'Create Type' }}
            </button>
          </div>
        </div>
      </Transition>
    </Teleport>

    <!-- ── Loan Plan Drawer ───────────────────────────────────────────────── -->
    <Teleport to="body">
      <Transition name="fade">
        <div v-if="planDrawer.open" class="fixed inset-0 z-40 bg-black/40" @click="planDrawer.open = false" />
      </Transition>
      <Transition name="slide-right">
        <div v-if="planDrawer.open" class="fixed inset-y-0 right-0 z-50 w-full max-w-lg bg-white dark:bg-gray-900 shadow-xl flex flex-col">
          <div class="flex items-center justify-between border-b border-gray-200 dark:border-gray-700 px-6 py-4">
            <h2 class="text-lg font-semibold text-gray-900 dark:text-white">
              {{ planDrawer.editing ? 'Edit Plan' : 'New Plan' }}
              <span v-if="planDrawer.typeName" class="text-sm text-gray-400 font-normal ml-1">— {{ planDrawer.typeName }}</span>
            </h2>
            <button @click="planDrawer.open = false" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
              <XMarkIcon class="h-5 w-5" />
            </button>
          </div>
          <div class="flex-1 overflow-y-auto px-6 py-5 space-y-4">
            <!-- Loan Type selector -->
            <div>
              <label class="form-label">Loan Type <span class="text-red-500">*</span></label>
              <select v-model.number="planForm.loan_type_id" class="form-input">
                <option value="">Select loan type…</option>
                <option v-for="t in localTypes" :key="t.id" :value="t.id">{{ t.name }}</option>
              </select>
              <p v-if="planErrors.loan_type_id" class="form-error">{{ planErrors.loan_type_id }}</p>
            </div>
            <!-- Name & Code -->
            <div class="grid grid-cols-2 gap-4">
              <div>
                <label class="form-label">Plan Name <span class="text-red-500">*</span></label>
                <input v-model="planForm.name" type="text" class="form-input" placeholder="12-Month Standard" />
                <p v-if="planErrors.name" class="form-error">{{ planErrors.name }}</p>
              </div>
              <div>
                <label class="form-label">Code <span class="text-red-500">*</span></label>
                <input v-model="planForm.code" type="text" class="form-input font-mono" placeholder="PERS-12M" />
                <p v-if="planErrors.code" class="form-error">{{ planErrors.code }}</p>
              </div>
            </div>
            <!-- Interest -->
            <div class="grid grid-cols-3 gap-4">
              <div>
                <label class="form-label">Interest Rate % <span class="text-red-500">*</span></label>
                <input v-model.number="planForm.interest_rate" type="number" step="0.01" min="0" class="form-input" />
                <p v-if="planErrors.interest_rate" class="form-error">{{ planErrors.interest_rate }}</p>
              </div>
              <div>
                <label class="form-label">Interest Type</label>
                <select v-model="planForm.interest_type" class="form-input">
                  <option value="flat">Flat</option>
                  <option value="reducing_balance">Reducing Balance</option>
                  <option value="compound">Compound</option>
                </select>
              </div>
              <div>
                <label class="form-label">Per</label>
                <select v-model="planForm.interest_period" class="form-input">
                  <option value="daily">Day</option>
                  <option value="weekly">Week</option>
                  <option value="monthly">Month</option>
                  <option value="annually">Year</option>
                </select>
              </div>
            </div>
            <!-- Tenure -->
            <div class="grid grid-cols-3 gap-4">
              <div>
                <label class="form-label">Min Tenure <span class="text-red-500">*</span></label>
                <input v-model.number="planForm.min_tenure" type="number" min="1" class="form-input" />
                <p v-if="planErrors.min_tenure" class="form-error">{{ planErrors.min_tenure }}</p>
              </div>
              <div>
                <label class="form-label">Max Tenure <span class="text-red-500">*</span></label>
                <input v-model.number="planForm.max_tenure" type="number" min="1" class="form-input" />
                <p v-if="planErrors.max_tenure" class="form-error">{{ planErrors.max_tenure }}</p>
              </div>
              <div>
                <label class="form-label">Tenure Unit</label>
                <select v-model="planForm.tenure_type" class="form-input">
                  <option value="days">Days</option>
                  <option value="weeks">Weeks</option>
                  <option value="months">Months</option>
                </select>
              </div>
            </div>
            <!-- Amount range -->
            <div class="grid grid-cols-2 gap-4">
              <div>
                <label class="form-label">Min Amount <span class="text-red-500">*</span></label>
                <input v-model.number="planForm.min_amount" type="number" step="0.01" min="0" class="form-input" />
                <p v-if="planErrors.min_amount" class="form-error">{{ planErrors.min_amount }}</p>
              </div>
              <div>
                <label class="form-label">Max Amount <span class="text-red-500">*</span></label>
                <input v-model.number="planForm.max_amount" type="number" step="0.01" min="0" class="form-input" />
                <p v-if="planErrors.max_amount" class="form-error">{{ planErrors.max_amount }}</p>
              </div>
            </div>
            <!-- Repayment Schedule -->
            <div>
              <label class="form-label">Repayment Schedule</label>
              <select v-model="planForm.repayment_schedule" class="form-input">
                <option value="daily">Daily</option>
                <option value="weekly">Weekly</option>
                <option value="bi_weekly">Bi-weekly</option>
                <option value="monthly">Monthly</option>
                <option value="bullet">Bullet (end of term)</option>
              </select>
            </div>
            <!-- Penalty -->
            <div class="grid grid-cols-2 gap-4">
              <div>
                <label class="form-label">Penalty Rate %</label>
                <input v-model.number="planForm.penalty_rate" type="number" step="0.01" min="0" class="form-input" />
              </div>
              <div>
                <label class="form-label">Penalty Type</label>
                <select v-model="planForm.penalty_type" class="form-input">
                  <option value="flat">Flat</option>
                  <option value="percentage">Percentage</option>
                </select>
              </div>
            </div>
            <!-- Fees & Grace -->
            <div class="grid grid-cols-3 gap-4">
              <div>
                <label class="form-label">Processing Fee</label>
                <input v-model.number="planForm.processing_fee" type="number" step="0.01" min="0" class="form-input" />
              </div>
              <div>
                <label class="form-label">Insurance Fee</label>
                <input v-model.number="planForm.insurance_fee" type="number" step="0.01" min="0" class="form-input" />
              </div>
              <div>
                <label class="form-label">Grace Period (days)</label>
                <input v-model.number="planForm.grace_period_days" type="number" min="0" class="form-input" />
              </div>
            </div>
            <!-- Active -->
            <label class="flex items-center gap-2 cursor-pointer">
              <input v-model="planForm.is_active" type="checkbox" class="form-checkbox" />
              <span class="text-sm text-gray-700 dark:text-gray-300">Active</span>
            </label>
          </div>
          <div class="border-t border-gray-200 dark:border-gray-700 px-6 py-4 flex justify-end gap-3">
            <button @click="planDrawer.open = false" class="btn-secondary">Cancel</button>
            <button @click="savePlan" :disabled="planDrawer.saving" class="btn-primary flex items-center gap-2">
              <ArrowPathIcon v-if="planDrawer.saving" class="h-4 w-4 animate-spin" />
              {{ planDrawer.editing ? 'Save Changes' : 'Create Plan' }}
            </button>
          </div>
        </div>
      </Transition>
    </Teleport>

    <!-- ── Confirm Delete Modal ──────────────────────────────────────────── -->
    <Teleport to="body">
      <Transition name="fade">
        <div v-if="deleteModal.open" class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
          <div class="bg-white dark:bg-gray-800 rounded-xl shadow-xl max-w-sm w-full p-6 space-y-4">
            <div class="flex items-start gap-3">
              <div class="flex-shrink-0 rounded-full bg-red-100 dark:bg-red-900/30 p-2">
                <ExclamationTriangleIcon class="h-5 w-5 text-red-600 dark:text-red-400" />
              </div>
              <div>
                <h3 class="font-semibold text-gray-900 dark:text-white">{{ deleteModal.title }}</h3>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ deleteModal.message }}</p>
              </div>
            </div>
            <div class="flex justify-end gap-3">
              <button @click="deleteModal.open = false" class="btn-secondary">Cancel</button>
              <button @click="executeDelete" :disabled="deleteModal.deleting" class="btn-danger flex items-center gap-2">
                <ArrowPathIcon v-if="deleteModal.deleting" class="h-4 w-4 animate-spin" />
                Delete
              </button>
            </div>
          </div>
        </div>
      </Transition>
    </Teleport>
  </AppLayout>
</template>

<script setup>
import { ref, reactive } from 'vue'
import AppLayout from '@/admin/components/layout/AppLayout.vue'
import {
  PlusIcon, PencilIcon, TrashIcon, TagIcon, XMarkIcon,
  ChevronDownIcon, ChevronRightIcon, ArrowPathIcon, ExclamationTriangleIcon,
} from '@heroicons/vue/24/outline'
import axios from 'axios'
import { usePage } from '@inertiajs/vue3'

const props = defineProps({
  types: { type: Array, default: () => [] },
})

const page   = usePage()
const flash  = reactive({ success: '', error: '' })

// ─── Local state (avoid Inertia reload for CRUD) ─────────────────────────────
const localTypes = ref(props.types.map(t => ({ ...t, plans: [...t.plans] })))

// ─── Expand / collapse ───────────────────────────────────────────────────────
const expanded = ref(new Set())
function toggleExpand(id) {
  if (expanded.value.has(id)) expanded.value.delete(id)
  else expanded.value.add(id)
}

// ─── Helpers ─────────────────────────────────────────────────────────────────
function fmtAmount(n) {
  return new Intl.NumberFormat('en-ZM', { style: 'currency', currency: 'ZMW', maximumFractionDigits: 0 }).format(n)
}
function interestTypeLabel(v) {
  return { flat: 'Flat rate', reducing_balance: 'Reducing balance', compound: 'Compound' }[v] ?? v
}
function showFlash(type, msg) {
  flash[type] = msg
  setTimeout(() => { flash[type] = '' }, 4000)
}

// ─── Type Drawer ─────────────────────────────────────────────────────────────
const typeDrawer = reactive({ open: false, editing: false, editingId: null, saving: false })
const typeErrors = reactive({})
const typeForm   = reactive({
  name: '', code: '', description: '', requires_collateral: false,
  requires_guarantor: false, required_documents: [], is_active: true, sort_order: 0,
})
const newDoc = ref('')

function openTypeDrawer(type) {
  Object.assign(typeErrors, { name: '', code: '' })
  if (type) {
    Object.assign(typeForm, {
      name: type.name, code: type.code, description: type.description ?? '',
      requires_collateral: type.requires_collateral, requires_guarantor: type.requires_guarantor,
      required_documents: [...(type.required_documents ?? [])],
      is_active: type.is_active, sort_order: type.sort_order ?? 0,
    })
    typeDrawer.editing   = true
    typeDrawer.editingId = type.id
  } else {
    Object.assign(typeForm, {
      name: '', code: '', description: '', requires_collateral: false,
      requires_guarantor: false, required_documents: [], is_active: true, sort_order: 0,
    })
    typeDrawer.editing   = false
    typeDrawer.editingId = null
  }
  newDoc.value     = ''
  typeDrawer.open  = true
}

function addDoc() {
  const d = newDoc.value.trim()
  if (d && !typeForm.required_documents.includes(d)) typeForm.required_documents.push(d)
  newDoc.value = ''
}
function removeDoc(i) { typeForm.required_documents.splice(i, 1) }

async function saveType() {
  typeDrawer.saving = true
  Object.assign(typeErrors, { name: '', code: '' })
  try {
    const payload = { ...typeForm }
    let res
    if (typeDrawer.editing) {
      res = await axios.put(`/api/v1/loan-types/${typeDrawer.editingId}`, payload)
    } else {
      res = await axios.post('/api/v1/loan-types', payload)
    }
    const saved = res.data.data
    if (typeDrawer.editing) {
      const idx = localTypes.value.findIndex(t => t.id === typeDrawer.editingId)
      if (idx !== -1) {
        localTypes.value[idx] = { ...localTypes.value[idx], ...saved, plans: localTypes.value[idx].plans }
      }
      showFlash('success', 'Loan type updated.')
    } else {
      localTypes.value.push({ ...saved, plans: [] })
      showFlash('success', 'Loan type created.')
    }
    typeDrawer.open = false
  } catch (e) {
    if (e.response?.status === 422) {
      const errs = e.response.data.errors ?? {}
      Object.assign(typeErrors, errs)
    } else {
      showFlash('error', e.response?.data?.message ?? 'An error occurred.')
    }
  } finally {
    typeDrawer.saving = false
  }
}

// ─── Plan Drawer ─────────────────────────────────────────────────────────────
const planDrawer  = reactive({ open: false, editing: false, editingId: null, saving: false, typeId: null, typeName: '' })
const planErrors  = reactive({})
const planForm    = reactive({
  loan_type_id: '', name: '', code: '', interest_rate: 0,
  interest_type: 'flat', interest_period: 'monthly',
  min_tenure: 1, max_tenure: 12, tenure_type: 'months',
  min_amount: 0, max_amount: 0, repayment_schedule: 'monthly',
  penalty_rate: 0, penalty_type: 'percentage', grace_period_days: 0,
  processing_fee: 0, insurance_fee: 0, is_active: true,
})

function openPlanDrawer(plan, type) {
  Object.keys(planErrors).forEach(k => { planErrors[k] = '' })
  planDrawer.typeId   = type.id
  planDrawer.typeName = type.name
  if (plan) {
    Object.assign(planForm, {
      loan_type_id: plan.loan_type_id ?? type.id,
      name: plan.name, code: plan.code,
      interest_rate: plan.interest_rate, interest_type: plan.interest_type,
      interest_period: plan.interest_period, min_tenure: plan.min_tenure,
      max_tenure: plan.max_tenure, tenure_type: plan.tenure_type,
      min_amount: plan.min_amount, max_amount: plan.max_amount,
      repayment_schedule: plan.repayment_schedule, penalty_rate: plan.penalty_rate,
      penalty_type: plan.penalty_type, grace_period_days: plan.grace_period_days,
      processing_fee: plan.processing_fee, insurance_fee: plan.insurance_fee,
      is_active: plan.is_active,
    })
    planDrawer.editing   = true
    planDrawer.editingId = plan.id
  } else {
    Object.assign(planForm, {
      loan_type_id: type.id, name: '', code: '', interest_rate: 0,
      interest_type: 'flat', interest_period: 'monthly',
      min_tenure: 1, max_tenure: 12, tenure_type: 'months',
      min_amount: 0, max_amount: 0, repayment_schedule: 'monthly',
      penalty_rate: 0, penalty_type: 'percentage', grace_period_days: 0,
      processing_fee: 0, insurance_fee: 0, is_active: true,
    })
    planDrawer.editing   = false
    planDrawer.editingId = null
  }
  planDrawer.open = true
}

async function savePlan() {
  planDrawer.saving = true
  Object.keys(planErrors).forEach(k => { planErrors[k] = '' })
  try {
    let res
    if (planDrawer.editing) {
      res = await axios.put(`/api/v1/loan-plans/${planDrawer.editingId}`, planForm)
    } else {
      res = await axios.post('/api/v1/loan-plans', planForm)
    }
    const saved  = res.data.data
    const typeId = saved.loan_type_id
    const typeIdx = localTypes.value.findIndex(t => t.id === typeId)
    if (typeIdx !== -1) {
      if (planDrawer.editing) {
        const planIdx = localTypes.value[typeIdx].plans.findIndex(p => p.id === planDrawer.editingId)
        if (planIdx !== -1) localTypes.value[typeIdx].plans.splice(planIdx, 1, saved)
        showFlash('success', 'Plan updated.')
      } else {
        localTypes.value[typeIdx].plans.push(saved)
        localTypes.value[typeIdx].plans_count = localTypes.value[typeIdx].plans.length
        showFlash('success', 'Plan created.')
      }
      // Ensure type is expanded to show new/updated plan
      expanded.value.add(typeId)
    }
    planDrawer.open = false
  } catch (e) {
    if (e.response?.status === 422) {
      const errs = e.response.data.errors ?? {}
      Object.assign(planErrors, errs)
    } else {
      showFlash('error', e.response?.data?.message ?? 'An error occurred.')
    }
  } finally {
    planDrawer.saving = false
  }
}

// ─── Delete ──────────────────────────────────────────────────────────────────
const deleteModal = reactive({ open: false, title: '', message: '', deleting: false, fn: null })

function confirmDeleteType(type) {
  deleteModal.title   = `Delete "${type.name}"?`
  deleteModal.message = type.plans_count > 0
    ? `This will also delete all ${type.plans_count} plan(s). This cannot be undone.`
    : 'This cannot be undone.'
  deleteModal.fn      = async () => {
    await axios.delete(`/api/v1/loan-types/${type.id}`)
    localTypes.value = localTypes.value.filter(t => t.id !== type.id)
    showFlash('success', 'Loan type deleted.')
  }
  deleteModal.open = true
}

function confirmDeletePlan(plan, type) {
  deleteModal.title   = `Delete plan "${plan.name}"?`
  deleteModal.message = 'This cannot be undone.'
  deleteModal.fn      = async () => {
    await axios.delete(`/api/v1/loan-plans/${plan.id}`)
    const typeIdx = localTypes.value.findIndex(t => t.id === type.id)
    if (typeIdx !== -1) {
      localTypes.value[typeIdx].plans = localTypes.value[typeIdx].plans.filter(p => p.id !== plan.id)
      localTypes.value[typeIdx].plans_count = localTypes.value[typeIdx].plans.length
    }
    showFlash('success', 'Plan deleted.')
  }
  deleteModal.open = true
}

async function executeDelete() {
  deleteModal.deleting = true
  try {
    await deleteModal.fn()
    deleteModal.open = false
  } catch (e) {
    showFlash('error', e.response?.data?.message ?? 'Delete failed.')
    deleteModal.open = false
  } finally {
    deleteModal.deleting = false
  }
}
</script>

<style scoped>
@reference "../../../../css/app.css";
.btn-primary {
  @apply inline-flex items-center px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium rounded-lg transition-colors disabled:opacity-60 disabled:cursor-not-allowed;
}
.btn-secondary {
  @apply inline-flex items-center px-3 py-2 border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-800 hover:bg-gray-50 dark:hover:bg-gray-700 text-sm font-medium rounded-lg transition-colors;
}
.btn-danger {
  @apply inline-flex items-center px-4 py-2 bg-red-600 hover:bg-red-700 text-white text-sm font-medium rounded-lg transition-colors disabled:opacity-60 disabled:cursor-not-allowed;
}
.form-label {
  @apply block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1;
}
.form-input {
  @apply w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-900 dark:text-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent;
}
.form-checkbox {
  @apply rounded border-gray-300 dark:border-gray-600 text-blue-600 focus:ring-blue-500;
}
.form-error {
  @apply mt-1 text-xs text-red-600 dark:text-red-400;
}

/* transitions */
.fade-enter-active, .fade-leave-active { transition: opacity 0.2s ease; }
.fade-enter-from, .fade-leave-to { opacity: 0; }

.slide-right-enter-active, .slide-right-leave-active { transition: transform 0.3s ease; }
.slide-right-enter-from, .slide-right-leave-to { transform: translateX(100%); }

.slide-down-enter-active, .slide-down-leave-active { transition: all 0.2s ease; overflow: hidden; }
.slide-down-enter-from, .slide-down-leave-to { max-height: 0; opacity: 0; }
.slide-down-enter-to, .slide-down-leave-from { max-height: 2000px; opacity: 1; }
</style>
