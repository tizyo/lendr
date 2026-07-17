<template>
  <div class="min-h-screen bg-white flex flex-col">
    <!-- Header with progress -->
    <header class="bg-white border-b border-gray-100 px-4 py-3 shrink-0">
      <div class="flex items-center gap-3 mb-3">
        <button @click="back" class="p-1.5 -ml-1.5 text-gray-400 hover:text-gray-600">
          <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
          </svg>
        </button>
        <div class="flex-1">
          <p class="text-xs text-gray-400 font-medium">Step {{ step }} of {{ totalSteps }}</p>
          <h1 class="text-base font-semibold text-gray-900">{{ stepTitle }}</h1>
        </div>
        <span class="text-xs font-medium text-emerald-600">{{ Math.round((step / totalSteps) * 100) }}%</span>
      </div>
      <!-- Progress bar -->
      <div class="h-1.5 bg-gray-100 rounded-full overflow-hidden">
        <div
          class="h-full bg-emerald-500 rounded-full transition-all duration-500"
          :style="{ width: `${(step / totalSteps) * 100}%` }"
        ></div>
      </div>
    </header>

    <!-- Step content -->
    <div class="flex-1 overflow-y-auto px-4 py-6">

      <!-- Step 1: Personal Info -->
      <Transition name="slide" mode="out-in">
        <div v-if="step === 1" key="s1" class="space-y-4">
          <FormField label="First Name *" v-model="form.first_name" placeholder="Enter first name" :error="errors.first_name" />
          <FormField label="Last Name *" v-model="form.last_name" placeholder="Enter last name" :error="errors.last_name" />
          <FormField label="Other Names" v-model="form.other_names" placeholder="Middle name (optional)" />
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1.5">Gender</label>
            <div class="flex gap-3">
              <button
                v-for="g in ['male','female','other']"
                :key="g"
                @click="form.gender = g"
                class="flex-1 py-3 rounded-xl border-2 text-sm font-medium capitalize transition"
                :class="form.gender === g ? 'border-emerald-500 bg-emerald-50 text-emerald-700' : 'border-gray-200 text-gray-600'"
              >{{ g }}</button>
            </div>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1.5">Date of Birth</label>
            <input
              v-model="form.date_of_birth"
              type="date"
              class="input w-full"
              :max="maxDob"
            />
          </div>
          <FormField label="NRC Number" v-model="form.national_id" placeholder="e.g. 123456/78/9" :error="errors.national_id" />
        </div>
      </Transition>

      <!-- Step 2: Identity Document -->
      <Transition name="slide" mode="out-in">
        <div v-if="step === 2" key="s2" class="space-y-4">
          <p class="text-sm text-gray-500">Upload a photo of your National Registration Card (NRC), passport, or driver's licence.</p>

          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1.5">Document Type *</label>
            <select v-model="form.id_doc_type" class="input w-full">
              <option value="">Select document type</option>
              <option value="national_id_front">NRC — Front</option>
              <option value="national_id_back">NRC — Back</option>
              <option value="passport">Passport</option>
              <option value="drivers_licence">Driver's Licence</option>
            </select>
          </div>

          <!-- Camera / file capture -->
          <div
            @click="triggerCapture('id_doc')"
            class="border-2 border-dashed border-gray-200 rounded-2xl p-6 flex flex-col items-center gap-3 cursor-pointer hover:border-emerald-400 transition"
            :class="files.id_doc ? 'border-emerald-400 bg-emerald-50' : ''"
          >
            <img v-if="previews.id_doc" :src="previews.id_doc" class="w-full max-h-48 object-contain rounded-lg" />
            <template v-else>
              <div class="w-14 h-14 bg-gray-100 rounded-xl flex items-center justify-center">
                <svg class="w-7 h-7 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"/>
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z"/>
                </svg>
              </div>
              <p class="text-sm font-medium text-gray-700">Take photo or upload file</p>
              <p class="text-xs text-gray-400">JPG, PNG or PDF · Max 10 MB</p>
            </template>
          </div>
          <input ref="idDocInput" type="file" accept="image/*,application/pdf" capture="environment" class="hidden" @change="onFileChange($event, 'id_doc')" />

          <p v-if="files.id_doc" class="text-xs text-emerald-600 font-medium">
            ✓ {{ files.id_doc.name }} selected
          </p>
        </div>
      </Transition>

      <!-- Step 3: Address -->
      <Transition name="slide" mode="out-in">
        <div v-if="step === 3" key="s3" class="space-y-4">
          <FormField label="Street Address" v-model="form.address" placeholder="House No., Street name" />
          <FormField label="City / Town *" v-model="form.city" placeholder="e.g. Lusaka" :error="errors.city" />
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1.5">Province *</label>
            <select v-model="form.province" class="input w-full" :class="errors.province ? 'border-red-400' : ''">
              <option value="">Select province</option>
              <option v-for="p in zambiaProvinces" :key="p" :value="p">{{ p }}</option>
            </select>
            <p v-if="errors.province" class="mt-1 text-xs text-red-600">{{ errors.province }}</p>
          </div>
          <FormField label="Country" v-model="form.country" placeholder="ZM" :disabled="true" />
        </div>
      </Transition>

      <!-- Step 4: Employment -->
      <Transition name="slide" mode="out-in">
        <div v-if="step === 4" key="s4" class="space-y-4">
          <FormField label="Occupation" v-model="form.occupation" placeholder="e.g. Teacher, Farmer, Trader" />
          <FormField label="Employer / Business Name" v-model="form.employer" placeholder="Where do you work?" />
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1.5">Employment Type</label>
            <div class="grid grid-cols-2 gap-2">
              <button
                v-for="et in employmentTypes"
                :key="et.value"
                @click="form.employment_type = et.value"
                class="py-3 px-2 rounded-xl border-2 text-xs font-medium transition text-center"
                :class="form.employment_type === et.value ? 'border-emerald-500 bg-emerald-50 text-emerald-700' : 'border-gray-200 text-gray-600'"
              >{{ et.label }}</button>
            </div>
          </div>
        </div>
      </Transition>

      <!-- Step 5: Next of Kin -->
      <Transition name="slide" mode="out-in">
        <div v-if="step === 5" key="s5" class="space-y-4">
          <p class="text-sm text-gray-500">Please provide contact details of someone we can reach if needed.</p>
          <FormField label="Full Name *" v-model="form.next_of_kin_name" placeholder="Enter full name" :error="errors.next_of_kin_name" />
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1.5">Phone Number *</label>
            <div class="flex rounded-xl border border-gray-300 overflow-hidden focus-within:border-emerald-500 transition">
              <span class="flex items-center px-3 bg-gray-50 border-r border-gray-300 text-sm text-gray-600">🇿🇲</span>
              <input
                v-model="form.next_of_kin_phone"
                type="tel"
                inputmode="numeric"
                placeholder="971 234 567"
                class="flex-1 px-3 py-3 text-base outline-none"
              />
            </div>
            <p v-if="errors.next_of_kin_phone" class="mt-1 text-xs text-red-600">{{ errors.next_of_kin_phone }}</p>
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1.5">Relationship</label>
            <select v-model="form.next_of_kin_relationship" class="input w-full">
              <option value="">Select relationship</option>
              <option v-for="r in relationships" :key="r" :value="r">{{ r }}</option>
            </select>
          </div>
        </div>
      </Transition>

      <!-- Step 6: Selfie -->
      <Transition name="slide" mode="out-in">
        <div v-if="step === 6" key="s6" class="space-y-4">
          <p class="text-sm text-gray-500">Take a clear selfie of your face. Ensure good lighting and your face is clearly visible.</p>

          <!-- Live camera or preview -->
          <div class="relative rounded-2xl overflow-hidden bg-black aspect-[3/4] max-h-96 flex items-center justify-center">
            <video
              v-if="cameraActive && !previews.selfie"
              ref="videoRef"
              autoplay
              playsinline
              muted
              class="w-full h-full object-cover"
            ></video>
            <img
              v-else-if="previews.selfie"
              :src="previews.selfie"
              class="w-full h-full object-cover"
            />
            <div v-else class="flex flex-col items-center gap-3 text-white/70">
              <svg class="w-12 h-12" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"/>
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z"/>
              </svg>
              <p class="text-sm">Camera not started</p>
            </div>
            <!-- Overlay guide oval -->
            <div v-if="cameraActive && !previews.selfie" class="absolute inset-0 flex items-center justify-center pointer-events-none">
              <div class="w-44 h-56 border-4 border-white/60 rounded-full"></div>
            </div>
          </div>

          <canvas ref="canvasRef" class="hidden"></canvas>

          <div class="flex gap-3">
            <button
              v-if="!cameraActive && !previews.selfie"
              @click="startCamera"
              class="flex-1 py-3 rounded-xl bg-emerald-600 text-white font-medium text-sm"
            >Start Camera</button>

            <button
              v-if="cameraActive && !previews.selfie"
              @click="capturePhoto"
              class="flex-1 py-3 rounded-xl bg-emerald-600 text-white font-medium text-sm flex items-center justify-center gap-2"
            >
              <div class="w-4 h-4 rounded-full border-2 border-white"></div>
              Take Photo
            </button>

            <button
              v-if="previews.selfie"
              @click="retakeSelfie"
              class="flex-1 py-3 rounded-xl border border-gray-300 text-gray-700 font-medium text-sm"
            >Retake</button>

            <!-- File fallback -->
            <button
              v-if="!previews.selfie"
              @click="$refs.selfieInput.click()"
              class="py-3 px-4 rounded-xl border border-gray-300 text-gray-600 font-medium text-sm"
            >
              <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/>
              </svg>
            </button>
          </div>
          <input ref="selfieInput" type="file" accept="image/*" capture="user" class="hidden" @change="onFileChange($event, 'selfie')" />
        </div>
      </Transition>

      <!-- Step 7: Review & Submit -->
      <Transition name="slide" mode="out-in">
        <div v-if="step === 7" key="s7" class="space-y-4">
          <div class="bg-emerald-50 border border-emerald-100 rounded-xl p-4 flex gap-3">
            <svg class="w-5 h-5 text-emerald-600 mt-0.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
            <p class="text-sm text-emerald-800">Please review your details before submitting. Our team will verify your documents within 24 hours.</p>
          </div>

          <!-- Summary cards -->
          <ReviewSection title="Personal Info">
            <ReviewRow label="Name" :value="`${form.first_name} ${form.other_names || ''} ${form.last_name}`.trim()" />
            <ReviewRow label="Gender" :value="capitalize(form.gender)" />
            <ReviewRow label="Date of Birth" :value="form.date_of_birth" />
            <ReviewRow label="NRC" :value="form.national_id" />
          </ReviewSection>

          <ReviewSection title="Address">
            <ReviewRow label="City" :value="form.city" />
            <ReviewRow label="Province" :value="form.province" />
            <ReviewRow label="Address" :value="form.address" />
          </ReviewSection>

          <ReviewSection title="Employment">
            <ReviewRow label="Occupation" :value="form.occupation" />
            <ReviewRow label="Employer" :value="form.employer" />
            <ReviewRow label="Type" :value="form.employment_type" />
          </ReviewSection>

          <ReviewSection title="Next of Kin">
            <ReviewRow label="Name" :value="form.next_of_kin_name" />
            <ReviewRow label="Phone" :value="form.next_of_kin_phone" />
            <ReviewRow label="Relationship" :value="form.next_of_kin_relationship" />
          </ReviewSection>

          <ReviewSection title="Documents">
            <ReviewRow label="ID Doc" :value="files.id_doc ? files.id_doc.name : 'Not uploaded'" />
            <ReviewRow label="Selfie" :value="files.selfie ? 'Captured' : 'Not taken'" />
          </ReviewSection>
        </div>
      </Transition>
    </div>

    <!-- Bottom actions -->
    <div class="px-4 py-4 bg-white border-t border-gray-100 shrink-0 safe-bottom">
      <p v-if="submitError" class="text-sm text-red-600 mb-3 text-center">{{ submitError }}</p>
      <button
        @click="next"
        :disabled="submitting"
        class="w-full py-4 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-semibold text-base transition disabled:opacity-50"
      >
        <span v-if="submitting" class="flex items-center justify-center gap-2">
          <svg class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
          </svg>
          Submitting…
        </span>
        <span v-else>{{ step === totalSteps ? 'Submit KYC' : 'Continue' }}</span>
      </button>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive, computed } from 'vue'
import { router } from '@inertiajs/vue3'
import axios from 'axios'
import { usePwaAuthStore } from '@/pwa/stores/auth.js'

// ─── Sub-components ───────────────────────────────────────────
const FormField = {
  props: ['label', 'modelValue', 'placeholder', 'error', 'disabled', 'type'],
  emits: ['update:modelValue'],
  template: `
    <div>
      <label class="block text-sm font-medium text-gray-700 mb-1.5">{{ label }}</label>
      <input
        :value="modelValue"
        :type="type || 'text'"
        :placeholder="placeholder"
        :disabled="disabled"
        @input="$emit('update:modelValue', $event.target.value)"
        class="input w-full"
        :class="[error ? 'border-red-400' : '', disabled ? 'bg-gray-50 text-gray-400' : '']"
      />
      <p v-if="error" class="mt-1 text-xs text-red-600">{{ error }}</p>
    </div>
  `,
}

const ReviewSection = {
  props: ['title'],
  template: `
    <div class="bg-gray-50 rounded-xl overflow-hidden">
      <div class="px-4 py-2.5 bg-gray-100 border-b border-gray-200">
        <h4 class="text-xs font-semibold text-gray-500 uppercase tracking-wide">{{ title }}</h4>
      </div>
      <div class="px-4 py-3 space-y-2">
        <slot />
      </div>
    </div>
  `,
}

const ReviewRow = {
  props: ['label', 'value'],
  template: `
    <div class="flex justify-between gap-3">
      <span class="text-xs text-gray-500 shrink-0">{{ label }}</span>
      <span class="text-xs text-gray-900 font-medium text-right">{{ value || '—' }}</span>
    </div>
  `,
}

// ─── State ────────────────────────────────────────────────────
const auth       = usePwaAuthStore()
const step       = ref(1)
const totalSteps = 7
const submitting = ref(false)
const submitError = ref('')

const form = reactive({
  first_name:               '',
  last_name:                '',
  other_names:              '',
  gender:                   '',
  date_of_birth:            '',
  national_id:              '',
  address:                  '',
  city:                     '',
  province:                 '',
  country:                  'ZM',
  occupation:               '',
  employer:                 '',
  employment_type:          '',
  next_of_kin_name:         '',
  next_of_kin_phone:        '',
  next_of_kin_relationship: '',
})

const files   = reactive({ id_doc: null, selfie: null })
const previews = reactive({ id_doc: null, selfie: null })
const errors  = reactive({})

// Camera
const cameraActive = ref(false)
const videoRef     = ref(null)
const canvasRef    = ref(null)
const idDocInput   = ref(null)
const selfieInput  = ref(null)
let cameraStream   = null

// ─── Constants ────────────────────────────────────────────────
const zambiaProvinces = [
  'Central', 'Copperbelt', 'Eastern', 'Luapula', 'Lusaka',
  'Muchinga', 'Northern', 'North-Western', 'Southern', 'Western',
]

const employmentTypes = [
  { value: 'employed',    label: 'Employed' },
  { value: 'self_employed', label: 'Self-Employed' },
  { value: 'business',    label: 'Business Owner' },
  { value: 'unemployed',  label: 'Unemployed' },
  { value: 'student',     label: 'Student' },
  { value: 'retired',     label: 'Retired' },
]

const relationships = ['Spouse', 'Parent', 'Sibling', 'Child', 'Friend', 'Other']

const maxDob = computed(() => {
  const d = new Date()
  d.setFullYear(d.getFullYear() - 18)
  return d.toISOString().split('T')[0]
})

const stepTitle = computed(() => {
  const titles = ['Personal Info', 'Identity Document', 'Address', 'Employment', 'Next of Kin', 'Selfie', 'Review & Submit']
  return titles[step.value - 1] ?? ''
})

// ─── Validation ───────────────────────────────────────────────
function validateStep() {
  const e = {}
  if (step.value === 1) {
    if (!form.first_name.trim()) e.first_name = 'Required'
    if (!form.last_name.trim())  e.last_name  = 'Required'
  }
  if (step.value === 2) {
    if (!files.id_doc) e.id_doc = 'Please upload or capture your ID document'
  }
  if (step.value === 3) {
    if (!form.city.trim())     e.city     = 'Required'
    if (!form.province)        e.province = 'Required'
  }
  if (step.value === 5) {
    if (!form.next_of_kin_name.trim())  e.next_of_kin_name  = 'Required'
    if (!form.next_of_kin_phone.trim()) e.next_of_kin_phone = 'Required'
  }
  Object.assign(errors, {
    first_name: undefined, last_name: undefined, id_doc: undefined,
    city: undefined, province: undefined,
    next_of_kin_name: undefined, next_of_kin_phone: undefined,
    ...e,
  })
  return Object.keys(e).length === 0
}

// ─── Navigation ───────────────────────────────────────────────
function back() {
  if (step.value > 1) {
    step.value--
    stopCamera()
  } else {
    router.visit(route('pwa.dashboard'))
  }
}

async function next() {
  if (!validateStep()) return
  if (step.value < totalSteps) {
    step.value++
    if (step.value !== 6) stopCamera()
  } else {
    await submit()
  }
}

// ─── File handling ────────────────────────────────────────────
function triggerCapture(field) {
  if (field === 'id_doc') idDocInput.value?.click()
}

function onFileChange(event, field) {
  const file = event.target.files[0]
  if (!file) return
  files[field] = file
  if (file.type.startsWith('image/')) {
    const reader = new FileReader()
    reader.onload = e => { previews[field] = e.target.result }
    reader.readAsDataURL(file)
  } else {
    previews[field] = null
  }
}

// ─── Camera ───────────────────────────────────────────────────
async function startCamera() {
  try {
    cameraStream = await navigator.mediaDevices.getUserMedia({
      video: { facingMode: 'user', width: { ideal: 1280 }, height: { ideal: 720 } },
    })
    cameraActive.value = true
    await new Promise(r => setTimeout(r, 100))
    if (videoRef.value) videoRef.value.srcObject = cameraStream
  } catch {
    selfieInput.value?.click()
  }
}

function capturePhoto() {
  const video  = videoRef.value
  const canvas = canvasRef.value
  if (!video || !canvas) return
  canvas.width  = video.videoWidth
  canvas.height = video.videoHeight
  canvas.getContext('2d').drawImage(video, 0, 0)
  canvas.toBlob(blob => {
    const file = new File([blob], 'selfie.jpg', { type: 'image/jpeg' })
    files.selfie     = file
    previews.selfie  = canvas.toDataURL('image/jpeg', 0.9)
    stopCamera()
  }, 'image/jpeg', 0.9)
}

function retakeSelfie() {
  files.selfie    = null
  previews.selfie = null
  startCamera()
}

function stopCamera() {
  cameraStream?.getTracks().forEach(t => t.stop())
  cameraStream   = null
  cameraActive.value = false
}

// ─── Submit ───────────────────────────────────────────────────
async function submit() {
  submitting.value = true
  submitError.value = ''
  try {
    // 1. Update borrower profile
    await axios.put('/api/v1/me/profile', {
      first_name:               form.first_name,
      last_name:                form.last_name,
      other_names:              form.other_names || null,
      gender:                   form.gender || null,
      date_of_birth:            form.date_of_birth || null,
      national_id:              form.national_id || null,
      address:                  form.address || null,
      city:                     form.city,
      province:                 form.province,
      country:                  form.country,
      occupation:               form.occupation || null,
      employer:                 form.employer || null,
      next_of_kin_name:         form.next_of_kin_name || null,
      next_of_kin_phone:        form.next_of_kin_phone || null,
      next_of_kin_relationship: form.next_of_kin_relationship || null,
    })

    // 2. Upload ID document
    if (files.id_doc) {
      const fd = new FormData()
      fd.append('file', files.id_doc)
      fd.append('document_type', form.id_doc_type || 'national_id_front')
      await axios.post('/api/v1/me/kyc/upload', fd, {
        headers: { 'Content-Type': 'multipart/form-data' },
      })
    }

    // 3. Upload selfie
    if (files.selfie) {
      const fd = new FormData()
      fd.append('file', files.selfie)
      fd.append('document_type', 'selfie')
      await axios.post('/api/v1/me/kyc/upload', fd, {
        headers: { 'Content-Type': 'multipart/form-data' },
      })
    }

    router.visit(route('pwa.kyc.status'))
  } catch (e) {
    submitError.value = e.response?.data?.message || 'Submission failed. Please try again.'
  } finally {
    submitting.value = false
  }
}

// ─── Helpers ──────────────────────────────────────────────────
function capitalize(s) {
  return s ? s.charAt(0).toUpperCase() + s.slice(1) : '—'
}
</script>

<style scoped>
.safe-bottom { padding-bottom: env(safe-area-inset-bottom, 0px); }
.slide-enter-active, .slide-leave-active { transition: all 0.2s ease; }
.slide-enter-from { opacity: 0; transform: translateX(20px); }
.slide-leave-to   { opacity: 0; transform: translateX(-20px); }
.input {
  width: 100%;
  padding: 0.75rem 1rem;
  border-radius: 0.75rem;
  border: 1px solid #e5e7eb;
  font-size: 0.9375rem;
  outline: none;
  transition: border-color 0.15s;
}
.input:focus { border-color: #10b981; box-shadow: 0 0 0 1px #10b981; }
</style>
