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
      <!-- Personal Info -->
      <div class="lendr-card p-6">
        <h2 class="text-sm font-semibold text-neutral-800 mb-4">Personal Information</h2>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
          <FormField label="First Name *" :error="form.errors.first_name">
            <input v-model="form.first_name" type="text" class="input" placeholder="John" />
          </FormField>
          <FormField label="Other Names" :error="form.errors.other_names">
            <input v-model="form.other_names" type="text" class="input" placeholder="Middle name" />
          </FormField>
          <FormField label="Last Name" :error="form.errors.last_name">
            <input v-model="form.last_name" type="text" class="input" placeholder="Banda" />
          </FormField>
          <FormField label="Gender" :error="form.errors.gender">
            <select v-model="form.gender" class="input">
              <option value="">Select gender</option>
              <option value="male">Male</option>
              <option value="female">Female</option>
              <option value="other">Other</option>
            </select>
          </FormField>
          <FormField label="Date of Birth" :error="form.errors.date_of_birth">
            <input v-model="form.date_of_birth" type="date" class="input" />
          </FormField>
          <FormField label="NRC Number" :error="form.errors.national_id">
            <input v-model="form.national_id" type="text" class="input" placeholder="123456/78/9" />
          </FormField>
          <FormField label="TPIN Number" :error="form.errors.tpin_number">
            <input v-model="form.tpin_number" type="text" class="input" placeholder="1234567890" />
          </FormField>
        </div>
      </div>

      <!-- Contact -->
      <div class="lendr-card p-6">
        <h2 class="text-sm font-semibold text-neutral-800 mb-4">Contact Details</h2>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <FormField label="Phone *" :error="form.errors.phone">
            <input v-model="form.phone" type="tel" class="input" placeholder="+260 97X XXX XXX" />
          </FormField>
          <FormField label="Alternate Phone" :error="form.errors.phone_alt">
            <input v-model="form.phone_alt" type="tel" class="input" placeholder="+260 96X XXX XXX" />
          </FormField>
          <FormField label="Email Address" :error="form.errors.email" class="md:col-span-2">
            <input v-model="form.email" type="email" class="input" placeholder="john@example.com" />
          </FormField>
          <FormField label="Physical Address" :error="form.errors.address" class="md:col-span-2">
            <input v-model="form.address" type="text" class="input" placeholder="Plot 123, Cairo Road" />
          </FormField>
          <FormField label="City" :error="form.errors.city">
            <input v-model="form.city" type="text" class="input" placeholder="Lusaka" />
          </FormField>
          <FormField label="Province" :error="form.errors.province">
            <select v-model="form.province" class="input">
              <option value="">Select province</option>
              <option v-for="p in zambianProvinces" :key="p" :value="p">{{ p }}</option>
            </select>
          </FormField>
        </div>
      </div>

      <!-- Employment -->
      <div class="lendr-card p-6">
        <h2 class="text-sm font-semibold text-neutral-800 mb-4">Employment</h2>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <FormField label="Occupation" :error="form.errors.occupation">
            <input v-model="form.occupation" type="text" class="input" placeholder="Teacher, Trader, etc." />
          </FormField>
          <FormField label="Employer" :error="form.errors.employer">
            <input v-model="form.employer" type="text" class="input" placeholder="Company / School name" />
          </FormField>
        </div>
      </div>

      <!-- Next of Kin -->
      <div class="lendr-card p-6">
        <h2 class="text-sm font-semibold text-neutral-800 mb-4">Next of Kin</h2>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
          <FormField label="Full Name" :error="form.errors.next_of_kin_name" class="md:col-span-2">
            <input v-model="form.next_of_kin_name" type="text" class="input" placeholder="Full name" />
          </FormField>
          <FormField label="Relationship" :error="form.errors.next_of_kin_relationship">
            <input v-model="form.next_of_kin_relationship" type="text" class="input" placeholder="Spouse, Parent…" />
          </FormField>
          <FormField label="Phone" :error="form.errors.next_of_kin_phone">
            <input v-model="form.next_of_kin_phone" type="tel" class="input" placeholder="+260 97X XXX XXX" />
          </FormField>
        </div>
      </div>

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
import FormField from '@/admin/components/forms/FormField.vue'

const props = defineProps({ borrower: Object })

const zambianProvinces = [
  'Central', 'Copperbelt', 'Eastern', 'Luapula',
  'Lusaka', 'Muchinga', 'North-Western', 'Northern', 'Southern', 'Western',
]

const form = useForm({
  first_name:                props.borrower.first_name ?? '',
  last_name:                 props.borrower.last_name ?? '',
  other_names:               props.borrower.other_names ?? '',
  email:                     props.borrower.email ?? '',
  phone:                     props.borrower.phone ?? '',
  phone_alt:                 props.borrower.phone_alt ?? '',
  gender:                    props.borrower.gender ?? '',
  date_of_birth:             props.borrower.date_of_birth ?? '',
  national_id:               props.borrower.national_id ?? '',
  tpin_number:               props.borrower.tpin_number ?? '',
  occupation:                props.borrower.occupation ?? '',
  employer:                  props.borrower.employer ?? '',
  address:                   props.borrower.address ?? '',
  city:                      props.borrower.city ?? '',
  province:                  props.borrower.province ?? '',
  country:                   props.borrower.country ?? 'ZM',
  next_of_kin_name:          props.borrower.next_of_kin_name ?? '',
  next_of_kin_phone:         props.borrower.next_of_kin_phone ?? '',
  next_of_kin_relationship:  props.borrower.next_of_kin_relationship ?? '',
  is_active:                 props.borrower.is_active ?? true,
})

function submit() {
  form.put(route('borrowers.update', props.borrower.id))
}
</script>
