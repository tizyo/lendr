<template>
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
</template>

<script setup>
// A single-file component: the app ships Vue's runtime-only build, so the inline
// `template:` string this used to be was never compiled and the field never showed.
defineProps(['label', 'modelValue', 'placeholder', 'error', 'disabled', 'type'])
defineEmits(['update:modelValue'])
</script>
