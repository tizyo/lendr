<template>
  <div class="min-h-screen bg-neutral-50 flex flex-col items-center justify-center px-5">
    <div class="text-center">
      <p class="text-5xl mb-4">✅</p>
      <h2 class="text-xl font-bold text-neutral-900">Signed in!</h2>
      <p class="text-neutral-500 text-sm mt-1">Redirecting you back…</p>
    </div>
  </div>
</template>

<script setup>
import { onMounted } from 'vue'
import { router } from '@inertiajs/vue3'
onMounted(() => {
  const redirect = new URLSearchParams(window.location.search).get('redirect')
  setTimeout(() => router.visit(redirect ?? '/app/repo'), 1200)
})
</script>
