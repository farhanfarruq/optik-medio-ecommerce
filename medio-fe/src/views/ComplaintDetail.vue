<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { complaintRepository } from '../repositories/ComplaintRepository';
import PageHero from '../components/layout/PageHero.vue';
import { apiOrigin } from '../core/api/axiosclient';

const route = useRoute();
const router = useRouter();

const complain = ref<any>(null);
const isLoading = ref(true);
const error = ref('');
const statusLabel = computed(() => {
  const map: Record<string, string> = {
    open: 'Menunggu Tindakan',
    in_progress: 'Sedang Diproses',
    resolved: 'Telah Diselesaikan',
    rejected: 'Tidak Dapat Diproses',
  };
  return map[complain.value?.status] ?? complain.value?.status ?? '-';
});

const statusColor = computed(() => {
  const map: Record<string, string> = {
    open: '#d97706',
    in_progress: '#2563eb',
    resolved: '#16a34a',
    rejected: '#dc2626',
  };
  return map[complain.value?.status] ?? '#5c4a3a';
});

const statusTimeline = computed(() => {
  const status = complain.value?.status;
  return [
    { label: 'Diterima', done: true, note: formatDate(complain.value?.created_at) },
    { label: 'Ditinjau', done: ['in_progress', 'resolved', 'rejected'].includes(status), note: 'Validasi bukti dan pesanan' },
    { label: 'Resolusi', done: ['resolved', 'rejected'].includes(status), note: complain.value?.resolved_at ? formatDate(complain.value.resolved_at) : 'Menunggu keputusan tim' },
  ];
});

const breadcrumbs = computed(() => {
  if (complain.value?.order) {
    return [
      { label: 'Pesanan', to: '/orders' },
      { label: complain.value.order.order_number, to: `/orders/${complain.value.order_id}` },
      { label: 'Detail Komplain' },
    ];
  }
  return [{ label: 'Komplain' }];
});

const backTo = computed(() =>
  complain.value?.order ? `/orders/${complain.value.order_id}` : '/orders'
);

const backLabel = computed(() =>
  complain.value?.order ? 'Kembali ke Pesanan' : 'Kembali ke Pesanan Saya'
);

const complaintTypeLabel = computed(() =>
  complain.value?.complaint_type === 'shipping_protection' ? 'Klaim Proteksi Pengiriman' : 'Komplain Umum'
);

const formatDate = (val: string) =>
  val ? new Date(val).toLocaleDateString('id-ID', { day: 'numeric', month: 'long', year: 'numeric', hour: '2-digit', minute: '2-digit' }) : '-';

onMounted(async () => {
  try {
    complain.value = await complaintRepository.getComplaint(Number(route.params.id));
  } catch {
    error.value = 'Komplain tidak ditemukan.';
  } finally {
    isLoading.value = false;
  }
});
</script>

<template>
  <div>
    <!-- Loading -->
    <div v-if="isLoading" class="flex justify-center py-24">
      <span class="material-symbols-outlined animate-spin text-4xl" style="color: var(--gold);">sync</span>
    </div>

    <!-- Error -->
    <div v-else-if="error" class="text-center py-24">
      <p class="text-sm font-medium" style="color: #dc2626;">{{ error }}</p>
      <button @click="router.back()" class="mt-4 text-xs font-black uppercase tracking-widest underline" style="color: #5c4a3a;">Kembali</button>
    </div>

    <template v-else-if="complain">
      <PageHero
        :title="complain.subject"
        :breadcrumbs="breadcrumbs"
        :backTo="backTo"
        :backLabel="backLabel"
      />

      <main class="max-w-3xl mx-auto px-6 pt-8 pb-10">
        <!-- Header -->
        <div class="premium-card mb-6 p-6">
          <div class="mb-3">
            <span class="inline-flex px-3 py-1 text-[10px] font-black uppercase tracking-[0.18em]" :style="`background: ${complain.complaint_type === 'shipping_protection' ? 'rgba(37,99,235,0.1)' : 'rgba(184,138,68,0.1)'}; color: ${complain.complaint_type === 'shipping_protection' ? '#1d4ed8' : '#5c4a3a'};`">
              {{ complaintTypeLabel }}
            </span>
          </div>
          <div class="flex items-start justify-between gap-4 flex-wrap">
            <div>
              <p class="text-[10px] font-black uppercase tracking-[0.2em] mb-1" style="color: #5c4a3a;">Komplain #{{ complain.id }}</p>
              <h1 class="text-xl font-black" style="color: var(--ink); font-family: 'Plus Jakarta Sans', Inter, system-ui, sans-serif;">{{ complain.subject }}</h1>
            </div>
            <span class="text-xs font-black uppercase tracking-wider px-3 py-1.5 rounded-lg"
              :style="`background: ${statusColor}18; color: ${statusColor}; border: 1px solid ${statusColor}40;`">
              {{ statusLabel }}
            </span>
          </div>

          <div class="mt-4 grid grid-cols-2 gap-3 text-xs" style="color: #5c4a3a;">
            <div>
              <span class="font-black uppercase tracking-wider">Dikirim</span>
              <p class="mt-0.5" style="color: var(--ink);">{{ formatDate(complain.created_at) }}</p>
            </div>
            <div v-if="complain.order">
              <span class="font-black uppercase tracking-wider">Pesanan</span>
              <p class="mt-0.5" style="color: var(--ink);">{{ complain.order.order_number }}</p>
            </div>
            <div v-if="complain.resolved_at">
              <span class="font-black uppercase tracking-wider">Diselesaikan</span>
              <p class="mt-0.5" style="color: var(--ink);">{{ formatDate(complain.resolved_at) }}</p>
            </div>
            <div v-if="complain.contact_phone">
              <span class="font-black uppercase tracking-wider">Kontak</span>
              <p class="mt-0.5" style="color: var(--ink);">{{ complain.contact_phone }}</p>
            </div>
          </div>
        </div>

        <div class="premium-card mb-6 p-6">
          <p class="text-[10px] font-black uppercase tracking-[0.2em] mb-4" style="color: #5c4a3a;">Timeline Penanganan</p>
          <div class="grid gap-3 sm:grid-cols-3">
            <div v-for="item in statusTimeline" :key="item.label" class="rounded-lg border p-4" :style="item.done ? 'background: rgba(184,138,68,0.08); border-color: rgba(184,138,68,0.28);' : 'background: white; border-color: var(--mist);'">
              <div class="flex items-center gap-2">
                <span class="material-symbols-outlined text-base" :style="item.done ? 'color: var(--gold);' : 'color: #5c4a3a;'">{{ item.done ? 'check_circle' : 'radio_button_unchecked' }}</span>
                <p class="text-xs font-black uppercase tracking-[0.14em]" style="color: var(--ink);">{{ item.label }}</p>
              </div>
              <p class="mt-2 text-xs leading-relaxed" style="color: #5c4a3a;">{{ item.note }}</p>
            </div>
          </div>
        </div>

        <!-- Pesan Komplain -->
        <div class="premium-card mb-6 p-6">
          <p class="text-[10px] font-black uppercase tracking-[0.2em] mb-3" style="color: #5c4a3a;">Pesan Komplain Anda</p>
          <p class="text-sm leading-relaxed whitespace-pre-line" style="color: var(--ink);">{{ complain.message }}</p>

          <!-- Lampiran -->
          <div v-if="complain.attachment_path" class="mt-4 pt-4 border-t" style="border-color: #f0ece4;">
            <p class="text-[10px] font-black uppercase tracking-[0.2em] mb-2" style="color: #5c4a3a;">Evidence / Lampiran</p>
            <a :href="`${apiOrigin}/storage/${complain.attachment_path}`"
               target="_blank"
               class="inline-flex items-center gap-1.5 text-xs font-bold underline"
               style="color: var(--gold);">
              <span class="material-symbols-outlined text-sm">attach_file</span>
              Lihat Lampiran
            </a>
          </div>
        </div>

        <!-- Respons Admin -->
        <div class="border p-6 mb-6" :style="`background: ${complain.admin_notes ? 'var(--porcelain)' : 'white'}; border-color: ${complain.admin_notes ? 'rgba(184,138,68,0.4)' : 'rgba(184,138,68,0.15)'};`">
          <div class="flex items-center gap-2 mb-3">
            <span class="material-symbols-outlined text-base" style="color: var(--gold);">support_agent</span>
            <p class="text-[10px] font-black uppercase tracking-[0.2em]" style="color: #5c4a3a;">Resolusi Tim Optik Medio</p>
          </div>

          <div v-if="complain.admin_notes">
            <p class="text-sm leading-relaxed whitespace-pre-line" style="color: var(--ink);">{{ complain.admin_notes }}</p>
            <p v-if="complain.handledBy" class="mt-3 text-xs" style="color: #5c4a3a;">
              — {{ complain.handledBy.name }}
            </p>
          </div>
          <div v-else class="flex items-center gap-2 py-2">
            <span class="material-symbols-outlined text-sm" style="color: var(--gold);">schedule</span>
            <p class="text-sm" style="color: #5c4a3a;">
              Tim kami sedang meninjau komplain Anda. Anda akan mendapat notifikasi email saat ada pembaruan.
            </p>
          </div>
        </div>
      </main>
    </template>
  </div>
</template>
