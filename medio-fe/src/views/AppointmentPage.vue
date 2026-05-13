<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import { useRouter } from 'vue-router';
import { apiClient } from '../core/api/axiosclient';
import { useAuthStore } from '../stores/authStore';
import { useToast } from '../composables/useToast';
import { useSeoMeta } from '../composables/useSeoMeta';
import PageHero from '../components/layout/PageHero.vue';

const router = useRouter();
const authStore = useAuthStore();
const { showToast } = useToast();
const { setSeo } = useSeoMeta();

const isLoading = ref(true);
const isSubmitting = ref(false);
const branches = ref<any[]>([]);
const myAppointments = ref<any[]>([]);
const availability = ref<any>(null);
const isCheckingAvailability = ref(false);

const form = ref({
  branch_id: null as number | null,
  appointment_date: '',
  appointment_time: '',
  service_type: '',
  customer_name: '',
  customer_phone: '',
  notes: '',
});

const isLoggedIn = computed(() => authStore.isAuthenticated);

const serviceOptions = [
  { value: 'eye_test', label: '👁️ Tes Mata', desc: 'Pemeriksaan mata oleh optometris kami' },
  { value: 'fitting', label: '🕶️ Fitting Frame', desc: 'Coba dan sesuaikan frame pilihan Anda' },
  { value: 'pickup', label: '📦 Ambil Pesanan', desc: 'Ambil pesanan online di toko' },
  { value: 'consultation', label: '💬 Konsultasi', desc: 'Konsultasi kebutuhan optik Anda' },
  { value: 'lens_replacement', label: '🔄 Ganti Lensa', desc: 'Ganti lensa frame yang sudah ada' },
];

const breadcrumbs = [
  { label: 'Beranda', to: '/' },
  { label: 'Booking Appointment' },
];

const loadData = async () => {
  try {
    const [branchRes] = await Promise.all([
      apiClient.get('/branches'),
      isLoggedIn.value ? loadMyAppointments() : Promise.resolve(),
    ]);
    branches.value = branchRes.data;

    // Pre-fill customer name/phone dari auth
    if (isLoggedIn.value && authStore.user) {
      form.value.customer_name  = authStore.user.name || '';
      form.value.customer_phone = authStore.user.phone || '';
    }
  } catch {
    showToast('Gagal memuat data cabang.', 'error');
  } finally {
    isLoading.value = false;
  }
};

const loadMyAppointments = async () => {
  try {
    const { data } = await apiClient.get('/appointments');
    myAppointments.value = data.data || data;
  } catch { /* silent */ }
};

const checkAvailability = async () => {
  if (!form.value.branch_id || !form.value.appointment_date) return;
  isCheckingAvailability.value = true;
  try {
    const { data } = await apiClient.get(
      `/branches/${form.value.branch_id}/availability`,
      { params: { date: form.value.appointment_date } }
    );
    availability.value = data;
    form.value.appointment_time = '';
  } catch {
    availability.value = null;
  } finally {
    isCheckingAvailability.value = false;
  }
};

const submitAppointment = async () => {
  if (!isLoggedIn.value) {
    router.push('/login?redirect=/appointment');
    return;
  }

  if (!form.value.branch_id || !form.value.appointment_date ||
      !form.value.appointment_time || !form.value.service_type ||
      !form.value.customer_name || !form.value.customer_phone) {
    showToast('Lengkapi semua data appointment.', 'error');
    return;
  }

  isSubmitting.value = true;
  try {
    const { data } = await apiClient.post('/appointments', form.value);
    showToast(data.message, 'success');
    await loadMyAppointments();
    // Reset form
    form.value.appointment_date = '';
    form.value.appointment_time = '';
    form.value.service_type = '';
    form.value.notes = '';
    availability.value = null;
  } catch (err: any) {
    showToast(err?.response?.data?.message || 'Gagal membuat appointment.', 'error');
  } finally {
    isSubmitting.value = false;
  }
};

const cancelAppointment = async (id: number) => {
  try {
    await apiClient.delete(`/appointments/${id}`);
    showToast('Appointment dibatalkan.', 'success');
    await loadMyAppointments();
  } catch (err: any) {
    showToast(err?.response?.data?.message || 'Gagal membatalkan.', 'error');
  }
};

const minDate = computed(() => {
  const d = new Date();
  d.setDate(d.getDate() + 1);
  return d.toISOString().split('T')[0];
});

onMounted(() => {
  setSeo({
    title: 'Booking Appointment',
    description: 'Booking appointment untuk tes mata, fitting frame, konsultasi, atau ambil pesanan di cabang Optik Medio terdekat.',
  });
  loadData();
});
</script>

<template>
  <div>
    <PageHero title="Booking Appointment" subtitle="Pilih cabang, layanan, dan waktu yang sesuai" :breadcrumbs="breadcrumbs" />

    <main class="max-w-5xl mx-auto px-6 py-12">
      <div v-if="isLoading" class="flex justify-center py-20">
        <span class="material-symbols-outlined animate-spin text-4xl" style="color: #c19a51;">sync</span>
      </div>

      <template v-else>
        <div class="grid gap-8 lg:grid-cols-[1.2fr,0.8fr]">

          <!-- Form Booking -->
          <div class="space-y-6">
            <h2 class="text-xl font-black" style="color: #1a1209; font-family: 'Outfit', sans-serif;">Buat Appointment Baru</h2>

            <!-- Pilih Cabang -->
            <div class="border p-6" style="background: white; border-color: rgba(193,154,81,0.2);">
              <p class="text-[10px] font-black uppercase tracking-[0.2em] mb-4" style="color: #8a7a60;">1. Pilih Cabang</p>
              <div class="grid gap-3">
                <label
                  v-for="branch in branches"
                  :key="branch.id"
                  class="flex items-start gap-4 p-4 border cursor-pointer transition-all"
                  :style="form.branch_id === branch.id ? 'border-color: #c19a51; background: rgba(193,154,81,0.05);' : 'border-color: #e5e0d8;'"
                >
                  <input type="radio" v-model="form.branch_id" :value="branch.id" class="mt-1" @change="availability = null; form.appointment_time = ''" />
                  <div>
                    <p class="font-bold text-sm" style="color: #1a1209;">{{ branch.name }}</p>
                    <p class="text-xs mt-0.5" style="color: #8a7a60;">{{ branch.address }}, {{ branch.city }}</p>
                    <p v-if="branch.phone" class="text-xs mt-0.5" style="color: #8a7a60;">📞 {{ branch.phone }}</p>
                  </div>
                </label>
              </div>
            </div>

            <!-- Pilih Layanan -->
            <div class="border p-6" style="background: white; border-color: rgba(193,154,81,0.2);">
              <p class="text-[10px] font-black uppercase tracking-[0.2em] mb-4" style="color: #8a7a60;">2. Pilih Layanan</p>
              <div class="grid gap-2">
                <label
                  v-for="svc in serviceOptions"
                  :key="svc.value"
                  class="flex items-center gap-4 p-3 border cursor-pointer transition-all"
                  :style="form.service_type === svc.value ? 'border-color: #c19a51; background: rgba(193,154,81,0.05);' : 'border-color: #e5e0d8;'"
                >
                  <input type="radio" v-model="form.service_type" :value="svc.value" />
                  <div>
                    <p class="font-bold text-sm" style="color: #1a1209;">{{ svc.label }}</p>
                    <p class="text-xs" style="color: #8a7a60;">{{ svc.desc }}</p>
                  </div>
                </label>
              </div>
            </div>

            <!-- Pilih Tanggal & Waktu -->
            <div class="border p-6" style="background: white; border-color: rgba(193,154,81,0.2);">
              <p class="text-[10px] font-black uppercase tracking-[0.2em] mb-4" style="color: #8a7a60;">3. Pilih Tanggal & Waktu</p>
              <div class="flex gap-3 mb-4">
                <input
                  v-model="form.appointment_date"
                  type="date"
                  :min="minDate"
                  class="flex-1 border px-4 py-3 text-sm focus:outline-none"
                  style="border-color: #e5e0d8;"
                  @change="checkAvailability"
                  :disabled="!form.branch_id"
                />
              </div>

              <div v-if="isCheckingAvailability" class="text-xs text-center py-3" style="color: #8a7a60;">
                <span class="material-symbols-outlined animate-spin text-sm align-middle">sync</span> Mengecek ketersediaan...
              </div>

              <div v-else-if="availability">
                <div v-if="availability.is_closed" class="p-3 text-sm text-center" style="background: rgba(239,68,68,0.06); color: #dc2626;">
                  Slot untuk tanggal ini sudah penuh atau cabang tutup.
                </div>
                <div v-else>
                  <p class="text-xs mb-3" style="color: #8a7a60;">{{ availability.available }} slot tersedia</p>
                  <div class="flex flex-wrap gap-2">
                    <button
                      v-for="slot in availability.available_slots"
                      :key="slot"
                      @click="form.appointment_time = slot"
                      class="px-3 py-1.5 text-xs font-bold border transition-all"
                      :style="form.appointment_time === slot
                        ? 'background: #1a1209; color: white; border-color: #1a1209;'
                        : 'border-color: #e5e0d8; color: #5a5248;'"
                    >
                      {{ slot }}
                    </button>
                  </div>
                </div>
              </div>
            </div>

            <!-- Data Pelanggan -->
            <div class="border p-6" style="background: white; border-color: rgba(193,154,81,0.2);">
              <p class="text-[10px] font-black uppercase tracking-[0.2em] mb-4" style="color: #8a7a60;">4. Data Anda</p>
              <div class="grid gap-3">
                <input v-model="form.customer_name" type="text" placeholder="Nama lengkap" class="border px-4 py-3 text-sm focus:outline-none" style="border-color: #e5e0d8;" />
                <input v-model="form.customer_phone" type="tel" placeholder="Nomor telepon" class="border px-4 py-3 text-sm focus:outline-none" style="border-color: #e5e0d8;" />
                <textarea v-model="form.notes" rows="2" placeholder="Catatan tambahan (opsional)" class="border px-4 py-3 text-sm focus:outline-none resize-none" style="border-color: #e5e0d8;"></textarea>
              </div>
            </div>

            <button
              @click="submitAppointment"
              :disabled="isSubmitting"
              class="w-full py-4 text-sm font-black uppercase tracking-wider text-white disabled:opacity-50 transition-all"
              style="background: linear-gradient(135deg, #1a1209 0%, #3d2c0e 100%);"
            >
              <span v-if="isSubmitting" class="material-symbols-outlined animate-spin text-sm align-middle mr-1">sync</span>
              {{ isSubmitting ? 'Memproses...' : 'Buat Appointment' }}
            </button>
          </div>

          <!-- Appointment Saya -->
          <div>
            <h2 class="text-xl font-black mb-4" style="color: #1a1209; font-family: 'Outfit', sans-serif;">Appointment Saya</h2>

            <div v-if="!isLoggedIn" class="border p-6 text-center" style="background: #fffdf7; border-color: rgba(193,154,81,0.2);">
              <p class="text-sm mb-3" style="color: #8a7a60;">Login untuk melihat appointment Anda.</p>
              <button @click="router.push('/login')" class="px-4 py-2 text-xs font-black uppercase tracking-wider text-white" style="background: #1a1209;">Login</button>
            </div>

            <div v-else-if="myAppointments.length === 0" class="border p-6 text-center" style="background: #fffdf7; border-color: rgba(193,154,81,0.2);">
              <span class="material-symbols-outlined text-3xl mb-2 block" style="color: #c19a51;">calendar_today</span>
              <p class="text-sm" style="color: #8a7a60;">Belum ada appointment.</p>
            </div>

            <div v-else class="space-y-3">
              <div
                v-for="apt in myAppointments.slice(0, 5)"
                :key="apt.id"
                class="border p-4"
                style="background: white; border-color: rgba(193,154,81,0.15);"
              >
                <div class="flex items-start justify-between gap-2 mb-2">
                  <p class="font-bold text-xs" style="color: #1a1209;">{{ apt.appointment_number }}</p>
                  <span
                    class="text-[10px] px-2 py-0.5 font-bold"
                    :style="apt.status === 'confirmed' ? 'background: rgba(59,130,246,0.1); color: #2563eb;'
                          : apt.status === 'completed' ? 'background: rgba(22,163,74,0.1); color: #16a34a;'
                          : apt.status === 'cancelled' ? 'background: rgba(239,68,68,0.1); color: #dc2626;'
                          : 'background: rgba(245,158,11,0.1); color: #d97706;'"
                  >
                    {{ apt.status === 'pending' ? 'Menunggu' : apt.status === 'confirmed' ? 'Dikonfirmasi' : apt.status === 'completed' ? 'Selesai' : 'Dibatalkan' }}
                  </span>
                </div>
                <p class="text-xs" style="color: #5a5248;">{{ apt.branch?.name }}</p>
                <p class="text-xs" style="color: #8a7a60;">{{ apt.appointment_date }} · {{ apt.appointment_time?.substring(0,5) }}</p>
                <button
                  v-if="['pending', 'confirmed'].includes(apt.status)"
                  @click="cancelAppointment(apt.id)"
                  class="mt-2 text-xs font-bold hover:underline"
                  style="color: #dc2626;"
                >
                  Batalkan
                </button>
              </div>
            </div>
          </div>
        </div>
      </template>
    </main>
  </div>
</template>
