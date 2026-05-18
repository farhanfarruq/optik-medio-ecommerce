<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { complaintRepository } from '../repositories/ComplaintRepository';
import { orderRepository } from '../repositories/OrderRepository';
import { useToast } from '../composables/useToast';
import { useAuthStore } from '../stores/authStore';
import PageHero from '../components/layout/PageHero.vue';

const route = useRoute();
const router = useRouter();
const authStore = useAuthStore();
const { showToast } = useToast();

const isLoadingOrders = ref(false);
const isSubmitting = ref(false);
const orders = ref<any[]>([]);
const attachment = ref<File | null>(null);
const complaintMode = ref<'general' | 'shipping_protection'>(
  route.query.mode === 'shipping_protection' ? 'shipping_protection' : 'general',
);
const isShippingProtectionMode = computed(() => complaintMode.value === 'shipping_protection');

const form = ref({
  order_id: route.query.order_id ? Number(route.query.order_id) : null as number | null,
  subject: route.query.mode === 'shipping_protection' ? 'Klaim Proteksi Pengiriman' : '',
  message: route.query.mode === 'shipping_protection'
    ? 'Pesanan mengalami kendala saat pengiriman. Mohon bantu proses klaim proteksi pengiriman untuk pesanan ini.'
    : '',
  contact_phone: '',
});

const loadOrders = async () => {
  isLoadingOrders.value = true;

  try {
    const response = await orderRepository.getUserOrders(1, 100);
    orders.value = response.data || [];
  } catch (error) {
    console.error('Failed to load orders', error);
  } finally {
    isLoadingOrders.value = false;
  }
};

const handleAttachment = (event: Event) => {
  const input = event.target as HTMLInputElement;
  attachment.value = input.files?.[0] || null;
};

const submitComplaint = async () => {
  isSubmitting.value = true;

  try {
    await complaintRepository.createComplaint({
      order_id: form.value.order_id,
      complaint_type: complaintMode.value,
      subject: form.value.subject,
      message: form.value.message,
      contact_phone: form.value.contact_phone,
      attachment: attachment.value,
    });

    showToast('Komplain berhasil dikirim.', 'success');
    router.push(form.value.order_id ? `/orders/${form.value.order_id}` : '/profile');
  } catch (error: any) {
    const message = error?.response?.data?.message || 'Gagal mengirim komplain.';
    showToast(message, 'error');
  } finally {
    isSubmitting.value = false;
  }
};

onMounted(async () => {
  if (!authStore.user) {
    await authStore.fetchUser();
  }

  form.value.contact_phone = authStore.user?.phone || '';
  await loadOrders();
});
</script>

<template>
  <div class="min-h-screen bg-ivory">

    <PageHero
      :title="isShippingProtectionMode ? 'Ajukan Klaim Proteksi Pengiriman' : 'Ajukan Komplain Pesanan'"
      :subtitle="isShippingProtectionMode
        ? 'Laporkan paket rusak, hilang, atau bermasalah saat pengiriman pada pesanan berproteksi.'
        : 'Laporkan kendala pesanan, barang rusak, salah kirim, atau kebutuhan retur lanjutan.'"
      :breadcrumbs="[{ label: isShippingProtectionMode ? 'Klaim Proteksi Pengiriman' : 'Komplain' }]"
    />

    <main class="container-premium max-w-4xl pt-24 pb-20">

    <form @submit.prevent="submitComplaint" class="premium-card space-y-6 p-6 sm:p-8" style="border-color: rgba(184,138,68,0.15)">
      <div class="grid gap-6 md:grid-cols-2">
        <div>
          <label class="block text-xs font-black uppercase tracking-[0.18em] text-graphite/65 mb-2">
            {{ isShippingProtectionMode ? 'Pesanan Proteksi' : 'Pesanan Terkait' }}
          </label>
          <select
            v-model="form.order_id"
            class="input-field"
            :disabled="isShippingProtectionMode && !!form.order_id"
          >
            <option :value="null">{{ isLoadingOrders ? 'Memuat pesanan...' : 'Pilih pesanan (opsional)' }}</option>
            <option v-for="order in orders" :key="order.id" :value="order.id">
              {{ order.order_number }} - {{ order.status }}
            </option>
          </select>
        </div>

        <div>
          <label class="block text-xs font-black uppercase tracking-[0.18em] text-graphite/65 mb-2">Nomor Kontak</label>
          <input v-model="form.contact_phone" type="text" class="input-field" placeholder="08xxxxxxxxxx" />
        </div>
      </div>

      <div>
        <label class="block text-xs font-black uppercase tracking-[0.18em] text-graphite/65 mb-2">
          {{ isShippingProtectionMode ? 'Subjek Klaim' : 'Subjek Komplain' }}
        </label>
        <input
          v-model="form.subject"
          required
          type="text"
          class="input-field"
          :readonly="isShippingProtectionMode"
          :placeholder="isShippingProtectionMode ? 'Klaim Proteksi Pengiriman' : 'Contoh: Lensa tidak sesuai pesanan'"
        />
      </div>

      <div>
        <label class="block text-xs font-black uppercase tracking-[0.18em] text-graphite/65 mb-2">Detail Masalah</label>
        <textarea
          v-model="form.message"
          required
          rows="7"
          class="input-field"
          :placeholder="isShippingProtectionMode
            ? 'Jelaskan kondisi paket, jenis kerusakan atau kehilangan, serta kronologi singkatnya.'
            : 'Jelaskan masalah secara detail agar tim admin bisa menindaklanjuti lebih cepat.'"
        ></textarea>
      </div>

      <div>
        <label class="block text-xs font-black uppercase tracking-[0.18em] text-graphite/65 mb-2">Lampiran</label>
        <input type="file" accept=".jpg,.jpeg,.png,.webp,.pdf,.mp4,.mov,.webm" @change="handleAttachment" class="block w-full border border-mist bg-ivory px-4 py-3 text-sm" />
        <p class="text-xs text-graphite/65 mt-2">Boleh berupa foto, video singkat, atau PDF pendukung. Maksimal 15 MB.</p>
      </div>

      <div class="flex flex-col gap-3 sm:flex-row">
        <button type="submit" :disabled="isSubmitting" class="px-6 py-3 text-sm font-black uppercase tracking-[0.16em] disabled:opacity-50" style="background: linear-gradient(135deg, var(--ink) 0%, #3d2c0e 100%); color: #fff;">
          {{ isSubmitting ? 'Mengirim...' : (isShippingProtectionMode ? 'Kirim Klaim Proteksi' : 'Kirim Komplain') }}
        </button>
        <button type="button" @click="router.back()" class="px-6 py-3 border border-mist text-sm font-black uppercase tracking-[0.16em]">
          Kembali
        </button>
      </div>
    </form>

    </main>
  </div>
</template>
