<template>
  <div class="max-w-5xl mx-auto py-8 px-4 space-y-6">
    <div class="flex items-center justify-between">
      <h1 class="text-2xl font-bold">Risk Management Policies</h1>
      <button @click="openCreate" class="bg-green-600 text-white rounded px-4 py-2 text-sm hover:bg-green-700">
        + New Policy
      </button>
    </div>

    <div class="bg-white border rounded-lg overflow-hidden">
      <table class="min-w-full text-sm">
        <thead class="bg-gray-50 text-xs font-semibold uppercase text-gray-500">
          <tr>
            <th class="px-4 py-3 text-left">#</th>
            <th class="px-4 py-3 text-left">Name</th>
            <th class="px-4 py-3 text-left">Rule Type</th>
            <th class="px-4 py-3 text-left">Value</th>
            <th class="px-4 py-3 text-left">Action</th>
            <th class="px-4 py-3 text-left">Status</th>
            <th class="px-4 py-3"></th>
          </tr>
        </thead>
        <tbody class="divide-y">
          <tr v-for="p in policies" :key="p.id" class="hover:bg-gray-50">
            <td class="px-4 py-3 text-gray-400">{{ p.sort_order }}</td>
            <td class="px-4 py-3 font-medium">{{ p.name }}</td>
            <td class="px-4 py-3 font-mono text-xs text-gray-600">{{ p.rule_type }}</td>
            <td class="px-4 py-3 text-gray-600">{{ p.value }}</td>
            <td class="px-4 py-3">
              <span :class="p.action === 'block' ? 'bg-red-100 text-red-700' : 'bg-yellow-100 text-yellow-700'"
                class="px-2 py-0.5 rounded-full text-xs font-medium capitalize">{{ p.action }}</span>
            </td>
            <td class="px-4 py-3">
              <span :class="p.is_active ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500'"
                class="px-2 py-0.5 rounded-full text-xs">{{ p.is_active ? 'Active' : 'Inactive' }}</span>
            </td>
            <td class="px-4 py-3 flex gap-2">
              <button @click="editPolicy(p)" class="text-xs text-green-700 hover:underline">Edit</button>
              <button @click="deletePolicy(p)" class="text-xs text-red-600 hover:underline">Delete</button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Modal -->
    <div v-if="showModal" class="fixed inset-0 bg-black/40 flex items-center justify-center z-50">
      <div class="bg-white rounded-xl shadow-xl w-full max-w-lg p-6 space-y-4">
        <h2 class="text-lg font-bold">{{ editing ? 'Edit Policy' : 'New Policy' }}</h2>
        <div class="grid grid-cols-2 gap-4">
          <div class="col-span-2">
            <label class="block text-xs font-medium mb-1">Policy Name *</label>
            <input v-model="form.name" class="border rounded px-3 py-2 text-sm w-full" />
          </div>
          <div>
            <label class="block text-xs font-medium mb-1">Rule Type *</label>
            <select v-model="form.rule_type" class="border rounded px-3 py-2 text-sm w-full">
              <option value="">— Select —</option>
              <option v-for="rt in ruleTypes" :key="rt" :value="rt">{{ rt }}</option>
            </select>
          </div>
          <div>
            <label class="block text-xs font-medium mb-1">Value *</label>
            <input v-model="form.value" placeholder="e.g. 3 or [&quot;City A&quot;]" class="border rounded px-3 py-2 text-sm w-full" />
          </div>
          <div>
            <label class="block text-xs font-medium mb-1">Action *</label>
            <select v-model="form.action" class="border rounded px-3 py-2 text-sm w-full">
              <option value="warn">Warn</option>
              <option value="block">Block</option>
            </select>
          </div>
          <div>
            <label class="block text-xs font-medium mb-1">Sort Order</label>
            <input v-model="form.sort_order" type="number" class="border rounded px-3 py-2 text-sm w-full" />
          </div>
          <div class="col-span-2 flex items-center gap-2">
            <input type="checkbox" v-model="form.is_active" id="is_active" />
            <label for="is_active" class="text-sm">Active</label>
          </div>
          <div class="col-span-2">
            <label class="block text-xs font-medium mb-1">Description</label>
            <textarea v-model="form.description" rows="2" class="border rounded px-3 py-2 text-sm w-full" />
          </div>
        </div>
        <p v-if="error" class="text-red-600 text-sm">{{ error }}</p>
        <div class="flex justify-end gap-3 pt-2">
          <button @click="showModal = false" class="border rounded px-4 py-2 text-sm">Cancel</button>
          <button @click="savePolicy" :disabled="saving" class="bg-green-600 text-white rounded px-4 py-2 text-sm hover:bg-green-700 disabled:opacity-50">
            {{ saving ? 'Saving…' : 'Save' }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import axios from 'axios'

const policies  = ref([])
const ruleTypes = ref([])
const showModal = ref(false)
const editing   = ref(null)
const saving    = ref(false)
const error     = ref('')

const form = ref({ name: '', rule_type: '', value: '', action: 'warn', is_active: true, sort_order: 0, description: '' })

async function load() {
  const [pol, rt] = await Promise.all([
    axios.get('/api/v1/risk-policies'),
    axios.get('/api/v1/risk-policy/rule-types'),
  ])
  policies.value  = pol.data.data
  ruleTypes.value = rt.data.data
}

function openCreate() {
  editing.value = null
  form.value = { name: '', rule_type: '', value: '', action: 'warn', is_active: true, sort_order: policies.value.length, description: '' }
  error.value = ''
  showModal.value = true
}

function editPolicy(p) {
  editing.value = p
  form.value = { name: p.name, rule_type: p.rule_type, value: p.value, action: p.action, is_active: p.is_active, sort_order: p.sort_order, description: p.description ?? '' }
  error.value = ''
  showModal.value = true
}

async function savePolicy() {
  saving.value = true; error.value = ''
  try {
    if (editing.value) {
      await axios.put(`/api/v1/risk-policies/${editing.value.id}`, form.value)
    } else {
      await axios.post('/api/v1/risk-policies', form.value)
    }
    showModal.value = false
    await load()
  } catch (e) {
    error.value = e.response?.data?.message ?? 'Error saving.'
  } finally { saving.value = false }
}

async function deletePolicy(p) {
  if (!confirm(`Delete policy "${p.name}"?`)) return
  await axios.delete(`/api/v1/risk-policies/${p.id}`)
  await load()
}

onMounted(load)
</script>
