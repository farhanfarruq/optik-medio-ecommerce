<script setup lang="ts">
import { useToast } from '../../composables/useToast';

const { toasts, removeToast } = useToast();

const iconMap = {
  success: 'check_circle',
  error: 'error',
  info: 'info',
  warning: 'warning',
};

const styleMap = {
  success: {
    bg: 'linear-gradient(135deg, #166534, #15803d)',
    icon: '#86efac',
    border: 'rgba(134,239,172,0.25)',
  },
  error: {
    bg: 'linear-gradient(135deg, #7f1d1d, #991b1b)',
    icon: '#fca5a5',
    border: 'rgba(252,165,165,0.25)',
  },
  info: {
    bg: 'linear-gradient(135deg, #1e3a5f, #1d4ed8)',
    icon: '#93c5fd',
    border: 'rgba(147,197,253,0.25)',
  },
  warning: {
    bg: 'linear-gradient(135deg, #78350f, #b45309)',
    icon: '#fcd34d',
    border: 'rgba(252,211,77,0.25)',
  },
};
</script>

<template>
  <Teleport to="body">
    <div class="fixed bottom-6 right-6 z-[9999] flex flex-col gap-3 pointer-events-none" style="max-width: 380px;">
      <TransitionGroup name="toast">
        <div
          v-for="toast in toasts"
          :key="toast.id"
          class="flex items-start gap-4 px-5 py-4 rounded-2xl shadow-2xl pointer-events-auto cursor-pointer"
          :style="`
            background: ${styleMap[toast.type].bg};
            border: 1px solid ${styleMap[toast.type].border};
            backdrop-filter: blur(12px);
            box-shadow: 0 8px 32px rgba(0,0,0,0.35), 0 2px 8px rgba(0,0,0,0.2);
          `"
          @click="removeToast(toast.id)"
        >
          <!-- Icon -->
          <span
            class="material-symbols-outlined text-xl mt-0.5 shrink-0"
            style="font-variation-settings: 'FILL' 1, 'wght' 500;"
            :style="`color: ${styleMap[toast.type].icon};`"
          >
            {{ iconMap[toast.type] }}
          </span>

          <!-- Message -->
          <p class="text-sm font-semibold leading-snug text-white flex-grow">
            {{ toast.message }}
          </p>

          <!-- Close -->
          <button
            @click.stop="removeToast(toast.id)"
            class="material-symbols-outlined text-lg shrink-0 transition-opacity hover:opacity-70"
            style="color: rgba(255,255,255,0.6);"
          >
            close
          </button>
        </div>
      </TransitionGroup>
    </div>
  </Teleport>
</template>

<style scoped>
.toast-enter-active {
  transition: all 0.35s cubic-bezier(0.34, 1.56, 0.64, 1);
}
.toast-leave-active {
  transition: all 0.25s ease-in;
}
.toast-enter-from {
  opacity: 0;
  transform: translateX(60px) scale(0.9);
}
.toast-leave-to {
  opacity: 0;
  transform: translateX(60px) scale(0.95);
}
.toast-move {
  transition: transform 0.3s ease;
}
</style>
