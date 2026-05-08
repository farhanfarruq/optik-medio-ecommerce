<script setup lang="ts">
import { onMounted, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { complaintRepository } from '../repositories/ComplaintRepository';
import { orderRepository } from '../repositories/OrderRepository';
import { useToast } from '../composables/useToast';
import { useAuthStore } from '../stores/authStore';

const route = useRoute();
const router = useRouter();
const authStore = useAuthStore();
const { showToast } = useToast();

const isLoadingOrders = ref(false);
const isSubmitting = ref(false);
const orders = ref<any[]>([]);
const attachment = ref<File | null>(null);

const form = ref({
  order_id: route.query.order_id ? Number(route.query.order_id) : null as number | null,
  subject: '',
  message: '',
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
  <main class="max-w-4xl mx-auto px-6 py-16">
    <section class="bg-white border border-stone-200 p-8 shadow-sm mb-8">
      <p class="text-xs font-black uppercase tracking-[0.24em] text-amber-700 mb-3">Helpdesk & Komplain</p>
      <h1 class="text-3xl font-black text-stone-900 mb-3" style="font-family: 'Outfit', sans-serif;">Ajukan Komplain Pesanan</h1>
      <p class="text-sm text-stone-600 max-w-2xl leading-relaxed">
        Gunakan formulir ini untuk melaporkan kendala pesanan, barang rusak, salah kirim, atau kebutuhan retur lanjutan.
      </p>
    </section>

    <form @submit.prevent="submitComplaint" class="bg-white border border-stone-200 p-8 shadow-sm space-y-6">
      <div class="grid gap-6 md:grid-cols-2">
        <div>
          <label class="block text-xs font-black uppercase tracking-[0.18em] text-stone-500 mb-2">Pesanan Terkait</label>
          <select v-model="form.order_id" class="w-full border border-stone-300 bg-stone-50 px-4 py-3 text-sm">
            <option :value="null">{{ isLoadingOrders ? 'Memuat pesanan...' : 'Pilih pesanan (opsional)' }}</option>
            <option v-for="order in orders" :key="order.id" :value="order.id">
              {{ order.order_number }} - {{ order.status }}
            </option>
          </select>
        </div>

        <div>
          <label class="block text-xs font-black uppercase tracking-[0.18em] text-stone-500 mb-2">Nomor Kontak</label>
          <input v-model="form.contact_phone" type="text" class="w-full border border-stone-300 bg-stone-50 px-4 py-3 text-sm" placeholder="08xxxxxxxxxx" />
        </div>
      </div>

      <div>
        <label class="block text-xs font-black uppercase tracking-[0.18em] text-stone-500 mb-2">Subjek Komplain</label>
        <input v-model="form.subject" required type="text" class="w-full border border-stone-300 bg-stone-50 px-4 py-3 text-sm" placeholder="Contoh: Lensa tidak sesuai pesanan" />
      </div>

      <div>
        <label class="block text-xs font-black uppercase tracking-[0.18em] text-stone-500 mb-2">Detail Masalah</label>
        <textarea v-model="form.message" required rows="7" class="w-full border border-stone-300 bg-stone-50 px-4 py-3 text-sm" placeholder="Jelaskan masalah secara detail agar tim admin bisa menindaklanjuti lebih cepat."></textarea>
      </div>

      <div>
        <label class="block text-xs font-black uppercase tracking-[0.18em] text-stone-500 mb-2">Lampiran</label>
        <input type="file" accept=".jpg,.jpeg,.png,.pdf" @change="handleAttachment" class="block w-full border border-stone-300 bg-stone-50 px-4 py-3 text-sm" />
        <p class="text-xs text-stone-500 mt-2">Boleh berupa foto produk, bukti kerusakan, atau PDF pendukung. Maksimal 4 MB.</p>
      </div>

      <div class="flex flex-col gap-3 sm:flex-row">
        <button type="submit" :disabled="isSubmitting" class="px-6 py-3 bg-stone-900 text-white text-sm font-black uppercase tracking-[0.16em] disabled:opacity-50">
          {{ isSubmitting ? 'Mengirim...' : 'Kirim Komplain' }}
        </button>
        <button type="button" @click="router.back()" class="px-6 py-3 border border-stone-300 text-sm font-black uppercase tracking-[0.16em]">
          Kembali
        </button>
      </div>
    </form>
  </main>
</template>
