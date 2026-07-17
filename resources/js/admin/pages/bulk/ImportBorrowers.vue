<template>
  <AppLayout>
    <template #header>
      <div>
        <h1 class="text-2xl font-bold text-neutral-900">Import Borrowers</h1>
        <p class="text-sm text-neutral-500 mt-0.5">Upload a CSV file to bulk-create borrower accounts</p>
      </div>
    </template>

    <div class="max-w-2xl space-y-6">

      <!-- Template download -->
      <div class="lendr-card p-6">
        <h2 class="text-base font-semibold text-neutral-800 mb-3">1. Download Template</h2>
        <p class="text-sm text-neutral-500 mb-4">Use the template below as a starting point. Required columns: <code class="bg-neutral-100 px-1 rounded text-xs">first_name</code>, <code class="bg-neutral-100 px-1 rounded text-xs">phone</code>.</p>
        <button @click="downloadTemplate" class="btn-secondary text-sm flex items-center gap-2">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
          </svg>
          Download CSV Template
        </button>
      </div>

      <!-- Upload -->
      <div class="lendr-card p-6">
        <h2 class="text-base font-semibold text-neutral-800 mb-3">2. Upload Your File</h2>

        <div
          class="border-2 border-dashed rounded-xl p-8 text-center transition"
          :class="file ? 'border-emerald-300 bg-emerald-50' : 'border-neutral-200 hover:border-neutral-300'"
          @dragover.prevent
          @drop.prevent="onDrop"
        >
          <input type="file" ref="fileInput" accept=".csv,.txt" class="sr-only" @change="onFileChange" />
          <svg class="w-8 h-8 text-neutral-400 mx-auto mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/>
          </svg>
          <p v-if="!file" class="text-sm text-neutral-500">Drag &amp; drop your CSV here, or</p>
          <button v-if="!file" @click="fileInput.click()" type="button" class="text-sm text-primary-600 hover:underline mt-1">browse files</button>
          <div v-else>
            <p class="font-semibold text-emerald-700 text-sm">{{ file.name }}</p>
            <p class="text-xs text-neutral-500 mt-1">{{ (file.size / 1024).toFixed(1) }} KB</p>
            <button @click="file = null" type="button" class="text-xs text-red-500 hover:underline mt-2">Remove</button>
          </div>
        </div>

        <div class="mt-4 flex gap-3">
          <button
            @click="upload"
            :disabled="!file || uploading"
            class="btn-primary disabled:opacity-50 flex items-center gap-2"
          >
            <svg v-if="uploading" class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
              <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
              <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"/>
            </svg>
            {{ uploading ? 'Importing…' : 'Import Borrowers' }}
          </button>
        </div>
      </div>

      <!-- Results -->
      <div v-if="result" class="lendr-card p-6 space-y-4">
        <h2 class="text-base font-semibold text-neutral-800">3. Import Results</h2>

        <div class="grid grid-cols-3 gap-4">
          <div class="text-center p-3 bg-emerald-50 rounded-xl">
            <p class="text-2xl font-bold text-emerald-700">{{ result.results.imported }}</p>
            <p class="text-xs text-neutral-500 mt-0.5">Imported</p>
          </div>
          <div class="text-center p-3 bg-red-50 rounded-xl">
            <p class="text-2xl font-bold text-red-600">{{ result.results.skipped }}</p>
            <p class="text-xs text-neutral-500 mt-0.5">Skipped</p>
          </div>
          <div class="text-center p-3 bg-neutral-50 rounded-xl">
            <p class="text-2xl font-bold text-neutral-700">{{ result.results.imported + result.results.skipped }}</p>
            <p class="text-xs text-neutral-500 mt-0.5">Total Rows</p>
          </div>
        </div>

        <div v-if="result.results.errors.length" class="space-y-1 max-h-48 overflow-y-auto">
          <p class="text-xs font-semibold text-red-600 uppercase tracking-wide">Errors / Warnings</p>
          <div v-for="(e, i) in result.results.errors" :key="i" class="text-xs text-red-700 bg-red-50 border border-red-100 rounded px-3 py-1.5">
            {{ e }}
          </div>
        </div>
      </div>

    </div>
  </AppLayout>
</template>

<script setup>
import { ref } from 'vue'
import AppLayout from '@/admin/components/layout/AppLayout.vue'
import axios from 'axios'

const props = defineProps({ templateHeaders: Array })

const file      = ref(null)
const fileInput = ref(null)
const uploading = ref(false)
const result    = ref(null)

function onFileChange(e) { file.value = e.target.files[0] ?? null }
function onDrop(e) { file.value = e.dataTransfer.files[0] ?? null }

function downloadTemplate() {
  const csv = props.templateHeaders.join(',') + '\n' + props.templateHeaders.map(() => '').join(',')
  const blob = new Blob([csv], { type: 'text/csv' })
  const url  = URL.createObjectURL(blob)
  const a    = document.createElement('a')
  a.href = url
  a.download = 'borrowers-template.csv'
  a.click()
  URL.revokeObjectURL(url)
}

async function upload() {
  if (!file.value) return
  uploading.value = true
  result.value    = null
  const fd = new FormData()
  fd.append('file', file.value)
  try {
    const { data } = await axios.post(route('bulk.import-borrowers.upload'), fd, {
      headers: { 'Content-Type': 'multipart/form-data' },
    })
    result.value = data
    file.value   = null
  } catch (e) {
    alert(e.response?.data?.message ?? 'Import failed.')
  } finally {
    uploading.value = false
  }
}
</script>
