<template>
  <AppLayout>
    <template #header>
      <div class="flex items-center gap-3">
        <Link :href="route('borrowers.index')" class="text-neutral-400 hover:text-neutral-600">
          <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
          </svg>
        </Link>
        <h1 class="text-2xl font-bold text-neutral-900">Add Borrower</h1>
      </div>
    </template>

    <form @submit.prevent="submit" class="max-w-3xl space-y-6">
      <BorrowerFields :form="form" :business-types="businessTypes" />

      <!-- Actions -->
      <div class="flex items-center gap-3">
        <button type="submit" :disabled="form.processing" class="btn-primary">
          {{ form.processing ? 'Saving…' : 'Create Borrower' }}
        </button>
        <Link :href="route('borrowers.index')" class="btn-secondary">Cancel</Link>
      </div>
    </form>
  </AppLayout>
</template>

<script setup>
import { useForm, Link } from '@inertiajs/vue3'
import AppLayout from '@/admin/components/layout/AppLayout.vue'
import BorrowerFields from '@/admin/components/forms/BorrowerFields.vue'

defineProps({ businessTypes: { type: Object, default: () => ({}) } })

const form = useForm({
  borrower_type: 'individual',
  // Individual
  first_name: '',
  last_name: '',
  other_names: '',
  gender: '',
  date_of_birth: '',
  national_id: '',
  occupation: '',
  employer: '',
  next_of_kin_name: '',
  next_of_kin_phone: '',
  next_of_kin_relationship: '',
  // Corporate
  company_name: '',
  trading_name: '',
  business_type: '',
  industry: '',
  company_reg_number: '',
  incorporation_date: '',
  contact_person_name: '',
  contact_person_position: '',
  contact_person_phone: '',
  contact_person_email: '',
  // Both
  tpin_number: '',
  email: '',
  phone: '',
  phone_alt: '',
  address: '',
  city: '',
  province: '',
  country: 'ZM',
})

function submit() {
  form.post(route('borrowers.store'))
}
</script>
