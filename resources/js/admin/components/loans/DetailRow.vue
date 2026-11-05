<template>
  <div class="flex justify-between gap-4">
    <dt class="text-neutral-500 shrink-0">{{ label }}</dt>
    <dd class="text-neutral-900 font-medium text-right">{{ value }}</dd>
  </div>
</template>

<script setup>
// A single-file component: the app ships Vue's runtime-only build, so an inline
// `template:` string (as this used to be) is never compiled and renders nothing.
defineProps({ label: String, value: [String, Number] })
</script>
