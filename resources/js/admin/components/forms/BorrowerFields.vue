<template>
  <!-- Borrower type -->
  <div class="lendr-card p-6">
    <h2 class="text-sm font-semibold text-neutral-800 mb-4">Borrower Type</h2>
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3" role="radiogroup" aria-label="Borrower type">
      <button
        v-for="option in types"
        :key="option.value"
        type="button"
        role="radio"
        :aria-checked="form.borrower_type === option.value"
        @click="form.borrower_type = option.value"
        class="text-left rounded-xl border p-4 transition"
        :class="form.borrower_type === option.value
          ? 'border-primary-600 bg-primary-50 ring-1 ring-primary-600'
          : 'border-neutral-200 hover:border-neutral-300'"
      >
        <p class="text-sm font-semibold text-neutral-900">{{ option.label }}</p>
        <p class="text-xs text-neutral-500 mt-0.5">{{ option.hint }}</p>
      </button>
    </div>
  </div>

  <!-- ── Individual ── -->
  <template v-if="!isCorporate">
    <div class="lendr-card p-6">
      <h2 class="text-sm font-semibold text-neutral-800 mb-4">Personal Information</h2>
      <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <FormField label="First Name *" :error="form.errors.first_name">
          <input v-model="form.first_name" type="text" class="input" placeholder="John" />
        </FormField>
        <FormField label="Other Names" :error="form.errors.other_names">
          <input v-model="form.other_names" type="text" class="input" placeholder="Middle name" />
        </FormField>
        <FormField label="Last Name *" :error="form.errors.last_name">
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
        <FormField label="TPIN" :error="form.errors.tpin_number">
          <input v-model="form.tpin_number" type="text" class="input" placeholder="1234567890" />
        </FormField>
      </div>
    </div>
  </template>

  <!-- ── Corporate ── -->
  <template v-else>
    <div class="lendr-card p-6">
      <h2 class="text-sm font-semibold text-neutral-800 mb-4">Company Information</h2>
      <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <FormField label="Registered Company Name *" :error="form.errors.company_name">
          <input v-model="form.company_name" type="text" class="input" placeholder="Mwale Agro Supplies Ltd" />
        </FormField>
        <FormField label="Trading Name" :error="form.errors.trading_name">
          <input v-model="form.trading_name" type="text" class="input" placeholder="If different from the registered name" />
        </FormField>
        <FormField label="Business Type" :error="form.errors.business_type">
          <select v-model="form.business_type" class="input">
            <option value="">Select business type</option>
            <option v-for="(label, value) in businessTypes" :key="value" :value="value">{{ label }}</option>
          </select>
        </FormField>
        <FormField label="Industry / Sector" :error="form.errors.industry">
          <input v-model="form.industry" type="text" class="input" placeholder="Agriculture, Retail, Transport…" />
        </FormField>
        <FormField label="PACRA Registration Number *" :error="form.errors.company_reg_number">
          <input v-model="form.company_reg_number" type="text" class="input" placeholder="120200012345" />
        </FormField>
        <FormField label="TPIN" :error="form.errors.tpin_number">
          <input v-model="form.tpin_number" type="text" class="input" placeholder="1234567890" />
        </FormField>
        <FormField label="Date of Incorporation" :error="form.errors.incorporation_date">
          <input v-model="form.incorporation_date" type="date" class="input" />
        </FormField>
      </div>
    </div>
  </template>

  <!-- Contact -->
  <div class="lendr-card p-6">
    <h2 class="text-sm font-semibold text-neutral-800 mb-4">{{ isCorporate ? 'Company Contact Details' : 'Contact Details' }}</h2>
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
      <FormField label="Phone *" :error="form.errors.phone">
        <input v-model="form.phone" type="tel" class="input" placeholder="+260 97X XXX XXX" />
      </FormField>
      <FormField label="Alternate Phone" :error="form.errors.phone_alt">
        <input v-model="form.phone_alt" type="tel" class="input" placeholder="+260 96X XXX XXX" />
      </FormField>
      <FormField label="Email Address" :error="form.errors.email" class="md:col-span-2">
        <input v-model="form.email" type="email" class="input" :placeholder="isCorporate ? 'accounts@company.co.zm' : 'john@example.com'" />
      </FormField>
      <FormField :label="isCorporate ? 'Business Address' : 'Physical Address'" :error="form.errors.address" class="md:col-span-2">
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

  <!-- Individual: employment + next of kin -->
  <template v-if="!isCorporate">
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
  </template>

  <!-- Corporate: contact person -->
  <div v-else class="lendr-card p-6">
    <h2 class="text-sm font-semibold text-neutral-800 mb-1">Contact Person</h2>
    <p class="text-xs text-neutral-500 mb-4">The director or authorised signatory staff should deal with about this account.</p>
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
      <FormField label="Full Name *" :error="form.errors.contact_person_name">
        <input v-model="form.contact_person_name" type="text" class="input" placeholder="Chanda Mwale" />
      </FormField>
      <FormField label="Position" :error="form.errors.contact_person_position">
        <input v-model="form.contact_person_position" type="text" class="input" placeholder="Director, Finance Manager…" />
      </FormField>
      <FormField label="Phone" :error="form.errors.contact_person_phone">
        <input v-model="form.contact_person_phone" type="tel" class="input" placeholder="+260 97X XXX XXX" />
      </FormField>
      <FormField label="Email" :error="form.errors.contact_person_email">
        <input v-model="form.contact_person_email" type="email" class="input" placeholder="name@company.co.zm" />
      </FormField>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue'
import FormField from './FormField.vue'

const props = defineProps({
  form: { type: Object, required: true },
  businessTypes: { type: Object, default: () => ({}) },
})

const types = [
  { value: 'individual', label: 'Individual', hint: 'A person borrowing in their own name' },
  { value: 'corporate', label: 'Corporate', hint: 'A company, SME, cooperative or other organisation' },
]

const isCorporate = computed(() => props.form.borrower_type === 'corporate')

const zambianProvinces = [
  'Central', 'Copperbelt', 'Eastern', 'Luapula',
  'Lusaka', 'Muchinga', 'North-Western', 'Northern', 'Southern', 'Western',
]
</script>
