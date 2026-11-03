<template>
  <AppLayout>
    <template #header>
      <div class="flex items-center gap-3">
        <Link :href="route('borrowers.show', borrower.id)" class="text-neutral-400 hover:text-neutral-600">
          <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
          </svg>
        </Link>
        <div>
          <h1 class="text-2xl font-bold text-neutral-900">Edit Borrower</h1>
          <p class="text-sm text-neutral-500 mt-0.5">{{ borrower.borrower_number }}</p>
        </div>
      </div>
    </template>

    <form @submit.prevent="submit" class="max-w-3xl space-y-6">
      <BorrowerFields :form="form" :business-types="businessTypes" />

      <!-- Status -->
      <div class="lendr-card p-6">
        <h2 class="text-sm font-semibold text-neutral-800 mb-4">Status</h2>
        <div class="flex items-center gap-6">
          <label class="flex items-center gap-2 cursor-pointer">
            <input v-model="form.is_active" type="checkbox" class="rounded border-neutral-300 text-primary-600" />
            <span class="text-sm text-neutral-700">Active borrower</span>
          </label>
        </div>
      </div>

      <!-- Actions -->
      <div class="flex items-center gap-3">
        <button type="submit" :disabled="form.processing" class="btn-primary">
          {{ form.processing ? 'Saving…' : 'Save Changes' }}
        </button>
        <Link :href="route('borrowers.show', borrower.id)" class="btn-secondary">Cancel</Link>
      </div>
    </form>
  </AppLayout>
</template>

<script setup>
import { useForm, Link } from '@inertiajs/vue3'
import AppLayout from '@/admin/components/layout/AppLayout.vue'
import BorrowerFields from '@/admin/components/forms/BorrowerFields.vue'

const props = defineProps({
  borrower: Object,
  businessTypes: { type: Object, default: () => ({}) },
})

const fields = [
  'first_name', 'last_name', 'other_names', 'gender', 'date_of_birth', 'national_id',
  'occupation', 'employer', 'next_of_kin_name', 'next_of_kin_phone', 'next_of_kin_relationship',
  'company_name', 'trading_name', 'business_type', 'industry', 'company_reg_number', 'incorporation_date',
  'contact_person_name', 'contact_person_position', 'contact_person_phone', 'contact_person_email',
  'tpin_number', 'email', 'phone', 'phone_alt', 'address', 'city', 'province',
]

const form = useForm({
  borrower_type: props.borrower.borrower_type ?? 'individual',
  ...Object.fromEntries(fields.map((f) => [f, props.borrower[f] ?? ''])),
  country: props.borrower.country ?? 'ZM',
  is_active: props.borrower.is_active ?? true,
})

function submit() {
  form.put(route('borrowers.update', props.borrower.id))
}
</script>
