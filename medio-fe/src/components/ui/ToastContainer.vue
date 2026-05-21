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
  success: { icon: '#56604B', border: 'rgba(86,96,75,0.24)', bg: '#FCFAF6' },
  error: { icon: '#A33A34', border: 'rgba(163,58,52,0.24)', bg: '#FFF7F5' },
  info: { icon: '#3F6F8F', border: 'rgba(63,111,143,0.24)', bg: '#F4FAFC' },
  warning: { icon: '#B88A44', border: 'rgba(184,138,68,0.28)', bg: '#FCFAF6' },
};
</script>

<template>
  <Teleport to="body">
    <div class="fixed bottom-20 right-4 z-[9999] flex w-[calc(100%-2rem)] max-w-[380px] flex-col gap-3 pointer-events-none md:bottom-6 md:right-6">
      <TransitionGroup name="toast">
        <div
          v-for="toast in toasts"
          :key="toast.id"
          class="flex cursor-pointer items-start gap-3 rounded-lg px-4 py-3 shadow-soft pointer-events-auto"
          :style="{ background: styleMap[toast.type].bg, border: '1px solid ' + styleMap[toast.type].border, color: '#15120E' }"
          @click="removeToast(toast.id)"
        >
          <span class="material-symbols-outlined mt-0.5 shrink-0 text-xl" style="font-variation-settings: 'FILL' 1, 'wght' 500;" :style="{ color: styleMap[toast.type].icon }">{{ iconMap[toast.type] }}</span>
          <p class="flex-grow text-sm font-semibold leading-snug text-ink">{{ toast.message }}</p>
          <button @click.stop="removeToast(toast.id)" class="material-symbols-outlined shrink-0 rounded-full p-0.5 text-lg text-graphite/55 transition-colors hover:bg-ivory hover:text-ink" aria-label="Tutup notifikasi">close</button>
        </div>
      </TransitionGroup>
    </div>
  </Teleport>
</template>

<style scoped>
.toast-enter-active { transition: all 0.28s ease; }
.toast-leave-active { transition: all 0.2s ease-in; }
.toast-enter-from { opacity: 0; transform: translateY(8px); }
.toast-leave-to { opacity: 0; transform: translateY(8px); }
.toast-move { transition: transform 0.25s ease; }
</style>
