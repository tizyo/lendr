<template>
  <PwaLayout title="New Listing" :show-back="true" back-route="pwa.marketplace.listings">
    <div class="px-4 py-5">
      <p class="text-sm text-gray-500 mb-5">List your loan need publicly so lenders can offer you funding.</p>

      <form @submit.prevent="submit" class="space-y-4">

        <!-- Title -->
        <div>
          <label class="block text-xs font-semibold text-gray-600 mb-1">Listing Title *</label>
          <input
            v-model="form.title"
            type="text"
            placeholder="e.g. Business expansion loan"
            maxlength="150"
            required
            class="w-full bg-white border border-gray-200 rounded-xl px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500"
          />
          <p v-if="errors.title" class="text-xs text-red-500 mt-1">{{ errors.title }}</p>
        </div>

        <!-- Purpose -->
        <div>
          <label class="block text-xs font-semibold text-gray-600 mb-1">Purpose *</label>
          <select
            v-model="form.purpose"
            required
            class="w-full bg-white border border-gray-200 rounded-xl px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500"
          >
            <option value="">Select purpose</option>
            <option value="business">Business</option>
            <option value="education">Education</option>
            <option value="medical">Medical</option>
            <option value="personal">Personal</option>
            <option value="agriculture">Agriculture</option>
            <option value="other">Other</option>
          </select>
          <p v-if="errors.purpose" class="text-xs text-red-500 mt-1">{{ errors.purpose }}</p>
        </div>

        <!-- Amount + Rate -->
        <div class="grid grid-cols-2 gap-3">
          <div>
            <label class="block text-xs font-semibold text-gray-600 mb-1">Amount (ZMW) *</label>
            <input
              v-model.number="form.amount_requested"
              type="number"
              min="100"
              step="0.01"
              placeholder="5000"
              required
              class="w-full bg-white border border-gray-200 rounded-xl px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500"
            />
            <p v-if="errors.amount_requested" class="text-xs text-red-500 mt-1">{{ errors.amount_requested }}</p>
          </div>
          <div>
            <label class="block text-xs font-semibold text-gray-600 mb-1">Rate Offered (%) *</label>
            <input
              v-model.number="form.interest_rate_offered"
              type="number"
              min="0"
              max="100"
              step="0.1"
              placeholder="18"
              required
              class="w-full bg-white border border-gray-200 rounded-xl px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500"
            />
            <p v-if="errors.interest_rate_offered" class="text-xs text-red-500 mt-1">{{ errors.interest_rate_offered }}</p>
          </div>
        </div>

        <!-- Tenure -->
        <div>
          <label class="block text-xs font-semibold text-gray-600 mb-1">Tenure (months) *</label>
          <input
            v-model.number="form.tenure_months"
            type="number"
            min="1"
            max="120"
            placeholder="12"
            required
            class="w-full bg-white border border-gray-200 rounded-xl px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500"
          />
          <p v-if="errors.tenure_months" class="text-xs text-red-500 mt-1">{{ errors.tenure_months }}</p>
        </div>

        <!-- Description -->
        <div>
          <label class="block text-xs font-semibold text-gray-600 mb-1">Description</label>
          <textarea
            v-model="form.description"
            rows="3"
            placeholder="Tell lenders about your business or why you need this loan…"
            maxlength="1000"
            class="w-full bg-white border border-gray-200 rounded-xl px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-500 resize-none"
          ></textarea>
          <p v-if="errors.description" class="text-xs text-red-500 mt-1">{{ errors.description }}</p>
        </div>

        <!-- Publish toggle -->
        <label class="flex items-center gap-3 bg-white border border-gray-200 rounded-xl p-4 cursor-pointer">
          <input v-model="form.publish" type="checkbox" class="w-4 h-4 text-emerald-600 rounded" />
          <div>
            <p class="text-sm font-semibold text-gray-800">Publish immediately</p>
            <p class="text-xs text-gray-400">Make visible to lenders right away. You can withdraw later.</p>
          </div>
        </label>

        <!-- Error -->
        <p v-if="serverError" class="text-xs text-red-500 bg-red-50 border border-red-100 rounded-xl p-3">{{ serverError }}</p>

        <!-- Submit -->
        <button
          type="submit"
          :disabled="submitting"
          class="w-full bg-emerald-600 text-white rounded-xl py-3.5 font-semibold text-sm active:bg-emerald-700 disabled:opacity-50 mt-2"
        >
          {{ submitting ? 'Creating…' : (form.publish ? 'Create & Publish' : 'Save as Draft') }}
        </button>

      </form>
    </div>
  </PwaLayout>
</template>

<script setup>
import { reactive, ref } from 'vue'
import { router } from '@inertiajs/vue3'
import axios from 'axios'
import PwaLayout from '@/pwa/components/PwaLayout.vue'

const form = reactive({
  title:                 '',
  purpose:               '',
  amount_requested:      null,
  interest_rate_offered: null,
  tenure_months:         null,
  description:           '',
  publish:               true,
})

const errors      = reactive({})
const submitting  = ref(false)
const serverError = ref(null)

async function submit() {
  submitting.value  = true
  serverError.value = null
  Object.keys(errors).forEach(k => delete errors[k])

  try {
    await axios.post('/api/v1/me/marketplace/listings', form)
    router.visit(route('pwa.marketplace.listings'))
  } catch (e) {
    const data = e.response?.data
    if (e.response?.status === 422 && data?.errors) {
      Object.assign(errors, Object.fromEntries(
        Object.entries(data.errors).map(([k, v]) => [k, v[0]])
      ))
    } else {
      serverError.value = data?.message ?? 'Something went wrong. Please try again.'
    }
  } finally {
    submitting.value = false
  }
}
</script>
