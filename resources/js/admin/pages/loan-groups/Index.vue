<template>
  <AppLayout>
    <template #header>
      <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
          <h1 class="text-2xl font-bold text-neutral-900">Loan Groups</h1>
          <p class="text-sm text-neutral-500 mt-0.5">{{ groups.total }} group{{ groups.total !== 1 ? 's' : '' }}</p>
        </div>
        <button @click="showCreate = true" class="btn-primary">+ New Group</button>
      </div>
    </template>

    <!-- Filters -->
    <div class="lendr-card p-4 mb-4 flex flex-col sm:flex-row gap-3">
      <input v-model="search" type="text" placeholder="Search groups…" class="input flex-1" @input="reload" />
      <select v-model="filterStatus" class="input w-40" @change="reload">
        <option value="">All Statuses</option>
        <option value="active">Active</option>
        <option value="inactive">Inactive</option>
        <option value="dissolved">Dissolved</option>
      </select>
    </div>

    <!-- Table -->
    <div class="lendr-card overflow-hidden">
      <div class="overflow-x-auto">
        <table class="w-full text-sm">
          <thead>
            <tr class="border-b border-neutral-100 bg-neutral-50">
              <th class="text-left px-5 py-3 text-xs font-semibold text-neutral-500 uppercase tracking-wide">Group #</th>
              <th class="text-left px-5 py-3 text-xs font-semibold text-neutral-500 uppercase tracking-wide">Name</th>
              <th class="text-left px-5 py-3 text-xs font-semibold text-neutral-500 uppercase tracking-wide">Officer</th>
              <th class="text-right px-5 py-3 text-xs font-semibold text-neutral-500 uppercase tracking-wide">Members</th>
              <th class="text-right px-5 py-3 text-xs font-semibold text-neutral-500 uppercase tracking-wide">Loans</th>
              <th class="text-left px-5 py-3 text-xs font-semibold text-neutral-500 uppercase tracking-wide">Status</th>
              <th class="px-5 py-3"></th>
            </tr>
          </thead>
          <tbody class="divide-y divide-neutral-50">
            <tr v-for="g in groups.data" :key="g.id" class="hover:bg-neutral-25">
              <td class="px-5 py-3 font-mono text-xs font-semibold">{{ g.group_number }}</td>
              <td class="px-5 py-3 font-medium">{{ g.name }}</td>
              <td class="px-5 py-3 text-neutral-500">{{ g.officer?.name ?? '—' }}</td>
              <td class="px-5 py-3 text-right">{{ g.active_members_count }} / {{ g.max_members }}</td>
              <td class="px-5 py-3 text-right">{{ g.loans_count }}</td>
              <td class="px-5 py-3">
                <span :class="statusClass(g.status)" class="px-2 py-0.5 rounded-full text-xs font-medium capitalize">{{ g.status }}</span>
              </td>
              <td class="px-5 py-3 text-right">
                <a :href="route('loan-groups.show', g.id)" class="text-xs text-primary-600 hover:underline">View</a>
              </td>
            </tr>
            <tr v-if="!groups.data?.length">
              <td colspan="7" class="px-5 py-10 text-center text-neutral-400">No loan groups found.</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Create Modal -->
    <div v-if="showCreate" class="fixed inset-0 bg-black/40 flex items-center justify-center z-50">
      <div class="bg-white rounded-xl shadow-2xl p-6 w-full max-w-md">
        <h3 class="text-lg font-semibold mb-4">Create Loan Group</h3>
        <div class="space-y-3">
          <div>
            <label class="label">Group Name *</label>
            <input v-model="form.name" type="text" class="input w-full" />
          </div>
          <div>
            <label class="label">Meeting Schedule</label>
            <input v-model="form.meeting_schedule" type="text" class="input w-full" placeholder="e.g. Every Monday 10AM" />
          </div>
          <div>
            <label class="label">Max Members</label>
            <input v-model="form.max_members" type="number" class="input w-full" value="30" />
          </div>
          <p v-if="createError" class="text-sm text-red-600">{{ createError }}</p>
        </div>
        <div class="flex gap-2 mt-4">
          <button @click="showCreate = false" class="btn-secondary flex-1">Cancel</button>
          <button @click="submitCreate" :disabled="creating" class="btn-primary flex-1">
            {{ creating ? 'Creating…' : 'Create' }}
          </button>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { ref } from 'vue'
import { router } from '@inertiajs/vue3'
import AppLayout from '@/admin/components/layout/AppLayout.vue'
import axios from 'axios'

const props = defineProps({ groups: Object })

const search = ref('')
const filterStatus = ref('')
const showCreate = ref(false)
const form = ref({ name: '', meeting_schedule: '', max_members: 30 })
const creating = ref(false)
const createError = ref('')

function statusClass(s) {
  return { active: 'bg-green-100 text-green-700', inactive: 'bg-yellow-100 text-yellow-700', dissolved: 'bg-neutral-100 text-neutral-500' }[s] ?? ''
}

function reload() {
  router.get(route('loan-groups.index'), { search: search.value, status: filterStatus.value }, { preserveState: true, replace: true })
}

async function submitCreate() {
  creating.value = true; createError.value = ''
  try {
    await axios.post('/api/v1/loan-groups', form.value)
    showCreate.value = false
    router.reload()
  } catch (e) {
    createError.value = e.response?.data?.message ?? 'Failed.'
  } finally {
    creating.value = false
  }
}
</script>
