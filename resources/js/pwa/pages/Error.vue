<template>
  <!-- The borrower app's bundle resolves pages from pwa/pages; the screen itself is shared. -->
  <ErrorScreen v-bind="$attrs" />
</template>

<script setup>
import ErrorScreen from '@/admin/pages/Error.vue'

defineOptions({ inheritAttrs: false })
</script>
