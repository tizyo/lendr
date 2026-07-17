<template>
  <PwaLayout title="KYC Status" :show-back="true">
    <div class="px-4 py-6 space-y-5">

      <!-- Overall status card -->
      <div class="rounded-2xl p-5 flex items-center gap-4"
        :class="{
          'bg-emerald-50 border border-emerald-100': overallStatus === 'verified',
          'bg-amber-50  border border-amber-100':   overallStatus === 'pending',
          'bg-red-50    border border-red-100':     overallStatus === 'rejected',
          'bg-gray-50   border border-gray-100':    overallStatus === 'none',
        }"
      >
        <div class="w-14 h-14 rounded-full flex items-center justify-center shrink-0"
          :class="{
            'bg-emerald-100': overallStatus === 'verified',
            'bg-amber-100':   overallStatus === 'pending',
            'bg-red-100':     overallStatus === 'rejected',
            'bg-gray-100':    overallStatus === 'none',
          }"
        >
          <svg v-if="overallStatus === 'verified'" class="w-8 h-8 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
          </svg>
          <svg v-else-if="overallStatus === 'pending'" class="w-8 h-8 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
          </svg>
          <svg v-else-if="overallStatus === 'rejected'" class="w-8 h-8 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"/>
          </svg>
          <svg v-else class="w-8 h-8 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
          </svg>
        </div>
        <div>
          <p class="text-xs font-semibold uppercase tracking-wide"
            :class="{
              'text-emerald-600': overallStatus === 'verified',
              'text-amber-600':   overallStatus === 'pending',
              'text-red-600':     overallStatus === 'rejected',
              'text-gray-500':    overallStatus === 'none',
            }"
          >KYC {{ overallStatusLabel }}</p>
          <p class="text-base font-bold text-gray-900 mt-0.5">{{ overallTitle }}</p>
          <p class="text-sm text-gray-500 mt-0.5">{{ overallMessage }}</p>
        </div>
      </div>

      <!-- Documents list -->
      <div v-if="documents.length" class="space-y-3">
        <h3 class="text-sm font-semibold text-gray-700">Your Documents</h3>
        <div
          v-for="doc in documents"
          :key="doc.id"
          class="bg-white rounded-xl border border-gray-100 shadow-sm p-4 flex items-center justify-between gap-3"
        >
          <div class="flex items-center gap-3 min-w-0">
            <div class="w-10 h-10 bg-gray-50 rounded-lg flex items-center justify-center shrink-0">
              <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
              </svg>
            </div>
            <div class="min-w-0">
              <p class="text-sm font-medium text-gray-900 capitalize">
                {{ doc.document_type.replace(/_/g, ' ') }}
              </p>
              <p class="text-xs text-gray-500">Uploaded {{ doc.created_at }}</p>
              <p v-if="doc.rejection_reason" class="text-xs text-red-600 mt-0.5">
                {{ doc.rejection_reason }}
              </p>
            </div>
          </div>
          <span
            class="text-xs px-2.5 py-1 rounded-full font-semibold shrink-0"
            :class="{
              'bg-emerald-100 text-emerald-700': doc.status === 'verified',
              'bg-amber-100 text-amber-700':     doc.status === 'pending',
              'bg-red-100 text-red-700':         doc.status === 'rejected',
              'bg-gray-100 text-gray-600':       doc.status === 'expired',
            }"
          >{{ doc.status_label }}</span>
        </div>
      </div>

      <!-- CTA buttons -->
      <div class="space-y-3 pt-2">
        <button
          v-if="overallStatus === 'none' || overallStatus === 'rejected'"
          @click="$inertia.visit(route('pwa.kyc.onboarding'))"
          class="w-full py-4 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-semibold text-base transition"
        >
          {{ overallStatus === 'rejected' ? 'Re-submit KYC' : 'Start KYC Verification' }}
        </button>

        <button
          @click="$inertia.visit(route('pwa.dashboard'))"
          class="w-full py-3.5 rounded-xl border border-gray-200 text-gray-700 font-medium text-base transition hover:bg-gray-50"
        >
          Back to Dashboard
        </button>
      </div>
    </div>
  </PwaLayout>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import axios from 'axios'
import PwaLayout from '@/pwa/components/PwaLayout.vue'

const props = defineProps({
  kycDocuments: { type: Array, default: () => [] },
  kycVerified:  { type: Boolean, default: false },
})

const documents = ref(props.kycDocuments ?? [])
const loading   = ref(false)

const overallStatus = computed(() => {
  if (props.kycVerified) return 'verified'
  if (!documents.value.length) return 'none'
  const statuses = documents.value.map(d => d.status)
  if (statuses.every(s => s === 'verified')) return 'verified'
  if (statuses.some(s => s === 'rejected')) return 'rejected'
  return 'pending'
})

const overallStatusLabel = computed(() => ({
  verified: 'Verified',
  pending:  'Under Review',
  rejected: 'Action Required',
  none:     'Not Started',
})[overallStatus.value])

const overallTitle = computed(() => ({
  verified: 'Your identity is verified!',
  pending:  'Under review',
  rejected: 'Documents rejected',
  none:     'Complete your verification',
})[overallStatus.value])

const overallMessage = computed(() => ({
  verified: 'You are now eligible to apply for loans.',
  pending:  'We are reviewing your documents. This usually takes up to 24 hours.',
  rejected: 'Some documents were rejected. Please re-submit with clearer photos.',
  none:     'Verify your identity to unlock full access to loans.',
})[overallStatus.value])
</script>
