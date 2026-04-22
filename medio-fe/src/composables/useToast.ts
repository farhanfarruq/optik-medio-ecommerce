import { ref } from 'vue';

export type ToastType = 'success' | 'error' | 'info' | 'warning';

export interface Toast {
  id: number;
  message: string;
  type: ToastType;
}

const toasts = ref<Toast[]>([]);
let nextId = 0;

export function useToast() {
  function showToast(message: string, type: ToastType = 'info', duration = 4000) {
    const id = ++nextId;
    toasts.value.push({ id, message, type });
    setTimeout(() => {
      toasts.value = toasts.value.filter(t => t.id !== id);
    }, duration);
  }

  function removeToast(id: number) {
    toasts.value = toasts.value.filter(t => t.id !== id);
  }

  return { toasts, showToast, removeToast };
}
