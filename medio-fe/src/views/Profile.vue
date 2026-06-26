<script setup lang="ts">
// ─────────────────────────────────────────────────────────────────────────
// FIXME P1-9 (Phase 3): God component (1.520 LOC) — refactor ke sub-tree.
// Lihat: medio-fe/src/views/REFACTOR_PLAN.md untuk migration plan lengkap.
// Composables baru tersedia: useOrderStatus, useFormatMoney.
// ─────────────────────────────────────────────────────────────────────────
import { logger } from '../core/utils/logger';
import { computed, ref, onMounted, watch } from 'vue';
import { useAuthStore } from '../stores/authStore';
import { orderRepository } from '../repositories/OrderRepository';
import { shippingRepository, type Location } from '../repositories/ShippingRepository';
import { useRoute, useRouter } from 'vue-router';
import { apiClient } from '../core/api/axiosclient';
import { useToast } from '../composables/useToast';
import { resolveImageUrl } from '../core/utils/image';
import { useWishlistStore } from '../stores/wishlistStore';
import { affiliateRepository, type AffiliateProfile, type AffiliateSummary, type AffiliateCommission, type AffiliateEarning } from '../repositories/AffiliateRepository';
import { prescriptionRepository, type PrescriptionPayload, type PrescriptionProfile } from '../repositories/PrescriptionRepository';
import WarrantyPage from './WarrantyPage.vue';
import PageHero from '../components/layout/PageHero.vue';

const { showToast } = useToast();

const authStore = useAuthStore();
const wishlistStore = useWishlistStore();
const route = useRoute();
const router = useRouter();
const orders = ref<any[]>([]);
const loyaltyHistory = ref<any[]>([]);
const isLoadingHistory = ref(false);
const isLoadingOrders = ref(false);
const confirmingOrderId = ref<number | null>(null);
const currentOrderPage = ref(1);
const lastOrderPage = ref(1);
const isLoadingMoreOrders = ref(false);
const canLoadMoreOrders = computed(() => currentOrderPage.value < lastOrderPage.value);
const orderStatusFilter = ref<string>('all');
const orderStatusTabs: Array<{ label: string; value: string; statuses: string[] }> = [
  { label: 'Semua', value: 'all', statuses: [] },
  { label: 'Menunggu Bayar', value: 'unpaid', statuses: ['unpaid'] },
  { label: 'Dikemas', value: 'processing', statuses: ['paid', 'waiting_prescription_review', 'prescription_verified', 'lens_processing', 'processing'] },
  { label: 'Dikirim', value: 'shipped', statuses: ['shipped'] },
  { label: 'Selesai', value: 'completed', statuses: ['delivered', 'completed'] },
  { label: 'Dibatalkan', value: 'cancelled', statuses: ['cancelled', 'refunded'] },
];
const filteredOrders = computed(() => {
  if (orderStatusFilter.value === 'all') return orders.value;
  const selectedTab = orderStatusTabs.find((tab) => tab.value === orderStatusFilter.value);
  if (!selectedTab) return orders.value;
  return orders.value.filter((order: any) => selectedTab.statuses.includes(normalizeOrderStatus(order.status)));
});
const affiliateProfile = ref<AffiliateProfile | null>(null);
const affiliateSummary = ref<AffiliateSummary | null>(null);
const affiliateCommissions = ref<AffiliateCommission[]>([]);
const affiliateEarnings = ref<AffiliateEarning[]>([]);
const isLoadingAffiliate = ref(false);
const isApplyingAffiliate = ref(false);
const isRequestingPayout = ref(false);
const isSavingPayoutProfile = ref(false);
const payoutAmount = ref<number>(0);
const payoutProfileForm = ref({
  payout_method: 'bank_transfer' as const,
  payout_bank_name: '',
  payout_account_number: '',
  payout_account_name: '',
  payout_notes: '',
});
const hasCompletePayoutProfile = computed(() =>
  Boolean(
    payoutProfileForm.value.payout_bank_name.trim()
      && payoutProfileForm.value.payout_account_number.trim()
      && payoutProfileForm.value.payout_account_name.trim(),
  ),
);
const prescriptions = ref<PrescriptionProfile[]>([]);
const isLoadingPrescriptions = ref(false);
const editingPrescriptionId = ref<number | null>(null);
const prescriptionEditForm = ref({ label: '', notes: '' });

const formatMoney = (value: number | string | null | undefined) => new Intl.NumberFormat('id-ID', {
  style: 'currency',
  currency: 'IDR',
  maximumFractionDigits: 0,
}).format(Number(value || 0));

const normalizeOrderStatus = (status: string | null | undefined) => String(status || '').toLowerCase();

const orderStatusLabel = (status: string | null | undefined) => {
  const normalized = normalizeOrderStatus(status);
  return ({
    unpaid: 'Menunggu Bayar',
    paid: 'Dibayar',
    waiting_prescription_review: 'Review Resep',
    prescription_verified: 'Resep Terverifikasi',
    lens_processing: 'Proses Lensa',
    processing: 'Dikemas',
    shipped: 'Dikirim',
    delivered: 'Diterima',
    completed: 'Selesai',
    cancelled: 'Dibatalkan',
    refunded: 'Refund',
  } as Record<string, string>)[normalized] || status || '-';
};

const orderStatusClass = (status: string | null | undefined) => {
  const normalized = normalizeOrderStatus(status);
  if (['paid', 'delivered', 'completed'].includes(normalized)) return 'bg-olive/10 text-olive';
  if (['shipped'].includes(normalized)) return 'bg-blue-100 text-blue-800';
  if (['cancelled', 'refunded'].includes(normalized)) return 'bg-red-100 text-red-800';
  if (['processing', 'waiting_prescription_review', 'prescription_verified', 'lens_processing'].includes(normalized)) return 'bg-gold/10 text-gold';
  return 'bg-secondary-fixed/30 text-secondary';
};

const syncPayoutProfileForm = (profile: AffiliateProfile | null) => {
  payoutProfileForm.value = {
    payout_method: 'bank_transfer',
    payout_bank_name: profile?.payout_bank_name || '',
    payout_account_number: profile?.payout_account_number || '',
    payout_account_name: profile?.payout_account_name || '',
    payout_notes: profile?.payout_notes || '',
  };
};

// Form tambah resep baru
const showCreatePrescriptionForm = ref(false);
const isCreatingPrescription = ref(false);
const newPrescriptionFile = ref<File | null>(null);
const newPrescriptionPdMode = ref<'single' | 'dual'>('single');
const newPrescriptionForm = ref({
  label: '',
  lens_type: 'single_vision',
  right_sphere: null as number | null,
  right_cylinder: null as number | null,
  right_axis: null as number | null,
  right_add: null as number | null,
  left_sphere: null as number | null,
  left_cylinder: null as number | null,
  left_axis: null as number | null,
  left_add: null as number | null,
  pd_single: null as number | null,
  pd_right: null as number | null,
  pd_left: null as number | null,
  notes: '',
  is_default: false,
});
const lensTypesWithAdd = ['progressive'];
const supportsAddForNewPrescription = computed(() =>
  lensTypesWithAdd.includes(newPrescriptionForm.value.lens_type),
);
const usesRightAxis = computed(() => Number(newPrescriptionForm.value.right_cylinder || 0) !== 0);
const usesLeftAxis = computed(() => Number(newPrescriptionForm.value.left_cylinder || 0) !== 0);

const formatLensTypeLabel = (lensType?: string | null) => {
  switch (lensType) {
    case 'single_vision':
      return 'Single Vision';
    case 'progressive':
      return 'Progresif / Bifokal';
    case 'reading':
      return 'Single Vision Baca';
    case 'blue_light':
      return 'Blue Light';
    case 'photochromic':
      return 'Photochromic';
    case 'high_index':
      return 'High Index';
    case 'anti_radiation':
      return 'Anti Radiasi';
    default:
      return lensType || '-';
  }
};
const isSharingWishlist = ref(false);
const profileHeroMap: Record<string, { title: string; subtitle: string; crumb: string }> = {
  profile: { title: "Akun Saya", subtitle: "Kelola profil, level loyalty, alamat, pesanan, wishlist, dan layanan akun.", crumb: "Akun Saya" },
  addresses: { title: "Alamat Saya", subtitle: "Atur alamat pengiriman dan titik penerimaan pesanan optik.", crumb: "Alamat" },
  prescriptions: { title: "Resep Optik", subtitle: "Simpan dan pakai ulang data resep untuk pemesanan lensa berikutnya.", crumb: "Resep Optik" },
  orders: { title: "Pesanan Saya", subtitle: "Pantau status pesanan, pembayaran, pengiriman, dan tindakan lanjutan.", crumb: "Pesanan" },
  wishlist: { title: "Wishlist", subtitle: "Koleksi produk favorit yang siap dibandingkan atau dibeli kembali.", crumb: "Wishlist" },
  warranty: { title: "Garansi & Servis", subtitle: "Cek layanan garansi, servis frame, dan bantuan purna jual.", crumb: "Garansi" },
  affiliate: { title: "Afiliasi & Komisi", subtitle: "Pantau performa referral dan data pencairan komisi.", crumb: "Afiliasi" }
};

const currentSection = computed(() => {
  switch (route.name) {
    case 'Addresses':
      return 'addresses';
    case 'Prescriptions':
      return 'prescriptions';
    case 'Orders':
      return 'orders';
    case 'Wishlist':
      return 'wishlist';
    case 'Warranty':
      return 'warranty';
    case 'AffiliateDashboard':
      return 'affiliate';
    default:
      return 'profile';
  }
});
const profileHero = computed(() => profileHeroMap[currentSection.value] || profileHeroMap.profile);
const profileBreadcrumbs = computed(() => [{ label: profileHero.value.crumb }]);

watch(
  () => newPrescriptionForm.value.lens_type,
  (lensType) => {
    if (!lensTypesWithAdd.includes(lensType)) {
      newPrescriptionForm.value.right_add = null;
      newPrescriptionForm.value.left_add = null;
    }
  },
);

watch(
  () => newPrescriptionForm.value.right_cylinder,
  (cylinder) => {
    if (Number(cylinder || 0) === 0) {
      newPrescriptionForm.value.right_axis = null;
    }
  },
);

watch(
  () => newPrescriptionForm.value.left_cylinder,
  (cylinder) => {
    if (Number(cylinder || 0) === 0) {
      newPrescriptionForm.value.left_axis = null;
    }
  },
);

watch(newPrescriptionPdMode, (mode) => {
  if (mode === 'single') {
    newPrescriptionForm.value.pd_right = null;
    newPrescriptionForm.value.pd_left = null;
    return;
  }

  newPrescriptionForm.value.pd_single = null;
});

const loadOrders = async (page = 1, append = false) => {
  const response = await orderRepository.getUserOrders(page);
  orders.value = append ? [...orders.value, ...response.data] : response.data;
  currentOrderPage.value = response.current_page;
  lastOrderPage.value = response.last_page;
};

const fetchLoyaltyHistory = async () => {
  try {
    isLoadingHistory.value = true;
    const response = await apiClient.get('/orders/loyalty-history');
    loyaltyHistory.value = response.data.history ? response.data.history.data : [];
  } catch (error) {
    logger.error('Failed to fetch loyalty history', error);
  } finally {
    isLoadingHistory.value = false;
  }
};

const levelMembers = ref<any[]>([]);
const fetchLevelMembers = async () => {
  try {
    const response = await apiClient.get('/level-members');
    levelMembers.value = response.data;
  } catch (error) {
    logger.error('Failed to fetch level members', error);
  }
};

const nextLevel = computed(() => {
  if (!authStore.user || !levelMembers.value.length) return null;
  const currentPoints = authStore.user.loyalty_points || 0;
  return levelMembers.value.find((l: any) => l.min_points > currentPoints);
});

const currentLevel = computed(() => {
  return authStore.user?.current_level_membership?.level_member;
});

const progressToNextLevel = computed(() => {
  if (!nextLevel.value || !authStore.user) return 0;
  const currentPoints = authStore.user.loyalty_points || 0;
  const min = currentLevel.value?.min_points || 0;
  const max = nextLevel.value.min_points;
  return Math.min(100, Math.max(0, ((currentPoints - min) / (max - min)) * 100));
});

onMounted(async () => {
  if (!authStore.isAuthenticated) {
    router.push('/login');
    return;
  }
  
  isLoadingOrders.value = true;
  try {
    await authStore.fetchUser();
    await fetchLevelMembers();
    await loadOrders();
    if (authStore.isAuthenticated) {
      await wishlistStore.fetchWishlist();
      await fetchLoyaltyHistory();
      await fetchPrescriptions();
      fetchAffiliateData();
    }
  } catch (error) {
    logger.error('Failed to fetch profile data', error);
  } finally {
    isLoadingOrders.value = false;
  }
});

const handleLogout = () => {
  authStore.logout();
  router.push('/');
};

const shareWishlist = async () => {
  try {
    isSharingWishlist.value = true;
    const link = await wishlistStore.createShareLink();
    await navigator.clipboard.writeText(link);
    showToast('Link wishlist berhasil disalin.', 'success');
  } catch (error: any) {
    const message = error.response?.data?.message || 'Gagal membuat link wishlist.';
    showToast(message, 'error');
  } finally {
    isSharingWishlist.value = false;
  }
};

const fetchPrescriptions = async () => {
  try {
    isLoadingPrescriptions.value = true;
    prescriptions.value = await prescriptionRepository.list();
  } catch (error) {
    logger.error('Failed to fetch prescriptions', error);
  } finally {
    isLoadingPrescriptions.value = false;
  }
};

const handleNewPrescriptionFile = (e: Event) => {
  const input = e.target as HTMLInputElement;
  newPrescriptionFile.value = input.files?.[0] || null;
};

const appendPrescriptionValue = (formData: FormData, key: string, value: unknown) => {
  if (value === null || value === undefined || value === '') return;
  formData.append(key, typeof value === 'boolean' ? (value ? '1' : '0') : String(value));
};

const buildNewPrescriptionPayload = () => {
  const payload = {
    ...newPrescriptionForm.value,
  } as Record<string, unknown>;

  if (!supportsAddForNewPrescription.value) {
    payload.right_add = null;
    payload.left_add = null;
  }

  if (!usesRightAxis.value) {
    payload.right_axis = null;
  }

  if (!usesLeftAxis.value) {
    payload.left_axis = null;
  }

  if (newPrescriptionPdMode.value === 'single') {
    payload.pd_right = null;
    payload.pd_left = null;
  } else {
    payload.pd_single = null;
  }

  return payload;
};

const validateNewPrescriptionInput = () => {
  if (usesRightAxis.value) {
    const axis = Number(newPrescriptionForm.value.right_axis);
    if (!Number.isInteger(axis) || axis < 1 || axis > 180) {
      return 'Axis kanan wajib diisi dengan angka 1 sampai 180 jika CYL kanan diisi.';
    }
  }

  if (usesLeftAxis.value) {
    const axis = Number(newPrescriptionForm.value.left_axis);
    if (!Number.isInteger(axis) || axis < 1 || axis > 180) {
      return 'Axis kiri wajib diisi dengan angka 1 sampai 180 jika CYL kiri diisi.';
    }
  }

  if (newPrescriptionPdMode.value === 'single') {
    const pdSingle = Number(newPrescriptionForm.value.pd_single);
    if (!Number.isFinite(pdSingle) || pdSingle < 50 || pdSingle > 75) {
      return 'PD tunggal wajib diisi dalam rentang 50 sampai 75 mm.';
    }

    return null;
  }

  const pdRight = Number(newPrescriptionForm.value.pd_right);
  const pdLeft = Number(newPrescriptionForm.value.pd_left);

  if (!Number.isFinite(pdRight) || pdRight < 25 || pdRight > 38) {
    return 'PD kanan wajib diisi dalam rentang 25 sampai 38 mm.';
  }

  if (!Number.isFinite(pdLeft) || pdLeft < 25 || pdLeft > 38) {
    return 'PD kiri wajib diisi dalam rentang 25 sampai 38 mm.';
  }

  return null;
};

const createNewPrescription = async () => {
  if (!newPrescriptionForm.value.label.trim()) {
    showToast('Nama resep wajib diisi.', 'error');
    return;
  }

  const validationMessage = validateNewPrescriptionInput();
  if (validationMessage) {
    showToast(validationMessage, 'error');
    return;
  }

  isCreatingPrescription.value = true;
  try {
    const formData = new FormData();
    const formValues = buildNewPrescriptionPayload();
    Object.entries(formValues).forEach(([key, value]) => {
      appendPrescriptionValue(formData, key, value);
    });
    if (newPrescriptionFile.value) {
      formData.append('attachment', newPrescriptionFile.value, newPrescriptionFile.value.name);
    }

    await prescriptionRepository.create(formData as any);
    await fetchPrescriptions();
    showToast('Resep berhasil disimpan!', 'success');

    // Reset form
    showCreatePrescriptionForm.value = false;
    newPrescriptionFile.value = null;
    newPrescriptionPdMode.value = 'single';
    newPrescriptionForm.value = {
      label: '', lens_type: 'single_vision',
      right_sphere: null, right_cylinder: null, right_axis: null, right_add: null,
      left_sphere: null, left_cylinder: null, left_axis: null, left_add: null,
      pd_single: null, pd_right: null, pd_left: null,
      notes: '', is_default: false,
    };
  } catch (error: any) {
    const errors = error?.response?.data?.errors as Record<string, string[]> | undefined;
    const msg = (errors ? Object.values(errors)[0]?.[0] : null)
      || error?.response?.data?.message
      || 'Gagal menyimpan resep.';
    showToast(String(msg), 'error');
  } finally {
    isCreatingPrescription.value = false;
  }
};

const prescriptionPayloadFromProfile = (profile: PrescriptionProfile): PrescriptionPayload => ({
  label: prescriptionEditForm.value.label || profile.label,
  lens_type: profile.lens_type || 'single_vision',
  right_sphere: profile.right_sphere ?? null,
  right_cylinder: profile.right_cylinder ?? null,
  right_axis: profile.right_axis ?? null,
  right_add: profile.right_add ?? null,
  left_sphere: profile.left_sphere ?? null,
  left_cylinder: profile.left_cylinder ?? null,
  left_axis: profile.left_axis ?? null,
  left_add: profile.left_add ?? null,
  pd_single: profile.pd_single ?? null,
  pd_right: profile.pd_right ?? null,
  pd_left: profile.pd_left ?? null,
  notes: prescriptionEditForm.value.notes || null,
  is_default: profile.is_default,
});

const startEditPrescription = (profile: PrescriptionProfile) => {
  editingPrescriptionId.value = profile.id;
  prescriptionEditForm.value = {
    label: profile.label,
    notes: profile.notes || '',
  };
};

const savePrescriptionEdit = async (profile: PrescriptionProfile) => {
  try {
    await prescriptionRepository.update(profile.id, prescriptionPayloadFromProfile(profile));
    editingPrescriptionId.value = null;
    await fetchPrescriptions();
    showToast('Resep berhasil diperbarui.', 'success');
  } catch (error) {
    logger.error('Failed to update prescription', error);
    showToast('Gagal memperbarui resep.', 'error');
  }
};

const setDefaultPrescription = async (id: number) => {
  try {
    await prescriptionRepository.setDefault(id);
    await fetchPrescriptions();
    showToast('Resep default diperbarui.', 'success');
  } catch (error) {
    logger.error('Failed to set default prescription', error);
    showToast('Gagal mengubah resep default.', 'error');
  }
};

const deletePrescription = async (id: number) => {
  if (!confirm('Hapus resep ini?')) return;

  try {
    await prescriptionRepository.delete(id);
    await fetchPrescriptions();
    showToast('Resep berhasil dihapus.', 'success');
  } catch (error) {
    logger.error('Failed to delete prescription', error);
    showToast('Gagal menghapus resep.', 'error');
  }
};

const fetchAffiliateData = async () => {
  if (!authStore.isAuthenticated) return;
  try {
    isLoadingAffiliate.value = true;
    const data = await affiliateRepository.getDashboard();
    affiliateProfile.value = data.profile;
    affiliateSummary.value = data.summary;
    syncPayoutProfileForm(data.profile);
    if (data.profile) {
      const commData = await affiliateRepository.getCommissions();
      affiliateCommissions.value = commData.data;
      affiliateEarnings.value = await affiliateRepository.getEarnings();
    } else {
      affiliateCommissions.value = [];
      affiliateEarnings.value = [];
    }
  } catch (error) { logger.warn('Failed affiliate data', error); }
  finally { isLoadingAffiliate.value = false; }
};
const applyAffiliate = async () => {
  try {
    isApplyingAffiliate.value = true;
    affiliateProfile.value = await affiliateRepository.apply();
    syncPayoutProfileForm(affiliateProfile.value);
    showToast('Pendaftaran affiliator berhasil!', 'success');
  } catch (err: any) { showToast(err.response?.data?.message || 'Gagal mendaftar.', 'error'); }
  finally { isApplyingAffiliate.value = false; }
};
const savePayoutProfile = async () => {
  if (!hasCompletePayoutProfile.value) {
    showToast('Lengkapi nama bank, nomor rekening, dan nama pemilik rekening.', 'error');
    return;
  }

  try {
    isSavingPayoutProfile.value = true;
    affiliateProfile.value = await affiliateRepository.updatePayoutProfile({
      payout_method: 'bank_transfer',
      payout_bank_name: payoutProfileForm.value.payout_bank_name.trim(),
      payout_account_number: payoutProfileForm.value.payout_account_number.trim(),
      payout_account_name: payoutProfileForm.value.payout_account_name.trim(),
      payout_notes: payoutProfileForm.value.payout_notes.trim(),
    });
    syncPayoutProfileForm(affiliateProfile.value);
    showToast('Rekening pencairan berhasil disimpan.', 'success');
  } catch (err: any) {
    showToast(err.response?.data?.message || 'Gagal menyimpan rekening.', 'error');
  } finally {
    isSavingPayoutProfile.value = false;
  }
};
const requestPayout = async () => {
  if (!hasCompletePayoutProfile.value) {
    showToast('Lengkapi rekening pencairan komisi terlebih dahulu.', 'error');
    return;
  }
  if (!payoutAmount.value || payoutAmount.value <= 0) { showToast('Masukkan jumlah yang valid.', 'error'); return; }
  if (affiliateSummary.value && payoutAmount.value > affiliateSummary.value.available_balance) {
    showToast('Jumlah pencairan melebihi saldo komisi tersedia.', 'error');
    return;
  }
  try {
    isRequestingPayout.value = true;
    await affiliateRepository.requestPayout(payoutAmount.value);
    showToast('Permintaan pencairan berhasil!', 'success');
    payoutAmount.value = 0;
    await fetchAffiliateData();
  } catch (err: any) { showToast(err.response?.data?.message || 'Gagal request.', 'error'); }
  finally { isRequestingPayout.value = false; }
};


const loadMoreOrders = async () => {
  if (!canLoadMoreOrders.value || isLoadingMoreOrders.value) return;

  isLoadingMoreOrders.value = true;
  try {
    await loadOrders(currentOrderPage.value + 1, true);
  } catch (error) {
    logger.error('Failed to load more orders', error);
    showToast('Gagal memuat pesanan tambahan.', 'error');
  } finally {
    isLoadingMoreOrders.value = false;
  }
};

const confirmDelivery = async (orderId: number) => {
  if (confirmingOrderId.value === orderId) return;

  confirmingOrderId.value = orderId;
  try {
    const response = await orderRepository.confirmDelivery(orderId);
    orders.value = orders.value.map((order) =>
      order.id === orderId ? { ...order, ...response.order } : order,
    );
    showToast(response.message || 'Pesanan berhasil dikonfirmasi diterima.', 'success');
  } catch (error: any) {
    const message = error?.response?.data?.message || 'Gagal mengonfirmasi penerimaan barang.';
    showToast(message, 'error');
  } finally {
    confirmingOrderId.value = null;
  }
};

// Address Management
const showAddressModal = ref(false);
const isSavingAddress = ref(false);
const provinces = ref<Location[]>([]);
const cities = ref<Location[]>([]);
const districts = ref<Location[]>([]);
const isProvLoading = ref(false);
const isCityLoading = ref(false);
const isDistLoading = ref(false);

const addressForm = ref({
  recipient_name: '',
  phone: '',
  address: '',
  province_id: '',
  province: '',
  city_id: '',
  city: '',
  district_id: '',
  district: '',
  postal_code: '',
  is_default: false
});

const openAddressModal = async () => {
  showAddressModal.value = true;
  if (provinces.value.length === 0) {
    try {
      isProvLoading.value = true;
      provinces.value = await shippingRepository.getProvinces();
    } catch (error) {
      logger.error('Failed to load provinces', error);
    } finally {
      isProvLoading.value = false;
    }
  }
};

watch(() => addressForm.value.province_id, async (newVal) => {
  if (newVal) {
    const selectedProv = provinces.value.find(p => String(p.id || (p as any).province_id) === String(newVal)) as any;
    addressForm.value.province = selectedProv ? (selectedProv.name || selectedProv.province_name || selectedProv.province) : '';
    addressForm.value.city_id = '';
    addressForm.value.district_id = '';
    cities.value = [];
    try {
      isCityLoading.value = true;
      const data = await shippingRepository.getCities(newVal);
      cities.value = data.map((c: any) => ({
        id: String(c.id || c.city_id || ''),
        name: c.name || c.city_name || `${c.type} ${c.city}`
      }));
    } catch (e) {
      logger.error('Failed to load cities', e);
    } finally {
      isCityLoading.value = false;
    }
  }
});

watch(() => addressForm.value.city_id, async (newVal) => {
  if (newVal) {
    const selectedCity = cities.value.find(c => String(c.id) === String(newVal)) as any;
    addressForm.value.city = selectedCity ? selectedCity.name : '';
    addressForm.value.district_id = '';
    districts.value = [];
    try {
      isDistLoading.value = true;
      const data = await shippingRepository.getDistricts(newVal);
      districts.value = data.map((d: any) => ({
        id: String(d.id || d.subdistrict_id || d.district_id || ''),
        name: d.name || d.subdistrict_name || d.district_name || d.district,
        zip_code: String(d.zip_code || d.postal_code || '')
      }));
    } catch (e) {
      logger.error('Failed to load districts', e);
    } finally {
      isDistLoading.value = false;
    }
  }
});

watch(() => addressForm.value.district_id, (newVal) => {
  if (newVal) {
    const selectedDist = districts.value.find(d => String(d.id) === String(newVal));
    addressForm.value.district = selectedDist ? selectedDist.name : '';
    addressForm.value.postal_code = (selectedDist as any)?.zip_code || addressForm.value.postal_code;
  }
});

const saveAddress = async () => {
  try {
    isSavingAddress.value = true;
    logger.debug('Saving address payload:', addressForm.value);
    await apiClient.post('/addresses', addressForm.value);
    await authStore.fetchUser();
    showAddressModal.value = false;
    // Reset form
    addressForm.value = {
      recipient_name: '',
      phone: '',
      address: '',
      province_id: '',
      province: '',
      city_id: '',
      city: '',
      district_id: '',
      district: '',
      postal_code: '',
      is_default: false
    };
  } catch (error) {
    logger.error('Failed to save address', error);
    showToast('Gagal menyimpan alamat. Periksa kembali data Anda.', 'error');
  } finally {
    isSavingAddress.value = false;
  }
};

const deleteAddress = async (id: number) => {
  if (!confirm('Are you sure you want to delete this address?')) return;
  try {
    await apiClient.delete(`/addresses/${id}`);
    await authStore.fetchUser();
  } catch (error) {
    logger.error('Failed to delete address', error);
  }
};
</script>

<template>
  <PageHero
    :title="profileHero.title"
    :subtitle="profileHero.subtitle"
    :breadcrumbs="profileBreadcrumbs"
  />

  <main class="container-commerce pt-8 pb-20 flex-grow">
    <div class="flex flex-col lg:flex-row gap-8 xl:gap-10">

      <aside class="w-full lg:w-64 xl:w-72 shrink-0">
        <nav class="flex flex-col gap-1 rounded-lg overflow-hidden border p-2" style="background: white; border-color: rgba(184,138,68,0.15); box-shadow: 0 2px 12px rgba(0,0,0,0.05);">
          <button
            @click="router.push('/profile')"
            class="flex items-center gap-3 text-left px-4 py-3 rounded-lg text-sm font-bold transition-all"
            :style="currentSection === 'profile'
              ? 'background: linear-gradient(135deg, var(--ink), #3d2c0e); color: white;'
              : 'color: var(--graphite); background: transparent;'"
          >
            <span class="material-symbols-outlined text-base">person</span>
            Profil Saya
          </button>
          <button
            @click="router.push('/addresses')"
            class="flex items-center gap-3 text-left px-4 py-3 rounded-lg text-sm font-bold transition-all"
            :style="currentSection === 'addresses'
              ? 'background: linear-gradient(135deg, var(--ink), #3d2c0e); color: white;'
              : 'color: var(--graphite); background: transparent;'"
          >
            <span class="material-symbols-outlined text-base">location_on</span>
            Alamat Saya
          </button>
          <button
            @click="router.push('/prescriptions')"
            class="flex items-center gap-3 text-left px-4 py-3 rounded-lg text-sm font-bold transition-all"
            :style="currentSection === 'prescriptions'
              ? 'background: linear-gradient(135deg, var(--ink), #3d2c0e); color: white;'
              : 'color: var(--graphite); background: transparent;'"
          >
            <span class="material-symbols-outlined text-base">visibility</span>
            Resep Optik
          </button>
          <button
            @click="router.push('/orders')"
            class="flex items-center gap-3 text-left px-4 py-3 rounded-lg text-sm font-bold transition-all"
            :style="currentSection === 'orders'
              ? 'background: linear-gradient(135deg, var(--ink), #3d2c0e); color: white;'
              : 'color: var(--graphite); background: transparent;'"
          >
            <span class="material-symbols-outlined text-base">receipt_long</span>
            Riwayat Pesanan
          </button>
          <button
            @click="router.push('/wishlist')"
            class="flex items-center gap-3 text-left px-4 py-3 rounded-lg text-sm font-bold transition-all"
            :style="currentSection === 'wishlist'
              ? 'background: linear-gradient(135deg, var(--ink), #3d2c0e); color: white;'
              : 'color: var(--graphite); background: transparent;'"
          >
            <span class="material-symbols-outlined text-base">favorite</span>
            Wishlist
          </button>
          <button
            @click="router.push('/warranty')"
            class="flex items-center gap-3 text-left px-4 py-3 rounded-lg text-sm font-bold transition-all"
            :style="currentSection === 'warranty'
              ? 'background: linear-gradient(135deg, var(--ink), #3d2c0e); color: white;'
              : 'color: var(--graphite); background: transparent;'"
          >
            <span class="material-symbols-outlined text-base">shield</span>
            Garansi &amp; Servis
          </button>
          <button
            @click="router.push('/affiliate')"
            class="flex items-center gap-3 text-left px-4 py-3 rounded-lg text-sm font-bold transition-all"
            :style="currentSection === 'affiliate'
              ? 'background: linear-gradient(135deg, var(--ink), #3d2c0e); color: white;'
              : 'color: var(--graphite); background: transparent;'"
          >
            <span class="material-symbols-outlined text-base">group</span>
            Afiliasi &amp; Komisi
          </button>
          <div class="h-px my-1" style="background: rgba(184,138,68,0.15);"></div>
          <button
            @click="handleLogout"
            class="flex items-center gap-3 text-left px-4 py-3 rounded-lg text-sm font-bold transition-all"
            style="color: #dc2626;"
          >
            <span class="material-symbols-outlined text-base">logout</span>
            Keluar
          </button>
        </nav>
      </aside>

      <div class="min-w-0 flex-grow">
        
        <div v-if="currentSection === 'profile'" class="bg-surface-container-low p-6 lg:p-8 rounded-lg border border-outline-variant/15">
          <div class="flex flex-col gap-6 mb-8">
            <div class="flex items-center justify-between">
              <h2 class="font-headline text-2xl text-primary">Informasi Akun</h2>
              <div class="bg-secondary-fixed/30 text-secondary px-4 py-1.5 rounded-lg text-sm font-semibold flex items-center gap-1">
                <span class="material-symbols-outlined text-sm">stars</span>
                {{ authStore.user?.loyalty_points || 0 }} Loyalty Points
              </div>
            </div>

            <!-- Membership Card -->
            <div class="relative overflow-hidden p-6 border border-outline-variant/15 bg-white shadow-card group">
              <!-- Background Decorative Elements -->
              <div class="absolute -top-10 -right-10 w-40 h-40 bg-primary/5 rounded-full blur-3xl group-hover:bg-primary/10 transition-all"></div>
              
              <div class="relative z-10 flex flex-col md:flex-row md:items-center gap-8">
                <!-- Level Badge -->
                <div class="flex flex-col items-center justify-center p-4 min-w-[140px] border-2 border-primary/20 bg-primary/5">
                  <span class="material-symbols-outlined text-4xl mb-1" :style="{ color: currentLevel?.name === 'Platinum' ? 'var(--ink)' : (currentLevel?.name === 'Gold' ? 'var(--gold)' : 'var(--graphite)') }">
                    {{ currentLevel?.name === 'Platinum' ? 'workspace_premium' : 'stars' }}
                  </span>
                  <span class="text-xs font-black uppercase tracking-widest text-on-surface-variant">Level Anda</span>
                  <span class="text-xl font-black text-primary uppercase tracking-normal">{{ currentLevel?.name || 'Bronze' }}</span>
                </div>

                <!-- Progress & Benefits -->
                <div class="flex-grow space-y-4">
                  <div v-if="nextLevel" class="space-y-2">
                    <div class="flex justify-between items-end">
                      <p class="text-sm font-bold text-primary">Progres ke {{ nextLevel.name }}</p>
                      <p class="text-xs text-on-surface-variant font-medium">
                        {{ (nextLevel.min_points - (authStore.user?.loyalty_points || 0)).toLocaleString('id-ID') }} poin lagi
                      </p>
                    </div>
                    <div class="h-2 w-full bg-surface-container-highest overflow-hidden">
                      <div class="h-full bg-primary transition-all duration-1000 ease-out" :style="{ width: `${progressToNextLevel}%` }"></div>
                    </div>
                  </div>
                  <div v-else class="py-2">
                    <p class="text-sm font-black text-primary flex items-center gap-2">
                      <span class="material-symbols-outlined text-gold">verified</span>
                      Selamat! Anda telah mencapai level tertinggi (Platinum)
                    </p>
                  </div>

                  <div class="flex flex-wrap gap-x-6 gap-y-2">
                    <div v-if="currentLevel?.discount_percentage > 0" class="flex items-center gap-2 text-xs font-bold text-secondary">
                      <span class="material-symbols-outlined text-sm">percent</span>
                      Potongan Harga {{ currentLevel.discount_percentage }}%
                    </div>
                    <div v-if="currentLevel?.name === 'Platinum'" class="flex items-center gap-2 text-xs font-bold text-secondary">
                      <span class="material-symbols-outlined text-sm">local_shipping</span>
                      Bebas Biaya Kirim
                    </div>
                    <div v-if="currentLevel?.name === 'Gold' || currentLevel?.name === 'Platinum'" class="flex items-center gap-2 text-xs font-bold text-secondary">
                      <span class="material-symbols-outlined text-sm">priority</span>
                      Prioritas Layanan
                    </div>
                  </div>
                </div>
              </div>

              <!-- Level Description Tooltip/Hint -->
              <div class="mt-4 pt-4 border-t border-outline-variant/10">
                <p class="text-[11px] text-on-surface-variant leading-relaxed">
                  {{ currentLevel?.description || 'Terus kumpulkan poin untuk membuka level member yang lebih tinggi dan nikmati berbagai keuntungan eksklusif.' }}
                </p>
              </div>
            </div>
          </div>
          
          <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
              <label class="block text-xs text-on-surface-variant mb-1 uppercase tracking-wider">Full Name</label>
              <p class="font-medium text-primary text-lg">{{ authStore.user?.name || 'User' }}</p>
            </div>
            <div>
              <label class="block text-xs text-on-surface-variant mb-1 uppercase tracking-wider">Email Address</label>
              <p class="font-medium text-primary text-lg">{{ authStore.user?.email }}</p>
            </div>
          </div>
          
          <div class="mt-10">
            <h3 class="font-headline text-xl text-primary mb-4 border-b border-outline-variant/15 pb-2">Riwayat Poin Loyalitas</h3>
            <div v-if="isLoadingHistory" class="animate-pulse flex flex-col gap-2">
              <div class="h-10 bg-surface-container-highest rounded-lg w-full"></div>
              <div class="h-10 bg-surface-container-highest rounded-lg w-full"></div>
            </div>
            <div v-else-if="loyaltyHistory.length === 0" class="text-center py-6">
              <p class="text-sm text-on-surface-variant">Belum ada riwayat penggunaan atau penambahan poin.</p>
            </div>
            <div v-else class="overflow-x-auto">
              <table class="w-full text-left text-sm">
                <thead>
                  <tr class="text-on-surface-variant border-b border-outline-variant/15">
                    <th class="py-2 font-semibold">Tanggal</th>
                    <th class="py-2 font-semibold">Tipe</th>
                    <th class="py-2 font-semibold text-right">Poin</th>
                    <th class="py-2 font-semibold">Keterangan</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="log in loyaltyHistory" :key="log.id" class="border-b border-outline-variant/10 hover:bg-surface-container-highest/50 transition-colors">
                    <td class="py-3">{{ new Date(log.created_at).toLocaleString('id-ID', { year: 'numeric', month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' }) }}</td>
                    <td class="py-3">
                      <span :class="log.type === 'earned' ? 'text-olive bg-olive/10' : 'text-red-600 bg-red-50'" class="px-2 py-0.5 rounded text-xs font-medium uppercase">
                        {{ log.type === 'earned' ? 'Dapat' : 'Pakai' }}
                      </span>
                    </td>
                    <td class="py-3 text-right font-medium" :class="log.type === 'earned' ? 'text-olive' : 'text-red-600'">
                      {{ log.type === 'earned' ? '+' : '-' }}{{ Math.abs(log.points) }}
                    </td>
                    <td class="py-3 text-on-surface-variant text-xs">{{ log.description }}</td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
        </div>

        <div v-if="currentSection === 'addresses'">
           <div class="flex items-center justify-between mb-6">
             <h2 class="font-headline text-2xl text-primary">Shipping Addresses</h2>
             <button @click="openAddressModal" class="bg-primary text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-primary-container transition-colors">Tambah Alamat Baru</button>
           </div>
           
           <div class="grid grid-cols-1 gap-4">
              <div v-if="authStore.user?.addresses?.length > 0">
                 <div v-for="addr in authStore.user.addresses" :key="addr.id" class="p-6 bg-surface-container-low border border-outline-variant/15 rounded-lg">
                    <div class="flex justify-between items-start">
                       <div>
                          <p class="font-bold text-primary">{{ addr.recipient_name }} <span v-if="addr.is_default" class="ml-2 text-[10px] bg-secondary-fixed/30 text-secondary px-2 py-0.5 rounded-lg uppercase">Default</span></p>
                          <p class="text-sm text-on-surface-variant mt-1">{{ addr.phone }}</p>
                          <p class="text-sm text-on-surface mt-3">{{ addr.address }}</p>
                          <p class="text-sm text-on-surface">{{ addr.district }}, {{ addr.city }}, {{ addr.province }} {{ addr.postal_code }}</p>
                       </div>
                       <div class="flex gap-2">
                          <button @click="deleteAddress(addr.id)" class="text-error hover:text-error-container transition-colors"><span class="material-symbols-outlined">delete</span></button>
                       </div>
                    </div>
                 </div>
              </div>
              <div v-else class="text-center py-12 bg-surface-container-low rounded-lg">
                 <p class="text-on-surface-variant">No addresses saved yet.</p>
              </div>
           </div>
        </div>

        <div v-if="currentSection === 'prescriptions'">
          <div class="flex items-center justify-between mb-6">
            <h2 class="font-headline text-2xl text-primary">Resep Optik</h2>
            <button
              @click="showCreatePrescriptionForm = !showCreatePrescriptionForm"
              class="flex items-center gap-2 px-4 py-2 text-xs font-black uppercase tracking-wider text-white transition-all"
              style="background: linear-gradient(135deg, var(--ink) 0%, #3d2c0e 100%);"
            >
              <span class="material-symbols-outlined text-sm">{{ showCreatePrescriptionForm ? 'close' : 'add' }}</span>
              {{ showCreatePrescriptionForm ? 'Batal' : 'Tambah Resep' }}
            </button>
          </div>

          <!-- Form Tambah Resep Baru -->
          <div v-if="showCreatePrescriptionForm" class="mb-6 border p-6" style="background: var(--porcelain); border-color: rgba(184,138,68,0.3);">
            <h3 class="font-bold text-sm mb-4" style="color: var(--ink);">Tambah Resep Baru</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div>
                <label class="block text-xs font-bold uppercase tracking-wider mb-1" style="color: #5c4a3a;">Nama Resep *</label>
                <input v-model="newPrescriptionForm.label" type="text" placeholder="Contoh: Resep Utama 2026" class="input-field py-2" style="border-color: var(--mist);" />
              </div>
              <div>
                <label class="block text-xs font-bold uppercase tracking-wider mb-1" style="color: #5c4a3a;">Tipe Lensa</label>
                <select v-model="newPrescriptionForm.lens_type" class="input-field py-2" style="border-color: var(--mist);">
                  <option value="single_vision">Single Vision</option>
                  <option value="progressive">Progresif / Bifokal</option>
                </select>
                <p class="text-[11px] mt-2" style="color: #5c4a3a;">
                  Fitur lensa seperti Blue Light, Photochromic, dan High Index dipilih saat checkout, bukan di resep dasar.
                </p>
              </div>
            </div>

            <!-- Tabel Resep -->
            <div class="mt-4 overflow-x-auto">
              <table class="w-full text-sm">
                <thead>
                  <tr>
                    <th class="text-left py-2 pr-4 text-xs font-black uppercase tracking-wider" style="color: #5c4a3a; width: 60px;"></th>
                    <th class="text-center py-2 px-2 text-xs font-black uppercase tracking-wider" style="color: #5c4a3a;">SPH</th>
                    <th class="text-center py-2 px-2 text-xs font-black uppercase tracking-wider" style="color: #5c4a3a;">CYL</th>
                    <th class="text-center py-2 px-2 text-xs font-black uppercase tracking-wider" style="color: #5c4a3a;">Axis</th>
                    <th
                      v-if="supportsAddForNewPrescription"
                      class="text-center py-2 px-2 text-xs font-black uppercase tracking-wider"
                      style="color: #5c4a3a;"
                    >
                      ADD
                    </th>
                  </tr>
                </thead>
                <tbody>
                  <tr>
                    <td class="py-2 pr-4 font-black text-xs" style="color: var(--ink);">OD (Kanan)</td>
                    <td class="py-2 px-2"><input v-model="newPrescriptionForm.right_sphere" type="number" step="0.25" placeholder="0.00" class="w-full border px-2 py-1.5 text-center text-sm focus:outline-none" style="border-color: var(--mist);" /></td>
                    <td class="py-2 px-2"><input v-model="newPrescriptionForm.right_cylinder" type="number" step="0.25" placeholder="0.00" class="w-full border px-2 py-1.5 text-center text-sm focus:outline-none" style="border-color: var(--mist);" /></td>
                    <td class="py-2 px-2"><input v-model="newPrescriptionForm.right_axis" :disabled="!usesRightAxis" type="number" min="1" max="180" placeholder="—" class="w-full border px-2 py-1.5 text-center text-sm focus:outline-none disabled:bg-mist disabled:text-graphite/45 disabled:cursor-not-allowed" style="border-color: var(--mist);" /></td>
                    <td v-if="supportsAddForNewPrescription" class="py-2 px-2"><input v-model="newPrescriptionForm.right_add" type="number" step="0.25" min="0" max="5" placeholder="0.00" class="w-full border px-2 py-1.5 text-center text-sm focus:outline-none" style="border-color: var(--mist);" /></td>
                  </tr>
                  <tr>
                    <td class="py-2 pr-4 font-black text-xs" style="color: var(--ink);">OS (Kiri)</td>
                    <td class="py-2 px-2"><input v-model="newPrescriptionForm.left_sphere" type="number" step="0.25" placeholder="0.00" class="w-full border px-2 py-1.5 text-center text-sm focus:outline-none" style="border-color: var(--mist);" /></td>
                    <td class="py-2 px-2"><input v-model="newPrescriptionForm.left_cylinder" type="number" step="0.25" placeholder="0.00" class="w-full border px-2 py-1.5 text-center text-sm focus:outline-none" style="border-color: var(--mist);" /></td>
                    <td class="py-2 px-2"><input v-model="newPrescriptionForm.left_axis" :disabled="!usesLeftAxis" type="number" min="1" max="180" placeholder="—" class="w-full border px-2 py-1.5 text-center text-sm focus:outline-none disabled:bg-mist disabled:text-graphite/45 disabled:cursor-not-allowed" style="border-color: var(--mist);" /></td>
                    <td v-if="supportsAddForNewPrescription" class="py-2 px-2"><input v-model="newPrescriptionForm.left_add" type="number" step="0.25" min="0" max="5" placeholder="0.00" class="w-full border px-2 py-1.5 text-center text-sm focus:outline-none" style="border-color: var(--mist);" /></td>
                  </tr>
                </tbody>
              </table>
            </div>

            <!-- PD -->
            <div class="mt-4">
              <div class="flex items-center gap-6 mb-4">
                <label class="flex items-center gap-2 cursor-pointer text-sm" style="color: var(--graphite);">
                  <input type="radio" v-model="newPrescriptionPdMode" value="single" class="accent-gold" />
                  PD Tunggal
                </label>
                <label class="flex items-center gap-2 cursor-pointer text-sm" style="color: var(--graphite);">
                  <input type="radio" v-model="newPrescriptionPdMode" value="dual" class="accent-gold" />
                  PD Ganda
                </label>
              </div>

              <div v-if="newPrescriptionPdMode === 'single'" class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                  <label class="block text-xs font-bold uppercase tracking-wider mb-1" style="color: #5c4a3a;">PD Tunggal (mm)</label>
                  <input v-model="newPrescriptionForm.pd_single" type="number" min="50" max="75" step="0.5" placeholder="64" class="input-field py-2" style="border-color: var(--mist);" />
                  <p class="text-[10px] mt-1" style="color: #6b5748;">Rentang umum 50 - 75 mm.</p>
                </div>
              </div>

              <div v-else class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                  <label class="block text-xs font-bold uppercase tracking-wider mb-1" style="color: #5c4a3a;">PD Kanan (mm)</label>
                  <input v-model="newPrescriptionForm.pd_right" type="number" min="25" max="38" step="0.5" placeholder="32" class="input-field py-2" style="border-color: var(--mist);" />
                  <p class="text-[10px] mt-1" style="color: #6b5748;">Rentang umum 25 - 38 mm.</p>
                </div>
                <div>
                  <label class="block text-xs font-bold uppercase tracking-wider mb-1" style="color: #5c4a3a;">PD Kiri (mm)</label>
                  <input v-model="newPrescriptionForm.pd_left" type="number" min="25" max="38" step="0.5" placeholder="32" class="input-field py-2" style="border-color: var(--mist);" />
                  <p class="text-[10px] mt-1" style="color: #6b5748;">Rentang umum 25 - 38 mm.</p>
                </div>
              </div>
            </div>

            <!-- Notes + Attachment -->
            <div class="mt-4 grid grid-cols-1 md:grid-cols-2 gap-4">
              <div>
                <label class="block text-xs font-bold uppercase tracking-wider mb-1" style="color: #5c4a3a;">Catatan</label>
                <textarea v-model="newPrescriptionForm.notes" rows="2" placeholder="Catatan tambahan..." class="input-field py-2" style="border-color: var(--mist);"></textarea>
              </div>
              <div>
                <label class="block text-xs font-bold uppercase tracking-wider mb-1" style="color: #5c4a3a;">Upload Resep (Opsional)</label>
                <input type="file" accept=".jpg,.jpeg,.png,.webp,.pdf" @change="handleNewPrescriptionFile" class="block w-full text-sm" />
                <p class="text-[10px] mt-1" style="color: #6b5748;">JPG, PNG, WEBP, atau PDF. Maks 4 MB.</p>
              </div>
            </div>

            <div class="mt-4 flex items-center gap-3">
              <label class="flex items-center gap-2 cursor-pointer text-sm" style="color: var(--graphite);">
                <input type="checkbox" v-model="newPrescriptionForm.is_default" class="accent-gold" />
                Jadikan resep default
              </label>
            </div>

            <div class="mt-5 flex gap-3">
              <button
                @click="createNewPrescription"
                :disabled="isCreatingPrescription"
                class="px-6 py-3 text-xs font-black uppercase tracking-wider text-white disabled:opacity-50 transition-all"
                style="background: linear-gradient(135deg, var(--ink) 0%, #3d2c0e 100%);"
              >
                <span v-if="isCreatingPrescription" class="material-symbols-outlined animate-spin text-sm align-middle mr-1">sync</span>
                {{ isCreatingPrescription ? 'Menyimpan...' : 'Simpan Resep' }}
              </button>
              <button @click="showCreatePrescriptionForm = false" class="px-4 py-3 border text-xs font-black uppercase tracking-wider" style="border-color: var(--mist); color: #5c4a3a;">
                Batal
              </button>
            </div>
          </div>

          <div v-if="isLoadingPrescriptions" class="py-12 text-center text-on-surface-variant">
            Memuat resep...
          </div>

          <div v-else-if="prescriptions.length === 0" class="text-center py-12 bg-surface-container-low rounded-lg">
            <p class="text-on-surface-variant">Belum ada resep tersimpan.</p>
          </div>

          <div v-else class="grid grid-cols-1 gap-4">
            <div
              v-for="profile in prescriptions"
              :key="profile.id"
              class="p-6 bg-surface-container-low border border-outline-variant/15 rounded-lg"
            >
              <div class="flex flex-col md:flex-row md:items-start md:justify-between gap-4">
                <div class="flex-1">
                  <div v-if="editingPrescriptionId === profile.id" class="grid grid-cols-1 md:grid-cols-2 gap-3 mb-4">
                    <label class="block">
                      <span class="block text-xs text-on-surface-variant mb-1 uppercase tracking-wider">Nama Resep</span>
                      <input v-model="prescriptionEditForm.label" class="w-full border border-outline-variant/30 rounded-lg px-3 py-2 bg-white" />
                    </label>
                    <label class="block md:col-span-2">
                      <span class="block text-xs text-on-surface-variant mb-1 uppercase tracking-wider">Catatan</span>
                      <textarea v-model="prescriptionEditForm.notes" rows="2" class="w-full border border-outline-variant/30 rounded-lg px-3 py-2 bg-white"></textarea>
                    </label>
                  </div>

                  <div v-else>
                    <p class="font-bold text-primary">
                      {{ profile.label }}
                      <span v-if="profile.is_default" class="ml-2 text-[10px] bg-secondary-fixed/30 text-secondary px-2 py-0.5 rounded-lg uppercase">Default</span>
                      <span v-if="profile.verification_status === 'approved'" class="ml-2 text-[10px] bg-olive/10 text-olive px-2 py-0.5 rounded-lg uppercase">Disetujui</span>
                      <span v-else-if="profile.verification_status === 'rejected'" class="ml-2 text-[10px] bg-red-100 text-red-800 px-2 py-0.5 rounded-lg uppercase">Ditolak</span>
                      <span v-else-if="profile.verified_at" class="ml-2 text-[10px] bg-olive/10 text-olive px-2 py-0.5 rounded-lg uppercase">Terverifikasi</span>
                    </p>
                    <p class="text-sm text-on-surface-variant mt-1">{{ formatLensTypeLabel(profile.lens_type || 'single_vision') }}</p>
                    <!-- Admin notes jika ada -->
                    <div v-if="profile.admin_notes" class="mt-2 p-2 text-xs rounded" :style="profile.verification_status === 'rejected' ? 'background: rgba(239,68,68,0.06); color: #dc2626;' : 'background: rgba(22,163,74,0.06); color: #16a34a;'">
                      <strong>Catatan Admin:</strong> {{ profile.admin_notes }}
                    </div>
                  </div>

                  <div class="grid grid-cols-2 md:grid-cols-4 gap-3 mt-4 text-sm">
                    <div><span class="text-on-surface-variant">OD SPH</span><p class="font-medium text-primary">{{ profile.right_sphere ?? '-' }}</p></div>
                    <div><span class="text-on-surface-variant">OD CYL</span><p class="font-medium text-primary">{{ profile.right_cylinder ?? '-' }}</p></div>
                    <div><span class="text-on-surface-variant">OS SPH</span><p class="font-medium text-primary">{{ profile.left_sphere ?? '-' }}</p></div>
                    <div><span class="text-on-surface-variant">PD</span><p class="font-medium text-primary">{{ profile.pd_single ?? `${profile.pd_right ?? '-'} / ${profile.pd_left ?? '-'}` }}</p></div>
                  </div>
                </div>

                <div class="flex flex-wrap md:flex-col gap-2 md:items-end">
                  <template v-if="editingPrescriptionId === profile.id">
                    <button @click="savePrescriptionEdit(profile)" class="px-4 py-2 rounded-lg text-xs font-bold uppercase tracking-wide bg-primary text-white">Simpan</button>
                    <button @click="editingPrescriptionId = null" class="px-4 py-2 rounded-lg text-xs font-bold uppercase tracking-wide border border-outline-variant/30 text-on-surface">Batal</button>
                  </template>
                  <template v-else>
                    <button @click="startEditPrescription(profile)" class="px-4 py-2 rounded-lg text-xs font-bold uppercase tracking-wide border border-outline-variant/30 text-on-surface">Edit</button>
                    <button v-if="!profile.is_default" @click="setDefaultPrescription(profile.id)" class="px-4 py-2 rounded-lg text-xs font-bold uppercase tracking-wide bg-primary text-white">Set Default</button>
                    <button @click="deletePrescription(profile.id)" class="px-4 py-2 rounded-lg text-xs font-bold uppercase tracking-wide text-error border border-error/20">Hapus</button>
                  </template>
                </div>
              </div>
            </div>
          </div>
        </div>

        <div v-if="currentSection === 'orders'">
          <h2 class="font-headline text-2xl text-primary mb-6">Order History</h2>
          <!-- Order Status Filter Tabs -->
          <div class="flex flex-wrap gap-2 mb-6">
            <button v-for="tab in orderStatusTabs" :key="tab.value" @click="orderStatusFilter = tab.value"
              class="px-3 py-1.5 text-xs font-black uppercase tracking-wider transition-all"
              :style="orderStatusFilter === tab.value ? 'background: linear-gradient(135deg, var(--ink), #3d2c0e); color: white;' : 'background: rgba(184,138,68,0.08); color: #7a6230; border: 1px solid rgba(184,138,68,0.2);'"
            >{{ tab.label }}</button>
          </div>
          
          <div v-if="isLoadingOrders" class="animate-pulse flex flex-col gap-4">
            <div class="h-24 bg-surface-container-low rounded-lg w-full"></div>
            <div class="h-24 bg-surface-container-low rounded-lg w-full"></div>
          </div>
          
          <div v-else-if="orders.length === 0" class="text-center py-12 bg-surface-container-low rounded-lg">
            <p class="text-on-surface-variant">You haven't placed any orders yet.</p>
          </div>
          
          <div v-else class="flex flex-col gap-4">
            <div v-if="filteredOrders.length === 0" class="text-center py-10 bg-surface-container-low rounded-lg">
              <p class="text-on-surface-variant">Tidak ada pesanan pada status ini.</p>
            </div>
            <template v-else>
            <div v-for="order in filteredOrders" :key="order.id" class="bg-surface-container-low p-6 rounded-lg border border-outline-variant/15 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 hover:shadow-card transition-shadow">
              <div class="flex items-center gap-4">
                <!-- Product Thumbnail Preview -->
                <div v-if="order.items && order.items.length > 0" class="w-16 h-16 shrink-0 bg-mist border p-1 rounded-lg flex items-center justify-center overflow-hidden">
                  <img alt="" 
                    :src="resolveImageUrl(order.items[0].product, order.items[0].product?.name)" 
                    class="w-full h-full object-contain mix-blend-multiply" loading="lazy" decoding="async" />
                </div>
                <div v-else class="w-16 h-16 shrink-0 bg-ivory border p-1 rounded-lg flex items-center justify-center">
                  <span class="material-symbols-outlined text-graphite/40">shopping_bag</span>
                </div>

                <div>
                  <p class="text-xs text-on-surface-variant uppercase tracking-wider mb-1">Order #{{ order.order_number }}</p>
                  <p class="font-medium text-primary">Rp {{ (Number(order.total_price) || 0).toLocaleString('id-ID') }}</p>
                  <p class="text-sm text-on-surface-variant mt-1">{{ new Date(order.created_at).toLocaleString('id-ID', { year: 'numeric', month: 'long', day: 'numeric', hour: '2-digit', minute: '2-digit' }) }}</p>
                </div>
              </div>
              
              <div class="flex items-center gap-3 flex-wrap justify-end">
                <span :class="['px-3 py-1 rounded text-xs font-medium uppercase', orderStatusClass(order.status)]">
                  {{ orderStatusLabel(order.status) }}
                </span>
                <button
                  v-if="normalizeOrderStatus(order.status) === 'shipped'"
                  @click="confirmDelivery(order.id)"
                  :disabled="confirmingOrderId === order.id"
                  class="px-4 py-2 rounded-lg text-xs font-bold uppercase tracking-wide bg-primary text-white hover:bg-primary-container transition-colors disabled:opacity-50"
                >
                  {{ confirmingOrderId === order.id ? 'Menyimpan...' : 'Konfirmasi Diterima' }}
                </button>
                <button @click="router.push(`/orders/${order.id}`)" class="text-sm font-medium text-primary hover:text-secondary transition-colors underline underline-offset-4">
                  View Details
                </button>
              </div>
            </div>
            </template>

            <button
              v-if="canLoadMoreOrders"
              @click="loadMoreOrders"
              :disabled="isLoadingMoreOrders"
              class="self-center mt-2 px-6 py-3 rounded-lg text-sm font-medium bg-primary text-white hover:bg-primary-container transition-colors disabled:opacity-50"
            >
              {{ isLoadingMoreOrders ? 'Loading...' : 'Load More' }}
            </button>
          </div>
        </div>

        <div v-if="currentSection === 'wishlist'">
          <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 mb-6">
            <div>
              <h2 class="font-headline text-2xl text-primary">Wishlist</h2>
              <p class="text-sm text-on-surface-variant mt-1">Bagikan produk favorit tanpa membuka data akun Anda.</p>
            </div>
            <button
              v-if="wishlistStore.items && wishlistStore.items.length > 0"
              @click="shareWishlist"
              :disabled="isSharingWishlist"
              class="px-4 py-2 rounded-lg text-xs font-bold uppercase tracking-wide bg-primary text-white hover:bg-primary-container transition-colors disabled:opacity-50 flex items-center gap-2"
            >
              <span class="material-symbols-outlined text-base">ios_share</span>
              {{ isSharingWishlist ? 'Membuat...' : 'Salin Link' }}
            </button>
          </div>

          <div v-if="!wishlistStore.items || wishlistStore.items.length === 0" class="text-center py-12 bg-surface-container-low rounded-lg">
            <p class="text-on-surface-variant">Belum ada produk di wishlist Anda.</p>
          </div>

          <div v-else class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div
              v-for="product in wishlistStore.items"
              :key="product.id"
              class="bg-surface-container-low p-5 rounded-lg border border-outline-variant/15 flex gap-4 items-start"
            >
              <div class="w-20 h-20 shrink-0 bg-mist border p-2 rounded-lg flex items-center justify-center overflow-hidden">
                <img alt=""
                  :src="resolveImageUrl(product, product.name)"
                  class="w-full h-full object-contain mix-blend-multiply" loading="lazy" decoding="async" />
              </div>

              <div class="flex-grow">
                <p class="text-xs uppercase tracking-wider text-on-surface-variant mb-1">{{ product.name }}</p>
                <h3 class="font-bold text-primary">{{ product.brand || product.name }}</h3>
                <p class="text-sm mt-2 text-on-surface-variant">
                  Rating {{ Number(product.avg_rating || 0).toFixed(1) }} · {{ product.review_count || 0 }} ulasan
                </p>
                <p class="text-sm font-semibold text-primary mt-1">
                  Rp {{ Number(product.price || 0).toLocaleString('id-ID') }}
                </p>
                <div class="flex items-center gap-4 mt-4">
                  <button @click="router.push(`/products/${product.slug}`)" class="text-sm font-medium text-primary hover:text-secondary transition-colors underline underline-offset-4">
                    Lihat Detail
                  </button>
                  <button @click="wishlistStore.removeFromWishlist(product.id)" class="text-sm font-medium text-red-600 hover:text-red-700 transition-colors">
                    Hapus
                  </button>
                </div>
              </div>
            </div>
          </div>
        </div>

        <div v-if="currentSection === 'warranty'">
          <WarrantyPage embedded />
        </div>

        <!-- Affiliate Section -->
        <div v-if="currentSection === 'affiliate'" class="space-y-6">
          <h2 class="font-black text-2xl" style="color: var(--ink); font-family: 'Plus Jakarta Sans', Inter, system-ui, sans-serif;">Afiliasi &amp; Komisi</h2>
          <div v-if="isLoadingAffiliate" class="flex items-center gap-2 py-8">
            <span class="material-symbols-outlined animate-spin" style="color: var(--gold);">sync</span>
            <span class="text-sm text-graphite/65">Memuat data afiliasi...</span>
          </div>
          <div v-else-if="!affiliateProfile" class="bg-white p-8 border border-mist text-center">
            <span class="material-symbols-outlined text-5xl block mb-4" style="color: var(--gold);">groups</span>
            <h3 class="font-black text-xl mb-2" style="color: var(--ink);">Program Afiliasi Optik Medio</h3>
            <p class="text-sm text-graphite/65 mb-6 max-w-md mx-auto">Dapatkan komisi dari setiap penjualan melalui link referral Anda. Pendaftaran memerlukan persetujuan admin.</p>
            <button @click="applyAffiliate" :disabled="isApplyingAffiliate" class="px-8 py-3 font-black text-sm text-white uppercase tracking-wider disabled:opacity-50" style="background: linear-gradient(135deg, var(--ink), #3d2c0e);">
              {{ isApplyingAffiliate ? 'Memproses...' : 'Daftar Afiliator' }}
            </button>
          </div>
          <div v-else class="space-y-6">
            <div class="bg-white p-6 border border-mist">
              <div class="flex justify-between items-start mb-6">
                <div>
                  <p class="text-xs font-black uppercase tracking-widest mb-1" style="color: var(--gold);">Kode Afiliasi</p>
                  <p class="font-black text-2xl" style="color: var(--ink); font-family: Inter, system-ui, sans-serif;">{{ affiliateProfile.affiliate_code }}</p>
                </div>
                <span class="px-4 py-1.5 text-xs font-black uppercase" :style="affiliateProfile.status === 'approved' ? 'background: rgba(22,163,74,0.1); color: #16a34a;' : 'background: rgba(245,158,11,0.1); color: #d97706;'">{{ affiliateProfile.status }}</span>
              </div>
              <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                <div class="text-center p-3 border border-mist"><p class="text-2xl font-black" style="color: var(--ink);">{{ affiliateSummary?.referrals_count || 0 }}</p><p class="text-[10px] uppercase text-graphite/65 mt-1">Referral</p></div>
                <div class="text-center p-3 border border-mist"><p class="text-lg font-black" style="color: #16a34a;">{{ formatMoney(affiliateSummary?.available_balance) }}</p><p class="text-[10px] uppercase text-graphite/65 mt-1">Saldo</p></div>
                <div class="text-center p-3 border border-mist"><p class="text-lg font-black" style="color: #d97706;">{{ formatMoney(affiliateSummary?.locked_balance) }}</p><p class="text-[10px] uppercase text-graphite/65 mt-1">Diproses</p></div>
                <div class="text-center p-3 border border-mist"><p class="text-lg font-black" style="color: var(--gold);">{{ affiliateProfile.commission_rate_percentage }}%</p><p class="text-[10px] uppercase text-graphite/65 mt-1">Komisi</p></div>
              </div>
            </div>
            <div class="bg-white p-6 border border-mist">
              <div class="flex items-start justify-between gap-4 mb-4">
                <div>
                  <h3 class="font-black text-base" style="color: var(--ink);">Rekening Pencairan</h3>
                  <p class="text-xs text-graphite/65 mt-1">Dipakai admin untuk transfer komisi. Data ini akan disalin ke setiap request pencairan.</p>
                </div>
                <span class="text-[10px] font-black uppercase px-2 py-1" :style="hasCompletePayoutProfile ? 'background: rgba(22,163,74,0.1); color: #16a34a;' : 'background: rgba(245,158,11,0.1); color: #d97706;'">
                  {{ hasCompletePayoutProfile ? 'Lengkap' : 'Belum Lengkap' }}
                </span>
              </div>
              <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                <div>
                  <label class="block text-xs font-black uppercase tracking-widest text-graphite/65 mb-2">Bank / E-Wallet</label>
                  <input v-model="payoutProfileForm.payout_bank_name" type="text" placeholder="BCA, BRI, Mandiri, DANA" class="input-field" style="border-color: var(--mist);" />
                </div>
                <div>
                  <label class="block text-xs font-black uppercase tracking-widest text-graphite/65 mb-2">Nomor Rekening / Akun</label>
                  <input v-model="payoutProfileForm.payout_account_number" type="text" placeholder="Nomor rekening" class="input-field" style="border-color: var(--mist);" />
                </div>
                <div>
                  <label class="block text-xs font-black uppercase tracking-widest text-graphite/65 mb-2">Nama Pemilik</label>
                  <input v-model="payoutProfileForm.payout_account_name" type="text" placeholder="Nama sesuai rekening" class="input-field" style="border-color: var(--mist);" />
                </div>
                <div class="md:col-span-3">
                  <label class="block text-xs font-black uppercase tracking-widest text-graphite/65 mb-2">Catatan</label>
                  <textarea v-model="payoutProfileForm.payout_notes" rows="2" placeholder="Opsional, misalnya cabang bank atau catatan admin" class="input-field" style="border-color: var(--mist);"></textarea>
                </div>
              </div>
              <div class="mt-4 flex justify-end">
                <button @click="savePayoutProfile" :disabled="isSavingPayoutProfile || !hasCompletePayoutProfile" class="px-6 py-3 text-sm font-black text-white uppercase disabled:opacity-50" style="background: linear-gradient(135deg, var(--ink), #3d2c0e);">
                  {{ isSavingPayoutProfile ? 'Menyimpan...' : 'Simpan Rekening' }}
                </button>
              </div>
            </div>
            <div v-if="affiliateProfile.status === 'approved'" class="bg-white p-6 border border-mist">
              <h3 class="font-black text-base mb-4" style="color: var(--ink);">Ajukan Pencairan</h3>
              <p class="text-xs text-graphite/65 mb-3">Saldo tersedia {{ formatMoney(affiliateSummary?.available_balance) }}. Minimal pencairan {{ formatMoney(affiliateSummary?.minimum_payout_amount || 10000) }}.</p>
              <div class="flex gap-3">
                <input v-model.number="payoutAmount" type="number" :min="affiliateSummary?.minimum_payout_amount || 10000" :max="affiliateSummary?.available_balance || 0" placeholder="Jumlah pencairan (Rp)" class="flex-grow border px-4 py-3 text-sm focus:outline-none" style="border-color: var(--mist);" />
                <button @click="requestPayout" :disabled="isRequestingPayout || !hasCompletePayoutProfile || !payoutAmount || payoutAmount > (affiliateSummary?.available_balance || 0)" class="px-6 py-3 text-sm font-black text-white uppercase disabled:opacity-50" style="background: linear-gradient(135deg, var(--ink), #3d2c0e);">{{ isRequestingPayout ? 'Proses...' : 'Request' }}</button>
              </div>
              <p v-if="!hasCompletePayoutProfile" class="text-xs text-red-600 mt-3">Lengkapi rekening pencairan sebelum membuat request.</p>
            </div>
            <div class="bg-white p-6 border border-mist">
              <div class="flex items-center justify-between gap-3 mb-4">
                <h3 class="font-black text-base" style="color: var(--ink);">Order Komisi</h3>
                <span class="text-xs font-black uppercase tracking-widest" style="color: var(--gold);">{{ affiliateEarnings.length }} order</span>
              </div>
              <div v-if="affiliateEarnings.length === 0" class="text-center py-6 text-graphite/45 text-sm">Belum ada order referral yang selesai.</div>
              <div v-else class="flex flex-col gap-3">
                <div v-for="earning in affiliateEarnings" :key="earning.order_id" class="flex flex-col md:flex-row md:items-center justify-between gap-3 p-4 border border-mist">
                  <div>
                    <p class="font-black text-sm" style="color: var(--ink);">{{ earning.order_number }}</p>
                    <p class="text-xs text-graphite/65">{{ earning.customer_name }} · {{ earning.delivered_at ? new Date(earning.delivered_at).toLocaleDateString('id-ID') : earning.status }}</p>
                  </div>
                  <div class="grid grid-cols-3 gap-3 text-right">
                    <div><p class="text-[10px] uppercase tracking-widest text-graphite/45">Order</p><p class="font-bold text-xs" style="color: var(--ink);">{{ formatMoney(earning.base_amount) }}</p></div>
                    <div><p class="text-[10px] uppercase tracking-widest text-graphite/45">Komisi</p><p class="font-bold text-xs" style="color: #16a34a;">{{ formatMoney(earning.total_commission) }}</p></div>
                    <div><p class="text-[10px] uppercase tracking-widest text-graphite/45">Tersedia</p><p class="font-bold text-xs" :style="earning.is_available_for_payout ? 'color: #16a34a;' : 'color: #5c4a3a;'">{{ formatMoney(earning.remaining_commission) }}</p></div>
                  </div>
                </div>
              </div>
            </div>
            <div class="bg-white p-6 border border-mist">
              <h3 class="font-black text-base mb-4" style="color: var(--ink);">Histori Pencairan</h3>
              <div v-if="affiliateCommissions.length === 0" class="text-center py-6 text-graphite/45 text-sm">Belum ada histori pencairan.</div>
              <div v-else class="flex flex-col gap-3">
                <div v-for="comm in affiliateCommissions" :key="comm.id" class="flex items-center justify-between p-4 border border-mist">
                  <div><p class="font-bold text-sm" style="color: var(--ink);">{{ comm.request_no }}</p><p class="text-xs text-graphite/65">{{ comm.requested_at ? new Date(comm.requested_at).toLocaleDateString('id-ID') : '-' }}</p></div>
                  <div class="text-right"><p class="font-black text-sm" style="color: var(--ink);">Rp {{ Number(comm.requested_amount).toLocaleString('id-ID') }}</p><span class="text-[10px] font-black uppercase px-2 py-0.5" :style="comm.status === 'success' ? 'background: rgba(22,163,74,0.1); color: #16a34a;' : 'background: rgba(245,158,11,0.1); color: #d97706;'">{{ comm.status }}</span></div>
                </div>
              </div>
            </div>
          </div>
        </div>


      </div>
    </div>
  </main>

  <!-- Address Modal -->
  <Teleport to="body">
    <div v-if="showAddressModal" role="dialog" aria-modal="true" aria-labelledby="address-modal-title" class="fixed inset-0 z-[9999] flex items-center justify-center bg-black/60 backdrop-blur-sm px-6 py-10">
      <div class="bg-surface-container-low w-full max-w-2xl rounded-lg p-8 max-h-full overflow-y-auto shadow-soft border border-outline-variant/20">
        <div class="flex justify-between items-center mb-8">
          <h3 id="address-modal-title" class="text-2xl font-headline text-primary">Tambah Alamat Baru</h3>
          <button @click="showAddressModal = false" aria-label="Tutup dialog tambah alamat" class="text-on-surface-variant hover:text-primary transition-colors p-2 hover:bg-surface-container-highest rounded-lg flex items-center justify-center">
            <span class="material-symbols-outlined" aria-hidden="true">close</span>
          </button>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
          <div class="md:col-span-2">
            <label class="block text-sm font-semibold text-on-surface-variant mb-2">Nama Penerima</label>
            <input v-model="addressForm.recipient_name" type="text" class="w-full bg-surface-container-highest p-4 rounded-lg border-0 ring-1 ring-inset ring-outline-variant/30 focus:ring-2 focus:ring-secondary transition-all" placeholder="Contoh: Farhan" />
          </div>
          <div>
            <label class="block text-sm font-semibold text-on-surface-variant mb-2">Nomor Telepon</label>
            <input v-model="addressForm.phone" type="text" class="w-full bg-surface-container-highest p-4 rounded-lg border-0 ring-1 ring-inset ring-outline-variant/30 focus:ring-2 focus:ring-secondary transition-all" placeholder="Contoh: 08123456789" />
          </div>
          <div>
            <label class="block text-sm font-semibold text-on-surface-variant mb-2">Provinsi</label>
            <select v-model="addressForm.province_id" class="w-full bg-surface-container-highest p-4 rounded-lg border-0 ring-1 ring-inset ring-outline-variant/30 focus:ring-2 focus:ring-secondary transition-all">
              <option value="">{{ isProvLoading ? 'Memuat...' : 'Pilih Provinsi' }}</option>
              <option v-for="prov in provinces" :key="prov.id || (prov as any).province_id" :value="prov.id || (prov as any).province_id">{{ prov.name || (prov as any).province_name || (prov as any).province }}</option>
            </select>
          </div>
          <div>
            <label class="block text-sm font-semibold text-on-surface-variant mb-2">Kota/Kabupaten</label>
            <select v-model="addressForm.city_id" :disabled="!addressForm.province_id" class="w-full bg-surface-container-highest p-4 rounded-lg border-0 ring-1 ring-inset ring-outline-variant/30 focus:ring-2 focus:ring-secondary transition-all">
              <option value="">{{ isCityLoading ? 'Memuat...' : 'Pilih Kota/Kabupaten' }}</option>
              <option v-for="city in cities" :key="city.id" :value="city.id">{{ city.name }}</option>
            </select>
          </div>
          <div>
            <label class="block text-sm font-semibold text-on-surface-variant mb-2">Kecamatan</label>
            <select v-model="addressForm.district_id" :disabled="!addressForm.city_id" class="w-full bg-surface-container-highest p-4 rounded-lg border-0 ring-1 ring-inset ring-outline-variant/30 focus:ring-2 focus:ring-secondary transition-all">
              <option value="">{{ isDistLoading ? 'Memuat...' : 'Pilih Kecamatan' }}</option>
              <option v-for="dist in districts" :key="dist.id" :value="dist.id">{{ dist.name }}</option>
            </select>
          </div>
          <div class="md:col-span-2">
            <label class="block text-sm font-semibold text-on-surface-variant mb-2">Alamat Lengkap</label>
            <textarea v-model="addressForm.address" rows="3" class="w-full bg-surface-container-highest p-4 rounded-lg border-0 ring-1 ring-inset ring-outline-variant/30 focus:ring-2 focus:ring-secondary transition-all" placeholder="Nama jalan, nomor rumah, patokan, dan detail lainnya"></textarea>
          </div>
          <div>
            <label class="block text-sm font-semibold text-on-surface-variant mb-2">Kode Pos</label>
            <input v-model="addressForm.postal_code" type="text" class="w-full bg-surface-container-highest p-4 rounded-lg border-0 ring-1 ring-inset ring-outline-variant/30 focus:ring-2 focus:ring-secondary transition-all" />
          </div>
          <div class="flex items-center gap-3 py-2">
            <input v-model="addressForm.is_default" type="checkbox" id="is_default" class="w-5 h-5 rounded border-outline-variant/30 text-secondary focus:ring-secondary" />
            <label for="is_default" class="text-sm font-medium text-on-surface cursor-pointer select-none">Jadikan Alamat Utama</label>
          </div>
        </div>

        <div class="mt-10 flex gap-4 border-t border-outline-variant/10 pt-4">
          <button @click="showAddressModal = false" class="flex-grow py-4 rounded-lg font-bold text-primary hover:bg-surface-container-highest transition-all">Batal</button>
          <button @click="saveAddress" :disabled="isSavingAddress" class="flex-grow py-4 rounded-lg font-bold transition-all shadow-card disabled:opacity-50" style="background: linear-gradient(135deg, var(--ink) 0%, #3d2c0e 100%); color: #fff;">
            {{ isSavingAddress ? 'Menyimpan...' : 'Simpan Alamat' }}
          </button>
        </div>
      </div>
    </div>
  </Teleport>
</template>

<style scoped>
/* Phase 8 — Profile/Account polish (additive, tidak ganti markup) */

/* Container & spacing responsive */
main.container-commerce {
  padding-top: clamp(96px, 12vw, 160px);
  padding-bottom: clamp(48px, 8vw, 96px);
}

/* ─── Sidebar nav ─── */
aside {
  /* Tetap di top desktop saat scroll, hindari sticky di mobile (terlalu ribet
     dengan section panjang). */
}

@media (min-width: 1024px) {
  aside { position: sticky; top: calc(var(--header-height, 72px) + 24px); }
}

/* Mobile: ubah sidebar jadi horizontal scroll chips */
@media (max-width: 1023.98px) {
  aside nav {
    display: flex !important;
    flex-direction: row !important;
    flex-wrap: nowrap !important;
    gap: 8px !important;
    padding: 8px !important;
    overflow-x: auto;
    -webkit-overflow-scrolling: touch;
    scrollbar-width: thin;
  }

  aside nav button {
    flex-shrink: 0;
    white-space: nowrap;
    padding: 10px 14px !important;
    min-height: 40px;
    border-radius: 999px !important;
  }

  aside nav button .material-symbols-outlined {
    font-size: 16px;
  }

  /* Hide divider line + logout button di scroll bar mobile.
     Logout tetap accessible via TopNavBar hamburger drawer. */
  aside nav > div[class*="h-px"] { display: none; }
  aside nav > button:last-child { /* logout */
    margin-left: 8px;
    padding-left: 16px !important;
    border-left: 1px solid var(--mist);
    border-radius: 999px !important;
  }
}

/* Desktop sidebar: refine button styling */
@media (min-width: 1024px) {
  aside nav button {
    min-height: 44px;
    transition: background-color 200ms ease, color 200ms ease;
  }

  aside nav button .material-symbols-outlined {
    flex-shrink: 0;
  }
}

/* ─── Main content area ─── */
main.container-commerce > div.flex {
  gap: clamp(16px, 2.4vw, 32px);
}

/* Section card normalize — kebanyakan section dipakai .bg-surface-container-low
   atau utility class lain. Kasih border, background, radius konsisten. */
main.container-commerce > div.flex > div.min-w-0 > div {
  border-radius: 10px;
}

/* Mobile: section padding lebih ringkas */
@media (max-width: 767.98px) {
  main.container-commerce > div.flex > div.min-w-0 > div.p-6,
  main.container-commerce > div.flex > div.min-w-0 > div.p-8 {
    padding: 16px !important;
  }

  main.container-commerce > div.flex > div.min-w-0 h2 {
    font-size: 1.25rem !important;
  }

  /* Kompakkan loyalty card */
  main.container-commerce .relative.overflow-hidden.p-6 {
    padding: 18px !important;
  }
}

/* Tap target minimum untuk semua action button di section */
main.container-commerce button,
main.container-commerce a[role="button"],
main.container-commerce .button {
  min-height: 36px;
}

/* Order tab status: scroll horizontal di mobile, tidak wrap */
main.container-commerce [class*="flex"]:has(> button[class*="orderStatusFilter"]) {
  overflow-x: auto;
}

/* Form inputs: konsistenkan dengan design system delta */
main.container-commerce input[type="text"],
main.container-commerce input[type="email"],
main.container-commerce input[type="tel"],
main.container-commerce input[type="number"],
main.container-commerce input[type="password"],
main.container-commerce textarea,
main.container-commerce select {
  border-radius: 8px;
  font-size: 14px;
  min-height: 44px;
}

main.container-commerce input[type="text"]:focus,
main.container-commerce input[type="email"]:focus,
main.container-commerce input[type="tel"]:focus,
main.container-commerce input[type="number"]:focus,
main.container-commerce input[type="password"]:focus,
main.container-commerce textarea:focus,
main.container-commerce select:focus {
  outline: none;
  border-color: var(--gold) !important;
  box-shadow: 0 0 0 3px rgba(184, 138, 68, 0.13);
}

/* Order/wishlist/prescription card hover */
main.container-commerce [class*="hover:shadow"]:not(button):not(a) {
  transition: box-shadow 200ms ease, border-color 200ms ease, transform 200ms ease;
}

/* ─── Modal global polish (Teleport-ed dialogs di Profile) ─── */
/* Tidak bisa target via scoped, biarkan markup existing menangani modal style. */

/* Loyalty membership card — pastikan tidak overflow */
main.container-commerce .relative.overflow-hidden {
  contain: layout style;
}

/* Empty state polish: kalau ada empty state inline (Wishlist kosong, dll) */
main.container-commerce .text-center.py-16,
main.container-commerce .text-center.py-20,
main.container-commerce .text-center.py-24 {
  padding: clamp(40px, 6vw, 80px) 24px;
}

/* Address card grid responsive */
@media (max-width: 767.98px) {
  main.container-commerce [class*="grid-cols-2"] {
    grid-template-columns: 1fr !important;
  }
}
</style>
