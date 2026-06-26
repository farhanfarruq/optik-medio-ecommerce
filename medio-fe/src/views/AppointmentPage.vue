<script setup lang="ts">
import { computed, nextTick, onMounted, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { apiClient } from '../core/api/axiosclient';
import { useAuthStore } from '../stores/authStore';
import { useToast } from '../composables/useToast';
import { useSeoMeta } from '../composables/useSeoMeta';
import PageHero from '../components/layout/PageHero.vue';

const route = useRoute();
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
const bookingFormRef = ref<HTMLElement | null>(null);
const selectedAppointment = ref<any>(null);
const isLoadingAppointmentDetail = ref(false);

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
  { value: 'eye_test', icon: 'visibility', label: 'Tes Mata', desc: 'Pemeriksaan mata oleh optometris kami' },
  { value: 'fitting', icon: 'star', label: 'Fitting Frame', desc: 'Coba dan sesuaikan frame pilihan Anda' },
  { value: 'pickup', icon: 'shopping_bag', label: 'Ambil Pesanan', desc: 'Ambil pesanan online di toko' },
  { value: 'consultation', icon: 'chat', label: 'Konsultasi', desc: 'Konsultasi kebutuhan optik Anda' },
  { value: 'lens_replacement', icon: 'sync', label: 'Ganti Lensa', desc: 'Ganti lensa frame yang sudah ada' },
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

const applyPrefillFromQuery = async () => {
  const requestedService = typeof route.query.service === 'string' ? route.query.service : '';
  const sourceLabel = typeof route.query.source_label === 'string' ? route.query.source_label : '';

  if (requestedService && serviceOptions.some((option) => option.value === requestedService)) {
    form.value.service_type = requestedService;
  }

  if (sourceLabel && !form.value.notes) {
    form.value.notes = `Booking dari produk: ${sourceLabel}`;
  }

  if (requestedService || sourceLabel) {
    await nextTick();
    bookingFormRef.value?.scrollIntoView({ behavior: 'smooth', block: 'start' });
  }
};

const loadMyAppointments = async () => {
  try {
    const { data } = await apiClient.get('/appointments');
    myAppointments.value = data.data || data;
    if (selectedAppointment.value) {
      const latest = myAppointments.value.find((apt: any) => apt.id === selectedAppointment.value.id);
      if (latest) selectedAppointment.value = { ...selectedAppointment.value, ...latest };
    }
  } catch { /* silent */ }
};

const serviceLabel = (value: string) => serviceOptions.find((svc) => svc.value === value)?.label || value;

const statusLabel = (status: string) => ({
  pending: 'Menunggu',
  confirmed: 'Dikonfirmasi',
  completed: 'Selesai',
  cancelled: 'Dibatalkan',
  no_show: 'Tidak Hadir',
}[status] || status);

const statusStyle = (status: string) => status === 'confirmed'
  ? 'background: rgba(59,130,246,0.1); color: #2563eb;'
  : status === 'completed'
    ? 'background: rgba(22,163,74,0.1); color: #16a34a;'
    : status === 'cancelled' || status === 'no_show'
      ? 'background: rgba(239,68,68,0.1); color: #dc2626;'
      : 'background: rgba(245,158,11,0.1); color: #d97706;';

const scrollToBookingForm = () => {
  if (!isLoggedIn.value) {
    router.push('/login?redirect=/appointment');
    return;
  }

  bookingFormRef.value?.scrollIntoView({ behavior: 'smooth', block: 'start' });
};

const viewAppointmentDetail = async (appointment: any) => {
  selectedAppointment.value = appointment;
  isLoadingAppointmentDetail.value = true;

  try {
    const { data } = await apiClient.get(`/appointments/${appointment.id}`);
    selectedAppointment.value = data;
  } catch {
    showToast('Gagal memuat detail appointment.', 'error');
  } finally {
    isLoadingAppointmentDetail.value = false;
  }
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
    if (data.appointment) selectedAppointment.value = data.appointment;
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
    if (selectedAppointment.value?.id === id) {
      selectedAppointment.value = { ...selectedAppointment.value, status: 'cancelled' };
    }
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
  loadData().then(() => applyPrefillFromQuery());
});
</script>

<template>
  <div>
    <PageHero title="Booking Appointment" subtitle="Pilih cabang, layanan, dan waktu yang sesuai" :breadcrumbs="breadcrumbs" />

    <main class="container-commerce pt-8 pb-12">
      <div v-if="isLoading" class="flex justify-center py-20">
        <span class="material-symbols-outlined animate-spin text-4xl" style="color: var(--gold);">sync</span>
      </div>

      <template v-else>
        <section class="mb-8 border p-5 md:p-6 flex flex-col md:flex-row md:items-center justify-between gap-4" style="background: var(--ink); border-color: rgba(184,138,68,0.35); color: #fff;">
          <div class="flex items-center gap-4">
            <span class="material-symbols-outlined text-4xl shrink-0" style="color: var(--gold);">calendar_today</span>
            <div>
              <p class="text-lg font-black" style="font-family: 'Plus Jakarta Sans', Inter, system-ui, sans-serif; color: #fff;">Siap booking kunjungan?</p>
              <p class="text-sm" style="color: rgba(255,255,255,0.78);">Pilih cabang, layanan, tanggal, lalu tekan tombol buat appointment.</p>
            </div>
          </div>
          <button
            type="button"
            @click="scrollToBookingForm"
            class="btn-primary px-6 py-3 text-xs uppercase tracking-[0.12em]"
            style="background: var(--gold); color: var(--ink);"
          >
            Booking Sekarang
            <span class="material-symbols-outlined text-sm">arrow_forward</span>
          </button>
        </section>

        <div class="grid gap-8 lg:grid-cols-[1.2fr,0.8fr]">

          <!-- Form Booking -->
          <div ref="bookingFormRef" class="space-y-6 scroll-mt-28">
            <h2 class="text-xl font-black" style="color: var(--ink); font-family: 'Plus Jakarta Sans', Inter, system-ui, sans-serif;">Buat Appointment Baru</h2>

            <!-- Pilih Cabang -->
            <div class="border p-6" style="background: white; border-color: rgba(184,138,68,0.2);">
              <p class="text-[10px] font-black uppercase tracking-[0.2em] mb-4" style="color: #5c4a3a;">1. Pilih Cabang</p>
              <div class="grid gap-3">
                <label
                  v-for="branch in branches"
                  :key="branch.id"
                  class="flex items-start gap-4 p-4 border cursor-pointer transition-all"
                  :style="form.branch_id === branch.id ? 'border-color: var(--gold); background: rgba(184,138,68,0.05);' : 'border-color: var(--mist);'"
                >
                  <input type="radio" v-model="form.branch_id" :value="branch.id" class="mt-1" @change="availability = null; form.appointment_time = ''" />
                  <div>
                    <p class="font-bold text-sm" style="color: var(--ink);">{{ branch.name }}</p>
                    <p class="text-xs mt-0.5" style="color: #5c4a3a;">{{ branch.address }}, {{ branch.city }}</p>
                    <p v-if="branch.phone" class="flex items-center gap-1.5 text-xs mt-0.5" style="color: #5c4a3a;">
                      <span class="material-symbols-outlined text-sm">call</span>
                      {{ branch.phone }}
                    </p>
                  </div>
                </label>
              </div>
            </div>

            <!-- Pilih Layanan -->
            <div class="border p-6" style="background: white; border-color: rgba(184,138,68,0.2);">
              <p class="text-[10px] font-black uppercase tracking-[0.2em] mb-4" style="color: #5c4a3a;">2. Pilih Layanan</p>
              <div class="grid gap-2">
                <label
                  v-for="svc in serviceOptions"
                  :key="svc.value"
                  class="flex items-center gap-4 p-3 border cursor-pointer transition-all"
                  :style="form.service_type === svc.value ? 'border-color: var(--gold); background: rgba(184,138,68,0.05);' : 'border-color: var(--mist);'"
                >
                  <input type="radio" v-model="form.service_type" :value="svc.value" />
                  <span class="material-symbols-outlined text-xl" style="color: var(--gold);">{{ svc.icon }}</span>
                  <div>
                    <p class="font-bold text-sm" style="color: var(--ink);">{{ svc.label }}</p>
                    <p class="text-xs" style="color: #5c4a3a;">{{ svc.desc }}</p>
                  </div>
                </label>
              </div>
            </div>

            <!-- Pilih Tanggal & Waktu -->
            <div class="border p-6" style="background: white; border-color: rgba(184,138,68,0.2);">
              <p class="text-[10px] font-black uppercase tracking-[0.2em] mb-4" style="color: #5c4a3a;">3. Pilih Tanggal & Waktu</p>
              <div class="flex gap-3 mb-4">
                <input
                  v-model="form.appointment_date"
                  type="date"
                  :min="minDate"
                  class="flex-1 border px-4 py-3 text-sm focus:outline-none"
                  style="border-color: var(--mist);"
                  @change="checkAvailability"
                  :disabled="!form.branch_id"
                />
              </div>

              <div v-if="isCheckingAvailability" class="text-xs text-center py-3" style="color: #5c4a3a;">
                <span class="material-symbols-outlined animate-spin text-sm align-middle">sync</span> Mengecek ketersediaan...
              </div>

              <div v-else-if="availability">
                <div v-if="availability.is_closed" class="p-3 text-sm text-center" style="background: rgba(239,68,68,0.06); color: #dc2626;">
                  Slot untuk tanggal ini sudah penuh atau cabang tutup.
                </div>
                <div v-else>
                  <p class="text-xs mb-3" style="color: #5c4a3a;">{{ availability.available }} slot tersedia</p>
                  <div class="flex flex-wrap gap-2">
                    <button
                      v-for="slot in availability.available_slots"
                      :key="slot"
                      @click="form.appointment_time = slot"
                      class="px-3 py-1.5 text-xs font-bold border transition-all"
                      :style="form.appointment_time === slot
                        ? 'background: var(--ink); color: white; border-color: var(--ink);'
                        : 'border-color: var(--mist); color: var(--graphite);'"
                    >
                      {{ slot }}
                    </button>
                  </div>
                </div>
              </div>
            </div>

            <!-- Data Pelanggan -->
            <div class="border p-6" style="background: white; border-color: rgba(184,138,68,0.2);">
              <p class="text-[10px] font-black uppercase tracking-[0.2em] mb-4" style="color: #5c4a3a;">4. Data Anda</p>
              <div class="grid gap-3">
                <input v-model="form.customer_name" type="text" placeholder="Nama lengkap" class="border px-4 py-3 text-sm focus:outline-none" style="border-color: var(--mist);" />
                <input v-model="form.customer_phone" type="tel" placeholder="Nomor telepon" class="border px-4 py-3 text-sm focus:outline-none" style="border-color: var(--mist);" />
                <textarea v-model="form.notes" rows="2" placeholder="Catatan tambahan (opsional)" class="border px-4 py-3 text-sm focus:outline-none resize-none" style="border-color: var(--mist);"></textarea>
              </div>
            </div>

            <button
              @click="submitAppointment"
              :disabled="isSubmitting"
              class="w-full py-4 text-sm font-black uppercase tracking-wider text-white disabled:opacity-50 transition-all"
              style="background: linear-gradient(135deg, var(--ink) 0%, var(--graphite) 100%);"
            >
              <span v-if="isSubmitting" class="material-symbols-outlined animate-spin text-sm align-middle mr-1">sync</span>
              {{ isSubmitting ? 'Memproses...' : 'Buat Appointment' }}
            </button>
          </div>

          <!-- Appointment Saya -->
          <div>
            <h2 class="text-xl font-black mb-4" style="color: var(--ink); font-family: 'Plus Jakarta Sans', Inter, system-ui, sans-serif;">Appointment Saya</h2>

            <div v-if="!isLoggedIn" class="border p-6 text-center" style="background: var(--porcelain); border-color: rgba(184,138,68,0.2);">
              <p class="text-sm mb-3" style="color: #5c4a3a;">Login untuk melihat appointment Anda.</p>
              <button @click="router.push('/login')" class="px-4 py-2 text-xs font-black uppercase tracking-wider text-white" style="background: var(--ink);">Login</button>
            </div>

            <div v-else-if="myAppointments.length === 0" class="border p-6 text-center" style="background: var(--porcelain); border-color: rgba(184,138,68,0.2);">
              <span class="material-symbols-outlined text-3xl mb-2 block" style="color: var(--gold);">calendar_today</span>
              <p class="text-sm" style="color: #5c4a3a;">Belum ada appointment.</p>
            </div>

            <div v-else class="space-y-3">
              <div
                v-for="apt in myAppointments.slice(0, 5)"
                :key="apt.id"
                class="border p-4"
                style="background: white; border-color: rgba(184,138,68,0.15);"
              >
                <div class="flex items-start justify-between gap-2 mb-2">
                  <p class="font-bold text-xs" style="color: var(--ink);">{{ apt.appointment_number }}</p>
                  <span
                    class="text-[10px] px-2 py-0.5 font-bold"
                    :style="statusStyle(apt.status)"
                  >
                    {{ statusLabel(apt.status) }}
                  </span>
                </div>
                <p class="text-xs" style="color: var(--graphite);">{{ apt.branch?.name }}</p>
                <p class="text-xs" style="color: #5c4a3a;">{{ apt.appointment_date }} · {{ apt.appointment_time?.substring(0,5) }}</p>
                <div class="mt-3 flex flex-wrap gap-2">
                  <button
                    type="button"
                    @click="viewAppointmentDetail(apt)"
                    class="inline-flex items-center gap-1.5 px-3 py-1.5 text-[10px] font-black uppercase tracking-wider border"
                    style="border-color: rgba(184,138,68,0.35); color: #8a5f13;"
                  >
                    <span class="material-symbols-outlined text-xs">visibility</span>
                    Detail
                  </button>
                  <button
                    v-if="['pending', 'confirmed'].includes(apt.status)"
                    type="button"
                    @click="cancelAppointment(apt.id)"
                    class="inline-flex items-center gap-1.5 px-3 py-1.5 text-[10px] font-black uppercase tracking-wider border"
                    style="border-color: rgba(220,38,38,0.25); color: #dc2626;"
                  >
                    <span class="material-symbols-outlined text-xs">close</span>
                    Batalkan
                  </button>
                </div>
              </div>
            </div>

            <div v-if="selectedAppointment" class="mt-5 border p-5" style="background: var(--porcelain); border-color: rgba(184,138,68,0.25);">
              <div class="flex items-start justify-between gap-3 mb-4">
                <div>
                  <p class="text-[10px] font-black uppercase tracking-[0.2em]" style="color: #5c4a3a;">Detail Appointment</p>
                  <h3 class="text-base font-black mt-1" style="color: var(--ink); font-family: 'Plus Jakarta Sans', Inter, system-ui, sans-serif;">
                    {{ selectedAppointment.appointment_number }}
                  </h3>
                </div>
                <span class="text-[10px] px-2 py-0.5 font-bold" :style="statusStyle(selectedAppointment.status)">
                  {{ statusLabel(selectedAppointment.status) }}
                </span>
              </div>

              <div v-if="isLoadingAppointmentDetail" class="flex items-center gap-2 text-xs" style="color: #5c4a3a;">
                <span class="material-symbols-outlined animate-spin text-sm">sync</span>
                Memuat detail...
              </div>

              <div v-else class="grid gap-3 text-xs">
                <div class="flex items-start gap-3">
                  <span class="material-symbols-outlined text-lg" style="color: var(--gold);">calendar_today</span>
                  <div>
                    <p class="font-bold" style="color: var(--ink);">{{ selectedAppointment.appointment_date }} · {{ selectedAppointment.appointment_time?.substring(0,5) }}</p>
                    <p style="color: #5c4a3a;">{{ serviceLabel(selectedAppointment.service_type) }}</p>
                  </div>
                </div>
                <div class="flex items-start gap-3">
                  <span class="material-symbols-outlined text-lg" style="color: var(--gold);">store</span>
                  <div>
                    <p class="font-bold" style="color: var(--ink);">{{ selectedAppointment.branch?.name }}</p>
                    <p style="color: #5c4a3a;">{{ selectedAppointment.branch?.address }}, {{ selectedAppointment.branch?.city }}</p>
                    <a
                      v-if="selectedAppointment.branch?.maps_url"
                      :href="selectedAppointment.branch.maps_url"
                      target="_blank"
                      rel="noopener noreferrer"
                      class="inline-flex items-center gap-1 mt-1 font-bold"
                      style="color: var(--gold);"
                    >
                      Buka Maps
                      <span class="material-symbols-outlined text-xs">open_in_new</span>
                    </a>
                  </div>
                </div>
                <div class="flex items-start gap-3">
                  <span class="material-symbols-outlined text-lg" style="color: var(--gold);">person</span>
                  <div>
                    <p class="font-bold" style="color: var(--ink);">{{ selectedAppointment.customer_name }}</p>
                    <p style="color: #5c4a3a;">{{ selectedAppointment.customer_phone }}</p>
                  </div>
                </div>
                <div v-if="selectedAppointment.notes" class="flex items-start gap-3">
                  <span class="material-symbols-outlined text-lg" style="color: var(--gold);">notes</span>
                  <p style="color: var(--graphite);">{{ selectedAppointment.notes }}</p>
                </div>
              </div>
            </div>
          </div>
        </div>
      </template>
    </main>
  </div>
</template>
