<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { orderRepository } from '../repositories/OrderRepository';
import { useToast } from '../composables/useToast';

const route = useRoute();
const router = useRouter();
const { showToast } = useToast();

const isLoading = ref(true);
const tracking = ref<any>(null);

const timeline = computed(() => tracking.value?.logs || []);

const formatDate = (value?: string | null) => {
  if (!value) return '-';

  return new Date(value).toLocaleString('id-ID', {
    day: 'numeric',
    month: 'short',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  });
};

const loadTracking = async () => {
  isLoading.value = true;

  try {
    tracking.value = await orderRepository.getTracking(Number(route.params.id));
  } catch (error) {
    console.error('Failed to load tracking', error);
    showToast('Gagal memuat tracking pesanan.', 'error');
    router.push('/orders');
  } finally {
    isLoading.value = false;
  }
};

onMounted(loadTracking);
</script>

<template>
  <div class="min-h-screen hero-bg font-sans selection:bg-amber-900 selection:text-amber-50" style="padding-top: 80px;">
    <main class="max-w-5xl mx-auto px-6 py-16 relative z-10">
      <div v-if="isLoading" class="space-y-4 animate-pulse">
      <div class="h-8 bg-stone-200 w-1/4"></div>
      <div class="h-28 bg-stone-100"></div>
      <div class="h-80 bg-stone-100"></div>
    </div>

    <div v-else-if="tracking" class="space-y-8">
      <section class="bg-white border border-stone-200 p-8 shadow-sm">
        <div class="flex flex-col gap-4 md:flex-row md:items-end md:justify-between">
          <div>
            <p class="text-xs font-black uppercase tracking-[0.24em] text-amber-700 mb-3">Tracking Internal</p>
            <h1 class="text-3xl font-black text-stone-900" style="font-family: 'Outfit', sans-serif;">Pesanan {{ tracking.order_number }}</h1>
            <p class="text-sm text-stone-600 mt-2">Pantau histori perubahan status pesanan langsung dari sistem admin Medio.</p>
          </div>
          <div class="grid gap-3 md:grid-cols-2">
            <div class="border border-stone-200 p-4 min-w-[180px]">
              <p class="text-xs font-black uppercase tracking-[0.16em] text-stone-500 mb-2">Status Saat Ini</p>
              <p class="font-bold text-stone-900 uppercase">{{ tracking.status }}</p>
            </div>
            <div class="border border-stone-200 p-4 min-w-[180px]">
              <p class="text-xs font-black uppercase tracking-[0.16em] text-stone-500 mb-2">Nomor Resi</p>
              <p class="font-bold text-stone-900">{{ tracking.tracking_number || '-' }}</p>
            </div>
          </div>
        </div>
      </section>

      <section class="grid gap-6 lg:grid-cols-[1.5fr,0.9fr]">
        <div class="bg-white border border-stone-200 p-8 shadow-sm">
          <h2 class="text-xl font-black text-stone-900 mb-6" style="font-family: 'Outfit', sans-serif;">Riwayat Pesanan</h2>

          <div v-if="timeline.length === 0" class="text-sm text-stone-500">
            Belum ada riwayat tracking untuk pesanan ini.
          </div>

          <div v-else class="space-y-6">
            <div v-for="(log, index) in timeline" :key="log.id || index" class="flex gap-4">
              <div class="flex flex-col items-center">
                <div class="w-11 h-11 rounded-full bg-stone-900 text-white flex items-center justify-center text-xs font-black">
                  {{ timeline.length - Number(index) }}
                </div>
                <div v-if="Number(index) < timeline.length - 1" class="w-px flex-1 bg-stone-200 mt-2"></div>
              </div>
              <div class="pb-8">
                <p class="text-sm font-black uppercase tracking-[0.12em] text-stone-900">{{ log.action || 'Update' }}</p>
                <p class="text-sm text-stone-600 mt-1 leading-relaxed">{{ log.description || 'Status pesanan diperbarui.' }}</p>
                <div class="mt-3 flex flex-wrap gap-4 text-xs text-stone-500">
                  <span>{{ formatDate(log.created_at) }}</span>
                  <span v-if="log.acted_by?.name">oleh {{ log.acted_by.name }}</span>
                </div>
              </div>
            </div>
          </div>
        </div>

        <div class="space-y-6">
          <div class="bg-stone-950 text-white p-8">
            <h2 class="text-lg font-black mb-4" style="font-family: 'Outfit', sans-serif;">Status Pembayaran</h2>
            <p class="text-sm text-white/75 leading-relaxed">
              {{ tracking.is_payment_verified ? 'Pembayaran sudah diverifikasi admin.' : 'Pembayaran belum diverifikasi admin.' }}
            </p>
          </div>

          <div class="bg-white border border-stone-200 p-8 shadow-sm">
            <h2 class="text-lg font-black text-stone-900 mb-4" style="font-family: 'Outfit', sans-serif;">Aksi Cepat</h2>
            <div class="space-y-3">
              <button @click="router.push(`/orders/${tracking.id}`)" class="w-full px-5 py-3 bg-stone-900 text-white text-sm font-black uppercase tracking-[0.16em]">
                Lihat Detail Pesanan
              </button>
              <button @click="router.push({ name: 'Complaint', query: { order_id: tracking.id } })" class="w-full px-5 py-3 border border-stone-300 text-sm font-black uppercase tracking-[0.16em]">
                Ajukan Komplain
              </button>
            </div>
          </div>
        </div>
      </section>
    </div>
    </main>
  </div>
</template>
