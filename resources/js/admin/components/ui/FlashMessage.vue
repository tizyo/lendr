<template>
  <!-- Floating toast stack, top-right, above drawers and modals. -->
  <Teleport to="body">
    <div class="fixed top-4 right-4 left-4 sm:left-auto z-[100] flex flex-col gap-3 sm:w-96 pointer-events-none" aria-live="polite">
      <TransitionGroup name="toast">
        <div
          v-for="t in toasts"
          :key="t.id"
          :role="t.type === 'error' ? 'alert' : 'status'"
          class="toast pointer-events-auto relative overflow-hidden rounded-2xl bg-white dark:bg-neutral-800 shadow-2xl shadow-black/10 ring-1 ring-black/5 dark:ring-white/10"
          @mouseenter="paused = t.id"
          @mouseleave="paused = null"
        >
          <!-- Accent edge -->
          <div class="absolute inset-y-0 left-0 w-1" :class="styles[t.type].bar"></div>

          <div class="flex items-start gap-3 py-3.5 pl-5 pr-3">
            <div class="shrink-0 w-9 h-9 rounded-full flex items-center justify-center" :class="styles[t.type].badge">
              <component :is="icons[t.type]" class="w-5 h-5" />
            </div>

            <div class="flex-1 min-w-0 pt-0.5">
              <p class="text-sm font-semibold text-neutral-900 dark:text-white">{{ t.title ?? titles[t.type] }}</p>
              <p class="mt-0.5 text-sm leading-snug text-neutral-600 dark:text-neutral-300 break-words">{{ t.text }}</p>
            </div>

            <button
              type="button"
              @click="dismiss(t.id)"
              class="shrink-0 p-1 rounded-lg text-neutral-400 hover:text-neutral-700 hover:bg-neutral-100 dark:hover:text-white dark:hover:bg-neutral-700 transition"
              aria-label="Dismiss"
            >
              <XMarkIcon class="w-4 h-4" />
            </button>
          </div>

          <!-- Time left; hovering pauses it, and its end dismisses the toast. -->
          <div class="h-1 bg-neutral-100 dark:bg-neutral-700">
            <div
              class="toast-progress h-full"
              :class="styles[t.type].bar"
              :style="{ animationDuration: t.duration + 'ms', animationPlayState: paused === t.id ? 'paused' : 'running' }"
              @animationend="dismiss(t.id)"
            ></div>
          </div>
        </div>
      </TransitionGroup>
    </div>
  </Teleport>
</template>

<script setup>
import { ref, watch, markRaw } from 'vue'
import { usePage } from '@inertiajs/vue3'
import {
  CheckCircleIcon, XCircleIcon, ExclamationTriangleIcon,
  InformationCircleIcon, XMarkIcon,
} from '@heroicons/vue/24/solid'
import { useToast } from '@/admin/composables/useToast.js'

const page = usePage()
const { items: toasts, dismiss, ...toast } = useToast()
const paused = ref(null)

const titles = { success: 'Success', error: 'Something went wrong', warning: 'Heads up', info: 'Note' }

const icons = {
  success: markRaw(CheckCircleIcon),
  error: markRaw(XCircleIcon),
  warning: markRaw(ExclamationTriangleIcon),
  info: markRaw(InformationCircleIcon),
}

const styles = {
  success: { bar: 'bg-emerald-500', badge: 'bg-emerald-50 text-emerald-600 dark:bg-emerald-500/15 dark:text-emerald-400' },
  error:   { bar: 'bg-red-500',     badge: 'bg-red-50 text-red-600 dark:bg-red-500/15 dark:text-red-400' },
  warning: { bar: 'bg-amber-500',   badge: 'bg-amber-50 text-amber-600 dark:bg-amber-500/15 dark:text-amber-400' },
  info:    { bar: 'bg-blue-500',    badge: 'bg-blue-50 text-blue-600 dark:bg-blue-500/15 dark:text-blue-400' },
}

// Server flash messages (redirect()->with('success', ...)) become toasts.
watch(() => page.props.flash, (flash) => {
  if (!flash) return
  for (const type of ['success', 'error', 'warning', 'info']) {
    if (flash[type]) toast[type](flash[type])
  }
}, { immediate: true, deep: true })
</script>

<style scoped>
.toast-progress {
  width: 100%;
  transform-origin: left;
  animation-name: toast-countdown;
  animation-timing-function: linear;
  animation-fill-mode: forwards;
}
@keyframes toast-countdown {
  from { transform: scaleX(1); }
  to   { transform: scaleX(0); }
}

.toast-enter-active { transition: all 0.3s cubic-bezier(0.21, 1.02, 0.73, 1); }
.toast-leave-active { transition: all 0.2s ease-in; }
.toast-enter-from { opacity: 0; transform: translateX(24px) scale(0.98); }
.toast-leave-to   { opacity: 0; transform: translateX(24px); }
.toast-move       { transition: transform 0.25s ease; }

@media (prefers-reduced-motion: reduce) {
  .toast-enter-active, .toast-leave-active, .toast-move { transition: none; }
}
</style>
