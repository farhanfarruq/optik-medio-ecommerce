<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { complaintRepository } from '../repositories/ComplaintRepository';
import PageHero from '../components/layout/PageHero.vue';

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
  return map[complain.value?.status] ?? '#8a7a60';
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
      <span class="material-symbols-outlined animate-spin text-4xl" style="color: #c19a51;">sync</span>
    </div>

    <!-- Error -->
    <div v-else-if="error" class="text-center py-24">
      <p class="text-sm font-medium" style="color: #dc2626;">{{ error }}</p>
      <button @click="router.back()" class="mt-4 text-xs font-black uppercase tracking-widest underline" style="color: #8a7a60;">Kembali</button>
    </div>

    <template v-else-if="complain">
      <PageHero
        :title="complain.subject"
        :breadcrumbs="breadcrumbs"
        :backTo="backTo"
        :backLabel="backLabel"
      />

      <main class="max-w-3xl mx-auto px-6 py-10">
        <!-- Header -->
        <div class="border p-6 mb-6" style="background: white; border-color: rgba(193,154,81,0.2); box-shadow: 0 2px 12px rgba(0,0,0,0.04);">
          <div class="flex items-start justify-between gap-4 flex-wrap">
            <div>
              <p class="text-[10px] font-black uppercase tracking-[0.2em] mb-1" style="color: #8a7a60;">Komplain #{{ complain.id }}</p>
              <h1 class="text-xl font-black" style="color: #1a1209; font-family: 'Outfit', sans-serif;">{{ complain.subject }}</h1>
            </div>
            <span class="text-xs font-black uppercase tracking-wider px-3 py-1.5 rounded-none"
              :style="`background: ${statusColor}18; color: ${statusColor}; border: 1px solid ${statusColor}40;`">
              {{ statusLabel }}
            </span>
          </div>

          <div class="mt-4 grid grid-cols-2 gap-3 text-xs" style="color: #8a7a60;">
            <div>
              <span class="font-black uppercase tracking-wider">Dikirim</span>
              <p class="mt-0.5" style="color: #1a1209;">{{ formatDate(complain.created_at) }}</p>
            </div>
            <div v-if="complain.order">
              <span class="font-black uppercase tracking-wider">Pesanan</span>
              <p class="mt-0.5" style="color: #1a1209;">{{ complain.order.order_number }}</p>
            </div>
            <div v-if="complain.resolved_at">
              <span class="font-black uppercase tracking-wider">Diselesaikan</span>
              <p class="mt-0.5" style="color: #1a1209;">{{ formatDate(complain.resolved_at) }}</p>
            </div>
            <div v-if="complain.contact_phone">
              <span class="font-black uppercase tracking-wider">Kontak</span>
              <p class="mt-0.5" style="color: #1a1209;">{{ complain.contact_phone }}</p>
            </div>
          </div>
        </div>

        <!-- Pesan Komplain -->
        <div class="border p-6 mb-6" style="background: white; border-color: rgba(193,154,81,0.15);">
          <p class="text-[10px] font-black uppercase tracking-[0.2em] mb-3" style="color: #8a7a60;">Pesan Komplain Anda</p>
          <p class="text-sm leading-relaxed whitespace-pre-line" style="color: #1a1209;">{{ complain.message }}</p>

          <!-- Lampiran -->
          <div v-if="complain.attachment_path" class="mt-4 pt-4 border-t" style="border-color: #f0ece4;">
            <p class="text-[10px] font-black uppercase tracking-[0.2em] mb-2" style="color: #8a7a60;">Lampiran</p>
            <a :href="`${$apiBase?.replace('/api','')}/storage/${complain.attachment_path}`"
               target="_blank"
               class="inline-flex items-center gap-1.5 text-xs font-bold underline"
               style="color: #c19a51;">
              <span class="material-symbols-outlined text-sm">attach_file</span>
              Lihat Lampiran
            </a>
          </div>
        </div>

        <!-- Respons Admin -->
        <div class="border p-6 mb-6" :style="`background: ${complain.admin_notes ? '#fffdf7' : 'white'}; border-color: ${complain.admin_notes ? 'rgba(193,154,81,0.4)' : 'rgba(193,154,81,0.15)'};`">
          <div class="flex items-center gap-2 mb-3">
            <span class="material-symbols-outlined text-base" style="color: #c19a51;">support_agent</span>
            <p class="text-[10px] font-black uppercase tracking-[0.2em]" style="color: #8a7a60;">Respons Tim Optik Medio</p>
          </div>

          <div v-if="complain.admin_notes">
            <p class="text-sm leading-relaxed whitespace-pre-line" style="color: #1a1209;">{{ complain.admin_notes }}</p>
            <p v-if="complain.handledBy" class="mt-3 text-xs" style="color: #8a7a60;">
              — {{ complain.handledBy.name }}
            </p>
          </div>
          <div v-else class="flex items-center gap-2 py-2">
            <span class="material-symbols-outlined text-sm" style="color: #c19a51;">schedule</span>
            <p class="text-sm" style="color: #8a7a60;">
              Tim kami sedang meninjau komplain Anda. Anda akan mendapat notifikasi email saat ada pembaruan.
            </p>
          </div>
        </div>
      </main>
    </template>
  </div>
</template>
