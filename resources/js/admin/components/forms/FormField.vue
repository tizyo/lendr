<template>
  <div :class="$attrs.class">
    <label v-if="label" class="block text-sm font-medium text-neutral-700 mb-1.5">
      {{ label }}
    </label>
    <slot />
    <p v-if="error" class="mt-1 text-xs text-red-600">{{ error }}</p>
    <p v-else-if="hint" class="mt-1 text-xs text-neutral-500">{{ hint }}</p>
  </div>
</template>

<script setup>
defineProps({
  label: String,
  error: String,
  hint: String,
})
defineOptions({ inheritAttrs: false })
</script>
