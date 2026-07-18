<template>
  <AppLayout title="Expense Categories">
    <div class="space-y-6">

      <!-- Header -->
      <div class="flex items-center justify-between">
        <div>
          <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Expense Categories</h1>
          <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
            Manage categories, monthly budgets, and approval thresholds.
          </p>
        </div>
        <button @click="openCatDrawer(null)" class="btn-primary flex items-center gap-2">
          <PlusIcon class="h-4 w-4" />
          Add Category
        </button>
      </div>

      <!-- Flash -->
      <div v-if="flash.success" class="rounded-md bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 px-4 py-3 text-sm text-green-800 dark:text-green-300">
        {{ flash.success }}
      </div>
      <div v-if="flash.error" class="rounded-md bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 px-4 py-3 text-sm text-red-800 dark:text-red-300">
        {{ flash.error }}
      </div>

      <!-- Tabs -->
      <div class="flex gap-1 border-b border-gray-200 dark:border-gray-700">
        <button
          v-for="tab in tabs"
          :key="tab.id"
          @click="activeTab = tab.id"
          class="px-4 py-2.5 text-sm font-medium border-b-2 -mb-px transition"
          :class="activeTab === tab.id
            ? 'border-blue-600 text-blue-700 dark:border-blue-400 dark:text-blue-400'
            : 'border-transparent text-gray-500 dark:text-gray-400 hover:text-gray-800 dark:hover:text-gray-200'"
        >{{ tab.label }}</button>
      </div>

      <!-- ─── Tab: Categories ─────────────────────────────────────── -->
      <div v-if="activeTab === 'categories'">
        <div v-if="localCategories.length === 0" class="rounded-lg border border-dashed border-gray-300 dark:border-gray-600 p-10 text-center">
          <RectangleStackIcon class="mx-auto h-10 w-10 text-gray-400" />
          <p class="mt-3 text-sm text-gray-500 dark:text-gray-400">No categories yet. Add one above.</p>
        </div>

        <div v-else class="rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 overflow-hidden shadow-sm">
          <table class="w-full text-sm">
            <thead>
              <tr class="bg-gray-50 dark:bg-gray-750 border-b border-gray-200 dark:border-gray-700">
                <th class="px-5 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Category</th>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Code</th>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Description</th>
                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Expenses</th>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Status</th>
                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Actions</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
              <tr v-for="cat in localCategories" :key="cat.id" class="hover:bg-gray-50 dark:hover:bg-gray-750 transition-colors">
                <td class="px-5 py-3">
                  <div class="flex items-center gap-2">
                    <span class="w-3 h-3 rounded-full flex-shrink-0" :style="{ background: cat.colour || '#94a3b8' }" />
                    <span class="font-medium text-gray-900 dark:text-white">{{ cat.name }}</span>
                    <span v-if="cat.icon" class="text-base">{{ cat.icon }}</span>
                  </div>
                </td>
                <td class="px-4 py-3 font-mono text-xs text-gray-500 dark:text-gray-400">{{ cat.code }}</td>
                <td class="px-4 py-3 text-gray-500 dark:text-gray-400 max-w-xs truncate">{{ cat.description || '—' }}</td>
                <td class="px-4 py-3 text-right text-gray-700 dark:text-gray-300">{{ cat.expenses_count }}</td>
                <td class="px-4 py-3">
                  <span
                    :class="cat.is_active ? 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400' : 'bg-gray-100 text-gray-500 dark:bg-gray-700 dark:text-gray-400'"
                    class="text-xs px-2 py-0.5 rounded-full font-medium"
                  >{{ cat.is_active ? 'Active' : 'Inactive' }}</span>
                </td>
                <td class="px-4 py-3 text-right">
                  <div class="flex items-center justify-end gap-1">
                    <button @click="openCatDrawer(cat)" class="p-1.5 text-gray-400 hover:text-blue-600 dark:hover:text-blue-400 transition-colors rounded">
                      <PencilIcon class="h-4 w-4" />
                    </button>
                    <button @click="confirmDelete(cat)" class="p-1.5 text-gray-400 hover:text-red-600 dark:hover:text-red-400 transition-colors rounded">
                      <TrashIcon class="h-4 w-4" />
                    </button>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- ─── Tab: Budgets ────────────────────────────────────────── -->
      <div v-if="activeTab === 'budgets'" class="space-y-4">
        <div class="flex items-center justify-between">
          <p class="text-sm text-gray-600 dark:text-gray-400">
            Monthly budget allocations for <strong>{{ currentYear }}</strong>.
          </p>
          <button @click="openBudgetDrawer" class="btn-secondary text-sm flex items-center gap-1.5">
            <PlusIcon class="h-4 w-4" />
            Set Budget
          </button>
        </div>

        <div v-if="localBudgets.length === 0" class="rounded-lg border border-dashed border-gray-300 dark:border-gray-600 p-10 text-center">
          <p class="text-sm text-gray-500 dark:text-gray-400">No budgets set for {{ currentYear }}.</p>
        </div>

        <div v-else class="rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 overflow-hidden shadow-sm">
          <table class="w-full text-sm">
            <thead>
              <tr class="bg-gray-50 dark:bg-gray-750 border-b border-gray-200 dark:border-gray-700">
                <th class="px-5 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Category</th>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Period</th>
                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Budget Amount</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
              <tr v-for="b in localBudgets" :key="b.id" class="hover:bg-gray-50 dark:hover:bg-gray-750 transition-colors">
                <td class="px-5 py-3 font-medium text-gray-900 dark:text-white">{{ b.category }}</td>
                <td class="px-4 py-3 text-gray-600 dark:text-gray-300 capitalize">
                  {{ b.period === 'monthly' ? monthName(b.period_month) + ' ' + b.period_year : 'Annual ' + b.period_year }}
                </td>
                <td class="px-4 py-3 text-right font-semibold text-gray-900 dark:text-white">
                  {{ fmtAmount(b.amount) }}
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- ─── Tab: Approval Settings ──────────────────────────────── -->
      <div v-if="activeTab === 'approvals'" class="space-y-4">
        <div class="flex items-center justify-between">
          <p class="text-sm text-gray-600 dark:text-gray-400">
            Configure approval thresholds and required roles per category.
          </p>
          <button @click="openApprovalDrawer" class="btn-secondary text-sm flex items-center gap-1.5">
            <PlusIcon class="h-4 w-4" />
            Add Rule
          </button>
        </div>

        <div v-if="localApprovals.length === 0" class="rounded-lg border border-dashed border-gray-300 dark:border-gray-600 p-10 text-center">
          <p class="text-sm text-gray-500 dark:text-gray-400">No approval rules configured.</p>
        </div>

        <div v-else class="rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 overflow-hidden shadow-sm">
          <table class="w-full text-sm">
            <thead>
              <tr class="bg-gray-50 dark:bg-gray-750 border-b border-gray-200 dark:border-gray-700">
                <th class="px-5 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Category</th>
                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Threshold</th>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Approver Role</th>
                <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Receipt Required</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
              <tr v-for="s in localApprovals" :key="s.id" class="hover:bg-gray-50 dark:hover:bg-gray-750 transition-colors">
                <td class="px-5 py-3 font-medium text-gray-900 dark:text-white">{{ s.category ?? 'All categories' }}</td>
                <td class="px-4 py-3 text-right text-gray-700 dark:text-gray-300">{{ fmtAmount(s.threshold_amount) }}</td>
                <td class="px-4 py-3 text-gray-700 dark:text-gray-300 capitalize">{{ s.approver_role?.replace('_', ' ') }}</td>
                <td class="px-4 py-3 text-center">
                  <span :class="s.requires_receipt ? 'text-green-600 dark:text-green-400' : 'text-gray-400'">
                    {{ s.requires_receipt ? '✓' : '—' }}
                  </span>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- ── Category Drawer ─────────────────────────────────────────────────── -->
    <Teleport to="body">
      <Transition name="fade">
        <div v-if="catDrawer.open" class="fixed inset-0 z-40 bg-black/40" @click="catDrawer.open = false" />
      </Transition>
      <Transition name="slide-right">
        <div v-if="catDrawer.open" class="fixed inset-y-0 right-0 z-50 w-full max-w-md bg-white dark:bg-gray-900 shadow-xl flex flex-col">
          <div class="flex items-center justify-between border-b border-gray-200 dark:border-gray-700 px-6 py-4">
            <h2 class="text-lg font-semibold text-gray-900 dark:text-white">
              {{ catDrawer.editing ? 'Edit Category' : 'New Category' }}
            </h2>
            <button @click="catDrawer.open = false" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
              <XMarkIcon class="h-5 w-5" />
            </button>
          </div>
          <div class="flex-1 overflow-y-auto px-6 py-5 space-y-4">
            <div class="grid grid-cols-2 gap-4">
              <div>
                <label class="form-label">Name <span class="text-red-500">*</span></label>
                <input v-model="catForm.name" type="text" class="form-input" placeholder="Travel" />
                <p v-if="catErrors.name" class="form-error">{{ catErrors.name }}</p>
              </div>
              <div>
                <label class="form-label">Code <span class="text-red-500">*</span></label>
                <input v-model="catForm.code" type="text" class="form-input font-mono" placeholder="TRAVEL" />
                <p v-if="catErrors.code" class="form-error">{{ catErrors.code }}</p>
              </div>
            </div>
            <div>
              <label class="form-label">Description</label>
              <textarea v-model="catForm.description" rows="2" class="form-input" />
            </div>
            <div class="grid grid-cols-2 gap-4">
              <div>
                <label class="form-label">Colour</label>
                <div class="flex items-center gap-2">
                  <input type="color" v-model="catForm.colour" class="h-9 w-10 cursor-pointer rounded border border-gray-300 dark:border-gray-600" />
                  <input v-model="catForm.colour" type="text" class="form-input flex-1 font-mono text-xs" />
                </div>
              </div>
              <div>
                <label class="form-label">Icon (emoji)</label>
                <input v-model="catForm.icon" type="text" class="form-input" placeholder="✈️" maxlength="4" />
              </div>
            </div>
            <label class="flex items-center gap-2 cursor-pointer">
              <input v-model="catForm.is_active" type="checkbox" class="form-checkbox" />
              <span class="text-sm text-gray-700 dark:text-gray-300">Active</span>
            </label>
          </div>
          <div class="border-t border-gray-200 dark:border-gray-700 px-6 py-4 flex justify-end gap-3">
            <button @click="catDrawer.open = false" class="btn-secondary">Cancel</button>
            <button @click="saveCat" :disabled="catDrawer.saving" class="btn-primary flex items-center gap-2">
              <ArrowPathIcon v-if="catDrawer.saving" class="h-4 w-4 animate-spin" />
              {{ catDrawer.editing ? 'Save Changes' : 'Create' }}
            </button>
          </div>
        </div>
      </Transition>
    </Teleport>

    <!-- ── Budget Drawer ───────────────────────────────────────────────────── -->
    <Teleport to="body">
      <Transition name="fade">
        <div v-if="budgetDrawer.open" class="fixed inset-0 z-40 bg-black/40" @click="budgetDrawer.open = false" />
      </Transition>
      <Transition name="slide-right">
        <div v-if="budgetDrawer.open" class="fixed inset-y-0 right-0 z-50 w-full max-w-md bg-white dark:bg-gray-900 shadow-xl flex flex-col">
          <div class="flex items-center justify-between border-b border-gray-200 dark:border-gray-700 px-6 py-4">
            <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Set Budget</h2>
            <button @click="budgetDrawer.open = false" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
              <XMarkIcon class="h-5 w-5" />
            </button>
          </div>
          <div class="flex-1 overflow-y-auto px-6 py-5 space-y-4">
            <div>
              <label class="form-label">Category <span class="text-red-500">*</span></label>
              <select v-model.number="budgetForm.category_id" class="form-input">
                <option value="">Select category…</option>
                <option v-for="c in localCategories" :key="c.id" :value="c.id">{{ c.name }}</option>
              </select>
              <p v-if="budgetErrors.category_id" class="form-error">{{ budgetErrors.category_id }}</p>
            </div>
            <div class="grid grid-cols-2 gap-4">
              <div>
                <label class="form-label">Period</label>
                <select v-model="budgetForm.period" class="form-input">
                  <option value="monthly">Monthly</option>
                  <option value="annual">Annual</option>
                </select>
              </div>
              <div v-if="budgetForm.period === 'monthly'">
                <label class="form-label">Month</label>
                <select v-model.number="budgetForm.period_month" class="form-input">
                  <option v-for="m in 12" :key="m" :value="m">{{ monthName(m) }}</option>
                </select>
              </div>
            </div>
            <div>
              <label class="form-label">Budget Amount <span class="text-red-500">*</span></label>
              <input v-model.number="budgetForm.amount" type="number" step="0.01" min="0" class="form-input" />
              <p v-if="budgetErrors.amount" class="form-error">{{ budgetErrors.amount }}</p>
            </div>
            <div>
              <label class="form-label">Notes</label>
              <textarea v-model="budgetForm.notes" rows="2" class="form-input" />
            </div>
          </div>
          <div class="border-t border-gray-200 dark:border-gray-700 px-6 py-4 flex justify-end gap-3">
            <button @click="budgetDrawer.open = false" class="btn-secondary">Cancel</button>
            <button @click="saveBudget" :disabled="budgetDrawer.saving" class="btn-primary flex items-center gap-2">
              <ArrowPathIcon v-if="budgetDrawer.saving" class="h-4 w-4 animate-spin" />
              Save Budget
            </button>
          </div>
        </div>
      </Transition>
    </Teleport>

    <!-- ── Approval Rule Drawer ────────────────────────────────────────────── -->
    <Teleport to="body">
      <Transition name="fade">
        <div v-if="approvalDrawer.open" class="fixed inset-0 z-40 bg-black/40" @click="approvalDrawer.open = false" />
      </Transition>
      <Transition name="slide-right">
        <div v-if="approvalDrawer.open" class="fixed inset-y-0 right-0 z-50 w-full max-w-md bg-white dark:bg-gray-900 shadow-xl flex flex-col">
          <div class="flex items-center justify-between border-b border-gray-200 dark:border-gray-700 px-6 py-4">
            <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Approval Rule</h2>
            <button @click="approvalDrawer.open = false" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
              <XMarkIcon class="h-5 w-5" />
            </button>
          </div>
          <div class="flex-1 overflow-y-auto px-6 py-5 space-y-4">
            <div>
              <label class="form-label">Category (leave blank for global)</label>
              <select v-model="approvalForm.category_id" class="form-input">
                <option value="">All categories (global)</option>
                <option v-for="c in localCategories" :key="c.id" :value="c.id">{{ c.name }}</option>
              </select>
            </div>
            <div>
              <label class="form-label">Threshold Amount <span class="text-red-500">*</span></label>
              <input v-model.number="approvalForm.threshold_amount" type="number" step="0.01" min="0" class="form-input" placeholder="0 = always requires approval" />
            </div>
            <div>
              <label class="form-label">Approver Role <span class="text-red-500">*</span></label>
              <select v-model="approvalForm.approver_role" class="form-input">
                <option value="loan_officer">Loan Officer</option>
                <option value="branch_manager">Branch Manager</option>
                <option value="super_admin">Super Admin</option>
              </select>
            </div>
            <label class="flex items-center gap-2 cursor-pointer">
              <input v-model="approvalForm.requires_receipt" type="checkbox" class="form-checkbox" />
              <span class="text-sm text-gray-700 dark:text-gray-300">Require receipt attachment</span>
            </label>
          </div>
          <div class="border-t border-gray-200 dark:border-gray-700 px-6 py-4 flex justify-end gap-3">
            <button @click="approvalDrawer.open = false" class="btn-secondary">Cancel</button>
            <button @click="saveApproval" :disabled="approvalDrawer.saving" class="btn-primary flex items-center gap-2">
              <ArrowPathIcon v-if="approvalDrawer.saving" class="h-4 w-4 animate-spin" />
              Save Rule
            </button>
          </div>
        </div>
      </Transition>
    </Teleport>

    <!-- ── Delete Confirm ─────────────────────────────────────────────────── -->
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
  PlusIcon, PencilIcon, TrashIcon, XMarkIcon,
  ArrowPathIcon, ExclamationTriangleIcon, RectangleStackIcon,
} from '@heroicons/vue/24/outline'
import axios from 'axios'

const props = defineProps({
  categories:       { type: Array, default: () => [] },
  budgets:          { type: Array, default: () => [] },
  approvalSettings: { type: Array, default: () => [] },
})

const tabs = [
  { id: 'categories', label: 'Categories' },
  { id: 'budgets',    label: 'Budgets' },
  { id: 'approvals',  label: 'Approval Rules' },
]
const activeTab   = ref('categories')
const currentYear = new Date().getFullYear()

const flash           = reactive({ success: '', error: '' })
const localCategories = ref(props.categories.map(c => ({ ...c })))
const localBudgets    = ref(props.budgets.map(b => ({ ...b })))
const localApprovals  = ref(props.approvalSettings.map(s => ({ ...s })))

function showFlash(type, msg) {
  flash[type] = msg
  setTimeout(() => { flash[type] = '' }, 4000)
}

function fmtAmount(n) {
  return new Intl.NumberFormat('en-ZM', { style: 'currency', currency: 'ZMW', maximumFractionDigits: 2 }).format(n)
}
function monthName(m) {
  return new Date(2024, m - 1, 1).toLocaleString('en', { month: 'long' })
}

// ─── Category CRUD ────────────────────────────────────────────────────────────
const catDrawer = reactive({ open: false, editing: false, editingId: null, saving: false })
const catErrors = reactive({})
const catForm   = reactive({ name: '', code: '', description: '', colour: '#6366f1', icon: '', is_active: true })

function openCatDrawer(cat) {
  Object.keys(catErrors).forEach(k => { catErrors[k] = '' })
  if (cat) {
    Object.assign(catForm, { name: cat.name, code: cat.code, description: cat.description ?? '', colour: cat.colour ?? '#6366f1', icon: cat.icon ?? '', is_active: cat.is_active })
    catDrawer.editing = true
    catDrawer.editingId = cat.id
  } else {
    Object.assign(catForm, { name: '', code: '', description: '', colour: '#6366f1', icon: '', is_active: true })
    catDrawer.editing = false
    catDrawer.editingId = null
  }
  catDrawer.open = true
}

async function saveCat() {
  catDrawer.saving = true
  Object.keys(catErrors).forEach(k => { catErrors[k] = '' })
  try {
    let res
    if (catDrawer.editing) {
      res = await axios.put(`/api/v1/expense-categories/${catDrawer.editingId}`, catForm)
      const idx = localCategories.value.findIndex(c => c.id === catDrawer.editingId)
      if (idx !== -1) localCategories.value.splice(idx, 1, { ...localCategories.value[idx], ...res.data.data })
      showFlash('success', 'Category updated.')
    } else {
      res = await axios.post('/api/v1/expense-categories', catForm)
      localCategories.value.push({ ...res.data.data, expenses_count: 0 })
      showFlash('success', 'Category created.')
    }
    catDrawer.open = false
  } catch (e) {
    if (e.response?.status === 422) Object.assign(catErrors, e.response.data.errors ?? {})
    else showFlash('error', e.response?.data?.message ?? 'An error occurred.')
  } finally {
    catDrawer.saving = false
  }
}

const deleteModal = reactive({ open: false, title: '', message: '', deleting: false, fn: null })

function confirmDelete(cat) {
  deleteModal.title   = `Delete "${cat.name}"?`
  deleteModal.message = cat.expenses_count > 0
    ? `This category has ${cat.expenses_count} expense(s) and cannot be deleted.`
    : 'This action cannot be undone.'
  if (cat.expenses_count > 0) {
    deleteModal.fn = null
  } else {
    deleteModal.fn = async () => {
      await axios.delete(`/api/v1/expense-categories/${cat.id}`)
      localCategories.value = localCategories.value.filter(c => c.id !== cat.id)
      showFlash('success', 'Category deleted.')
    }
  }
  deleteModal.open = true
}

async function executeDelete() {
  if (!deleteModal.fn) { deleteModal.open = false; return }
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

// ─── Budget ───────────────────────────────────────────────────────────────────
const budgetDrawer = reactive({ open: false, saving: false })
const budgetErrors = reactive({})
const budgetForm   = reactive({ category_id: '', amount: 0, period: 'monthly', period_month: new Date().getMonth() + 1, period_year: currentYear, notes: '' })

function openBudgetDrawer() {
  Object.keys(budgetErrors).forEach(k => { budgetErrors[k] = '' })
  Object.assign(budgetForm, { category_id: '', amount: 0, period: 'monthly', period_month: new Date().getMonth() + 1, period_year: currentYear, notes: '' })
  budgetDrawer.open = true
}

async function saveBudget() {
  budgetDrawer.saving = true
  try {
    const payload = {
      expense_category_id: budgetForm.category_id,
      amount:              budgetForm.amount,
      period:              budgetForm.period,
      period_year:         budgetForm.period_year,
      period_month:        budgetForm.period === 'monthly' ? budgetForm.period_month : null,
      notes:               budgetForm.notes || null,
    }
    const res = await axios.post('/api/v1/expense-budgets', payload)
    const cat = localCategories.value.find(c => c.id === budgetForm.category_id)
    localBudgets.value.push({
      ...res.data.data,
      category: cat?.name ?? '',
    })
    showFlash('success', 'Budget saved.')
    budgetDrawer.open = false
  } catch (e) {
    if (e.response?.status === 422) Object.assign(budgetErrors, e.response.data.errors ?? {})
    else showFlash('error', e.response?.data?.message ?? 'An error occurred.')
  } finally {
    budgetDrawer.saving = false
  }
}

// ─── Approval Rules ───────────────────────────────────────────────────────────
const approvalDrawer = reactive({ open: false, saving: false })
const approvalForm   = reactive({ category_id: '', threshold_amount: 0, approver_role: 'branch_manager', requires_receipt: false })

function openApprovalDrawer() {
  Object.assign(approvalForm, { category_id: '', threshold_amount: 0, approver_role: 'branch_manager', requires_receipt: false })
  approvalDrawer.open = true
}

async function saveApproval() {
  approvalDrawer.saving = true
  try {
    const payload = {
      settings: [{
        category_id:      approvalForm.category_id || null,
        threshold_amount: approvalForm.threshold_amount,
        approver_role:    approvalForm.approver_role,
        requires_receipt: approvalForm.requires_receipt,
      }],
    }
    await axios.put('/api/v1/expense-settings', payload)
    const cat = localCategories.value.find(c => c.id === approvalForm.category_id)
    localApprovals.value.push({
      id:               Date.now(),
      category_id:      approvalForm.category_id || null,
      category:         cat?.name ?? null,
      threshold_amount: approvalForm.threshold_amount,
      approver_role:    approvalForm.approver_role,
      requires_receipt: approvalForm.requires_receipt,
    })
    showFlash('success', 'Approval rule saved.')
    approvalDrawer.open = false
  } catch (e) {
    showFlash('error', e.response?.data?.message ?? 'An error occurred.')
  } finally {
    approvalDrawer.saving = false
  }
}
</script>

<style scoped>
@reference "../../../../css/app.css";
.btn-primary  { @apply inline-flex items-center px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium rounded-lg transition-colors disabled:opacity-60 disabled:cursor-not-allowed; }
.btn-secondary{ @apply inline-flex items-center px-3 py-2 border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-800 hover:bg-gray-50 dark:hover:bg-gray-700 text-sm font-medium rounded-lg transition-colors; }
.btn-danger   { @apply inline-flex items-center px-4 py-2 bg-red-600 hover:bg-red-700 text-white text-sm font-medium rounded-lg transition-colors disabled:opacity-60 disabled:cursor-not-allowed; }
.form-label   { @apply block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1; }
.form-input   { @apply w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-900 dark:text-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent; }
.form-checkbox{ @apply rounded border-gray-300 dark:border-gray-600 text-blue-600 focus:ring-blue-500; }
.form-error   { @apply mt-1 text-xs text-red-600 dark:text-red-400; }

.fade-enter-active, .fade-leave-active { transition: opacity 0.2s ease; }
.fade-enter-from, .fade-leave-to { opacity: 0; }
.slide-right-enter-active, .slide-right-leave-active { transition: transform 0.3s ease; }
.slide-right-enter-from, .slide-right-leave-to { transform: translateX(100%); }
</style>
