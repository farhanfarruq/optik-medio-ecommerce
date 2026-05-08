<script setup lang="ts">
import { computed, ref, onMounted, watch } from 'vue';
import { useAuthStore } from '../stores/authStore';
import { orderRepository } from '../repositories/OrderRepository';
import { shippingRepository, type Location } from '../repositories/ShippingRepository';
import { useRoute, useRouter } from 'vue-router';
import { apiClient } from '../core/api/axiosclient';
import { useToast } from '../composables/useToast';
import { resolveImageUrl } from '../core/utils/image';
import { useWishlistStore } from '../stores/wishlistStore';
import { affiliateRepository, type AffiliateProfile, type AffiliateSummary, type AffiliateCommission } from '../repositories/AffiliateRepository';

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
const currentSection = computed(() => {
  switch (route.name) {
    case 'Addresses':
      return 'addresses';
    case 'Orders':
      return 'orders';
    case 'Wishlist':
      return 'wishlist';
    case 'Affiliate':
      return 'affiliate';
    default:
      return 'profile';
  }
});

// Order filter tab
const orderStatusFilter = ref<string>('all');
const orderStatusTabs = [
  { label: 'Semua', value: 'all' },
  { label: 'Menunggu Bayar', value: 'WaitingPayment' },
  { label: 'Dikemas', value: 'Processing' },
  { label: 'Dikirim', value: 'Shipped' },
  { label: 'Selesai', value: 'Completed' },
  { label: 'Dibatalkan', value: 'Cancelled' },
];

const filteredOrders = computed(() => {
  if (orderStatusFilter.value === 'all') return orders.value;
  return orders.value.filter((o: any) => o.status === orderStatusFilter.value);
});

// Affiliate state
const affiliateProfile = ref<AffiliateProfile | null>(null);
const affiliateSummary = ref<AffiliateSummary | null>(null);
const affiliateCommissions = ref<AffiliateCommission[]>([]);
const isLoadingAffiliate = ref(false);
const isApplyingAffiliate = ref(false);
const isRequestingPayout = ref(false);
const payoutAmount = ref<number>(0);

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
    loyaltyHistory.value = response.data.data;
  } catch (error) {
    console.error('Failed to fetch loyalty history', error);
  } finally {
    isLoadingHistory.value = false;
  }
};

onMounted(async () => {
  if (!authStore.isAuthenticated) {
    router.push('/login');
    return;
  }
  
  isLoadingOrders.value = true;
  try {
    await authStore.fetchUser();
    await loadOrders();
    if (authStore.isAuthenticated) {
      await wishlistStore.fetchWishlist();
      await fetchLoyaltyHistory();
      fetchAffiliateData();
    }
  } catch (error) {
    console.error('Failed to fetch profile data', error);
  } finally {
    isLoadingOrders.value = false;
  }
});

const handleLogout = () => {
  authStore.logout();
  router.push('/');
};


const fetchAffiliateData = async () => {
  if (!authStore.isAuthenticated) return;
  try {
    isLoadingAffiliate.value = true;
    const data = await affiliateRepository.getDashboard();
    affiliateProfile.value = data.profile;
    affiliateSummary.value = data.summary;
    if (data.profile) {
      const commData = await affiliateRepository.getCommissions();
      affiliateCommissions.value = commData.data;
    }
  } catch (error) {
    console.warn("Failed to fetch affiliate data", error);
  } finally {
    isLoadingAffiliate.value = false;
  }
};

const applyAffiliate = async () => {
  try {
    isApplyingAffiliate.value = true;
    const profile = await affiliateRepository.apply();
    affiliateProfile.value = profile;
    showToast("Pendaftaran affiliator berhasil! Menunggu persetujuan admin.", "success");
  } catch (err: any) {
    showToast(err.response?.data?.message || "Gagal mendaftar affiliator.", "error");
  } finally {
    isApplyingAffiliate.value = false;
  }
};

const requestPayout = async () => {
  if (!payoutAmount.value || payoutAmount.value <= 0) {
    showToast("Masukkan jumlah pencairan yang valid.", "error");
    return;
  }
  try {
    isRequestingPayout.value = true;
    await affiliateRepository.requestPayout(payoutAmount.value);
    showToast("Permintaan pencairan berhasil dikirim!", "success");
    payoutAmount.value = 0;
    await fetchAffiliateData();
  } catch (err: any) {
    showToast(err.response?.data?.message || "Gagal request pencairan.", "error");
  } finally {
    isRequestingPayout.value = false;
  }
};

const loadMoreOrders = async () => {
  if (!canLoadMoreOrders.value || isLoadingMoreOrders.value) return;

  isLoadingMoreOrders.value = true;
  try {
    await loadOrders(currentOrderPage.value + 1, true);
  } catch (error) {
    console.error('Failed to load more orders', error);
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
      console.error('Failed to load provinces', error);
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
      console.error('Failed to load cities', e);
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
      console.error('Failed to load districts', e);
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
    console.log('Saving address payload:', addressForm.value);
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
    console.error('Failed to save address', error);
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
    console.error('Failed to delete address', error);
  }
};
</script>

<template>
  <!-- Mini Hero with gradient bleed -->
  <div class="relative w-full" style="margin-bottom: -60px;">
    <div class="relative overflow-hidden" style="height: 280px;">
      <img src="/gambar/hero-bg.jpeg" alt="" class="absolute inset-0 w-full h-full object-cover object-center" style="transform: scale(1.08); object-position: center 40%;" />
      <div class="absolute inset-0" style="background: linear-gradient(135deg, rgba(10,8,5,0.65) 0%, rgba(30,20,10,0.45) 100%);"></div>
      <div class="absolute bottom-0 left-0 right-0" style="height: 100px; background: linear-gradient(to bottom, transparent 0%, #F5F2EE 100%);"></div>
      <div class="absolute" style="bottom: 100px; left: 0; right: 0; height: 1px; background: linear-gradient(90deg, transparent, rgba(193,154,81,0.6), transparent);"></div>
      <div class="relative z-10 h-full max-w-[1000px] mx-auto px-6 flex flex-col justify-between" :style="{ paddingTop: 'calc(var(--header-height, 96px) + 16px)', paddingBottom: '56px' }">
        <!-- Breadcrumb + Back -->
        <div>
          <nav class="flex items-center gap-2 text-xs font-medium mb-2" style="color: rgba(255,255,255,0.55);">
            <router-link to="/" class="hover:text-white transition-colors">Beranda</router-link>
            <span class="material-symbols-outlined text-sm">chevron_right</span>
            <span class="text-white">Akun Saya</span>
          </nav>
          <router-link to="/" class="flex items-center gap-2 text-sm font-bold group w-fit transition-all" style="color: rgba(193,154,81,0.9);">
            <span class="material-symbols-outlined text-lg group-hover:-translate-x-1 transition-transform">arrow_back</span>
            Kembali ke Beranda
          </router-link>
        </div>
        <!-- Page Title -->
        <h1 class="text-4xl font-black tracking-tight text-white" style="font-family: 'Outfit', sans-serif;">Akun Saya</h1>
      </div>
    </div>
  </div>

  <main class="max-w-[1000px] mx-auto w-full px-6 pb-20 flex-grow" style="padding-top: calc(var(--header-height, 96px) + 40px);">
    <div class="flex flex-col md:flex-row gap-10">

      <aside class="w-full md:w-56 shrink-0">
        <nav class="flex flex-col gap-1 rounded-none overflow-hidden border p-2" style="background: white; border-color: rgba(193,154,81,0.15); box-shadow: 0 2px 12px rgba(0,0,0,0.05);">
          <button
            @click="router.push('/profile')"
            class="flex items-center gap-3 text-left px-4 py-3 rounded-none text-sm font-bold transition-all"
            :style="currentSection === 'profile'
              ? 'background: linear-gradient(135deg, #1a1209, #3d2c0e); color: white;'
              : 'color: #5a5248; background: transparent;'"
          >
            <span class="material-symbols-outlined text-base">person</span>
            Profil Saya
          </button>
          <button
            @click="router.push('/addresses')"
            class="flex items-center gap-3 text-left px-4 py-3 rounded-none text-sm font-bold transition-all"
            :style="currentSection === 'addresses'
              ? 'background: linear-gradient(135deg, #1a1209, #3d2c0e); color: white;'
              : 'color: #5a5248; background: transparent;'"
          >
            <span class="material-symbols-outlined text-base">location_on</span>
            Alamat Saya
          </button>
          <button
            @click="router.push('/orders')"
            class="flex items-center gap-3 text-left px-4 py-3 rounded-none text-sm font-bold transition-all"
            :style="currentSection === 'orders'
              ? 'background: linear-gradient(135deg, #1a1209, #3d2c0e); color: white;'
              : 'color: #5a5248; background: transparent;'"
          >
            <span class="material-symbols-outlined text-base">receipt_long</span>
            Riwayat Pesanan
          </button>
          <button
            @click="router.push('/wishlist')"
            class="flex items-center gap-3 text-left px-4 py-3 rounded-none text-sm font-bold transition-all"
            :style="currentSection === 'wishlist'
              ? 'background: linear-gradient(135deg, #1a1209, #3d2c0e); color: white;'
              : 'color: #5a5248; background: transparent;'"
          >
            <span class="material-symbols-outlined text-base">favorite</span>
            Wishlist
          </button>
          <button
            @click="router.push('/affiliate')"
            class="flex items-center gap-3 text-left px-4 py-3 rounded-none text-sm font-bold transition-all"
            :style="currentSection === 'affiliate'
              ? 'background: linear-gradient(135deg, #1a1209, #3d2c0e); color: white;'
              : 'color: #5a5248; background: transparent;'"
          >
            <span class="material-symbols-outlined text-base">group</span>
            Afiliasi &amp; Komisi
          </button>
          <div class="h-px my-1" style="background: rgba(193,154,81,0.15);"></div>
          <button
            @click="handleLogout"
            class="flex items-center gap-3 text-left px-4 py-3 rounded-none text-sm font-bold transition-all"
            style="color: #dc2626;"
          >
            <span class="material-symbols-outlined text-base">logout</span>
            Keluar
          </button>
        </nav>
      </aside>

      <div class="flex-grow">
        
        <div v-if="currentSection === 'profile'" class="bg-surface-container-low p-8 rounded-none border border-outline-variant/15">
          <div class="flex items-center justify-between mb-8">
            <h2 class="font-headline text-2xl text-primary">Personal Information</h2>
            <div class="bg-secondary-fixed/30 text-secondary px-4 py-1.5 rounded-none text-sm font-semibold flex items-center gap-1">
              <span class="material-symbols-outlined text-sm">stars</span>
              {{ authStore.user?.loyalty_points || 0 }} Loyalty Points
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
              <div class="h-10 bg-surface-container-highest rounded-none w-full"></div>
              <div class="h-10 bg-surface-container-highest rounded-none w-full"></div>
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
                      <span :class="log.type === 'earn' ? 'text-green-600 bg-green-50' : 'text-red-600 bg-red-50'" class="px-2 py-0.5 rounded text-xs font-medium uppercase">
                        {{ log.type === 'earn' ? 'Dapat' : 'Pakai' }}
                      </span>
                    </td>
                    <td class="py-3 text-right font-medium" :class="log.type === 'earn' ? 'text-green-600' : 'text-red-600'">
                      {{ log.type === 'earn' ? '+' : '-' }}{{ log.points }}
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
             <button @click="openAddressModal" class="bg-primary text-white px-4 py-2 rounded-none text-sm font-medium hover:bg-primary-container transition-colors">Add New Address</button>
           </div>
           
           <div class="grid grid-cols-1 gap-4">
              <div v-if="authStore.user?.addresses?.length > 0">
                 <div v-for="addr in authStore.user.addresses" :key="addr.id" class="p-6 bg-surface-container-low border border-outline-variant/15 rounded-none">
                    <div class="flex justify-between items-start">
                       <div>
                          <p class="font-bold text-primary">{{ addr.recipient_name }} <span v-if="addr.is_default" class="ml-2 text-[10px] bg-secondary-fixed/30 text-secondary px-2 py-0.5 rounded-none uppercase">Default</span></p>
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
              <div v-else class="text-center py-12 bg-surface-container-low rounded-none">
                 <p class="text-on-surface-variant">No addresses saved yet.</p>
              </div>
           </div>
        </div>

        <div v-if="currentSection === 'orders'">
          <h2 class="font-black text-2xl mb-4" style="color: #1a1209; font-family: Outfit, sans-serif;">Riwayat Pesanan</h2>
          <!-- Order Status Filter Tabs -->
          <div class="flex flex-wrap gap-2 mb-6">
            <button
              v-for="tab in orderStatusTabs"
              :key="tab.value"
              @click="orderStatusFilter = tab.value"
              class="px-3 py-1.5 text-xs font-black uppercase tracking-wider rounded-none transition-all"
              :style="orderStatusFilter === tab.value
                ? 'background: linear-gradient(135deg, #1a1209, #3d2c0e); color: white;'
                : 'background: rgba(193,154,81,0.08); color: #7a6230; border: 1px solid rgba(193,154,81,0.2);'"
            >{{ tab.label }}</button>
          </div>

          
          <div v-if="isLoadingOrders" class="animate-pulse flex flex-col gap-4">
            <div class="h-24 bg-surface-container-low rounded-none w-full"></div>
            <div class="h-24 bg-surface-container-low rounded-none w-full"></div>
          </div>
          
          <div v-else-if="filteredOrders.length === 0" class="text-center py-12 bg-surface-container-low rounded-none">
            <p class="text-on-surface-variant">You haven't placed any orders yet.</p>
          </div>
          
          <div v-else class="flex flex-col gap-4">
            <div v-for="order in filteredOrders" :key="order.id" class="bg-surface-container-low p-6 rounded-none border border-outline-variant/15 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 hover:shadow-md transition-shadow">
              <div class="flex items-center gap-4">
                <!-- Product Thumbnail Preview -->
                <div v-if="order.items && order.items.length > 0" class="w-16 h-16 shrink-0 bg-stone-100 border p-1 rounded-none flex items-center justify-center overflow-hidden">
                  <img 
                    :src="resolveImageUrl(order.items[0].product, order.items[0].product?.name)" 
                    class="w-full h-full object-contain mix-blend-multiply"
                  />
                </div>
                <div v-else class="w-16 h-16 shrink-0 bg-stone-50 border p-1 rounded-none flex items-center justify-center">
                  <span class="material-symbols-outlined text-stone-300">shopping_bag</span>
                </div>

                <div>
                  <p class="text-xs text-on-surface-variant uppercase tracking-wider mb-1">Order #{{ order.order_number }}</p>
                  <p class="font-medium text-primary">Rp {{ (Number(order.total_price) || 0).toLocaleString('id-ID') }}</p>
                  <p class="text-sm text-on-surface-variant mt-1">{{ new Date(order.created_at).toLocaleString('id-ID', { year: 'numeric', month: 'long', day: 'numeric', hour: '2-digit', minute: '2-digit' }) }}</p>
                </div>
              </div>
              
              <div class="flex items-center gap-3 flex-wrap justify-end">
                <span :class="['px-3 py-1 rounded text-xs font-medium uppercase', 
                  order.status?.toUpperCase() === 'PAID' ? 'bg-green-100 text-green-800' : 
                  order.status?.toUpperCase() === 'SHIPPED' ? 'bg-blue-100 text-blue-800' : 
                  order.status?.toUpperCase() === 'DELIVERED' ? 'bg-purple-100 text-purple-800' :
                  'bg-secondary-fixed/30 text-secondary']">
                  {{ order.status }}
                </span>
                <button
                  v-if="order.status?.toUpperCase() === 'SHIPPED'"
                  @click="confirmDelivery(order.id)"
                  :disabled="confirmingOrderId === order.id"
                  class="px-4 py-2 rounded-none text-xs font-bold uppercase tracking-wide bg-primary text-white hover:bg-primary-container transition-colors disabled:opacity-50"
                >
                  {{ confirmingOrderId === order.id ? 'Menyimpan...' : 'Konfirmasi Diterima' }}
                </button>
                <button @click="router.push(`/orders/${order.id}`)" class="text-sm font-medium text-primary hover:text-secondary transition-colors underline underline-offset-4">
                  View Details
                </button>
              </div>
            </div>

            <button
              v-if="canLoadMoreOrders"
              @click="loadMoreOrders"
              :disabled="isLoadingMoreOrders"
              class="self-center mt-2 px-6 py-3 rounded-none text-sm font-medium bg-primary text-white hover:bg-primary-container transition-colors disabled:opacity-50"
            >
              {{ isLoadingMoreOrders ? 'Loading...' : 'Load More' }}
            </button>
          </div>
        </div>

        <div v-if="currentSection === 'wishlist'">
          <h2 class="font-headline text-2xl text-primary mb-6">Wishlist</h2>

          <div v-if="wishlistStore.items.length === 0" class="text-center py-12 bg-surface-container-low rounded-none">
            <p class="text-on-surface-variant">Belum ada produk di wishlist Anda.</p>
          </div>

          <div v-else class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div
              v-for="product in wishlistStore.items"
              :key="product.id"
              class="bg-surface-container-low p-5 rounded-none border border-outline-variant/15 flex gap-4 items-start"
            >
              <div class="w-20 h-20 shrink-0 bg-stone-100 border p-2 rounded-none flex items-center justify-center overflow-hidden">
                <img
                  :src="resolveImageUrl(product, product.name)"
                  class="w-full h-full object-contain mix-blend-multiply"
                />
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

        <!-- Affiliate & Commission Section -->
        <div v-if="currentSection === 'affiliate'" class="space-y-6">
          <h2 class="font-black text-2xl" style="color: #1a1209; font-family: Outfit, sans-serif;">Afiliasi &amp; Komisi</h2>

          <div v-if="isLoadingAffiliate" class="flex items-center gap-2 py-8">
            <span class="material-symbols-outlined animate-spin" style="color: #c19a51;">sync</span>
            <span class="text-sm text-stone-500">Memuat data afiliasi...</span>
          </div>

          <!-- Belum mendaftar -->
          <div v-else-if="!affiliateProfile" class="bg-white p-8 border border-stone-200 text-center">
            <span class="material-symbols-outlined text-5xl block mb-4" style="color: #c19a51;">groups</span>
            <h3 class="font-black text-xl mb-2" style="color: #1a1209; font-family: Outfit, sans-serif;">Program Afiliasi Optik Medio</h3>
            <p class="text-sm text-stone-500 mb-6 max-w-md mx-auto">Daftarkan diri Anda sebagai affiliator dan dapatkan komisi dari setiap penjualan melalui link referral Anda.</p>
            <button
              @click="applyAffiliate"
              :disabled="isApplyingAffiliate"
              class="px-8 py-3 font-black text-sm text-white uppercase tracking-wider transition-all disabled:opacity-50"
              style="background: linear-gradient(135deg, #1a1209, #3d2c0e);"
            >
              {{ isApplyingAffiliate ? 'Memproses...' : 'Daftar Afiliator' }}
            </button>
          </div>

          <!-- Profil affiliator -->
          <div v-else class="space-y-6">
            <!-- Status Card -->
            <div class="bg-white p-6 border border-stone-200">
              <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-6">
                <div>
                  <p class="text-xs font-black uppercase tracking-widest mb-1" style="color: #c19a51;">Kode Afiliasi Anda</p>
                  <p class="font-black text-2xl" style="color: #1a1209; font-family: Outfit, sans-serif;">{{ affiliateProfile.affiliate_code }}</p>
                </div>
                <span
                  class="px-4 py-1.5 text-xs font-black uppercase tracking-widest self-start"
                  :style="affiliateProfile.status === 'approved'
                    ? 'background: rgba(22,163,74,0.1); color: #16a34a;'
                    : affiliateProfile.status === 'pending'
                    ? 'background: rgba(245,158,11,0.1); color: #d97706;'
                    : 'background: rgba(220,38,38,0.1); color: #dc2626;'"
                >{{ affiliateProfile.status }}</span>
              </div>
              <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                <div class="text-center p-3 border border-stone-100">
                  <p class="text-2xl font-black" style="color: #1a1209;">{{ affiliateSummary?.referrals_count || 0 }}</p>
                  <p class="text-[10px] uppercase tracking-widest text-stone-500 mt-1">Referral</p>
                </div>
                <div class="text-center p-3 border border-stone-100">
                  <p class="text-2xl font-black" style="color: #16a34a;">{{ affiliateSummary?.total_success || 0 }}</p>
                  <p class="text-[10px] uppercase tracking-widest text-stone-500 mt-1">Berhasil</p>
                </div>
                <div class="text-center p-3 border border-stone-100">
                  <p class="text-2xl font-black" style="color: #d97706;">{{ affiliateSummary?.total_pending || 0 }}</p>
                  <p class="text-[10px] uppercase tracking-widest text-stone-500 mt-1">Pending</p>
                </div>
                <div class="text-center p-3 border border-stone-100">
                  <p class="text-lg font-black" style="color: #c19a51;">{{ affiliateProfile.commission_rate_percentage }}%</p>
                  <p class="text-[10px] uppercase tracking-widest text-stone-500 mt-1">Komisi</p>
                </div>
              </div>
            </div>

            <!-- Request Pencairan -->
            <div v-if="affiliateProfile.status === 'approved'" class="bg-white p-6 border border-stone-200">
              <h3 class="font-black text-base mb-4" style="color: #1a1209;">Request Pencairan Komisi</h3>
              <div class="flex gap-3">
                <input
                  v-model.number="payoutAmount"
                  type="number"
                  min="1"
                  placeholder="Jumlah pencairan (Rp)"
                  class="flex-grow border px-4 py-3 text-sm focus:outline-none"
                  style="border-color: #e5e0d8; color: #1a1209;"
                />
                <button
                  @click="requestPayout"
                  :disabled="isRequestingPayout"
                  class="px-6 py-3 text-sm font-black text-white uppercase tracking-wider disabled:opacity-50 shrink-0"
                  style="background: linear-gradient(135deg, #1a1209, #3d2c0e);"
                >{{ isRequestingPayout ? 'Memproses...' : 'Request' }}</button>
              </div>
            </div>

            <!-- Commission History -->
            <div class="bg-white p-6 border border-stone-200">
              <h3 class="font-black text-base mb-4" style="color: #1a1209;">Histori Pencairan</h3>
              <div v-if="affiliateCommissions.length === 0" class="text-center py-8 text-stone-400 text-sm">Belum ada histori pencairan.</div>
              <div v-else class="flex flex-col gap-3">
                <div v-for="comm in affiliateCommissions" :key="comm.id" class="flex items-center justify-between p-4 border border-stone-100">
                  <div>
                    <p class="font-bold text-sm" style="color: #1a1209;">{{ comm.request_no }}</p>
                    <p class="text-xs text-stone-500">{{ comm.requested_at ? new Date(comm.requested_at).toLocaleDateString('id-ID') : '-' }}</p>
                  </div>
                  <div class="text-right">
                    <p class="font-black text-sm" style="color: #1a1209;">Rp {{ Number(comm.requested_amount).toLocaleString('id-ID') }}</p>
                    <span class="text-[10px] font-black uppercase px-2 py-0.5"
                      :style="comm.status === 'success' ? 'background: rgba(22,163,74,0.1); color: #16a34a;' : comm.status === 'pending' ? 'background: rgba(245,158,11,0.1); color: #d97706;' : 'background: rgba(220,38,38,0.1); color: #dc2626;'"
                    >{{ comm.status }}</span>
                  </div>
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
    <div v-if="showAddressModal" class="fixed inset-0 z-[9999] flex items-center justify-center bg-black/60 backdrop-blur-sm px-6 py-10">
      <div class="bg-surface-container-low w-full max-w-2xl rounded-none p-8 max-h-full overflow-y-auto shadow-2xl border border-outline-variant/20">
        <div class="flex justify-between items-center mb-8">
          <h3 class="text-2xl font-headline text-primary">Add New Address</h3>
          <button @click="showAddressModal = false" class="text-on-surface-variant hover:text-primary transition-colors p-2 hover:bg-surface-container-highest rounded-none flex items-center justify-center">
            <span class="material-symbols-outlined">close</span>
          </button>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
          <div class="md:col-span-2">
            <label class="block text-sm font-semibold text-on-surface-variant mb-2">Recipient Name</label>
            <input v-model="addressForm.recipient_name" type="text" class="w-full bg-surface-container-highest p-4 rounded-none border-0 ring-1 ring-inset ring-outline-variant/30 focus:ring-2 focus:ring-secondary transition-all" placeholder="e.g. John Doe" />
          </div>
          <div>
            <label class="block text-sm font-semibold text-on-surface-variant mb-2">Phone Number</label>
            <input v-model="addressForm.phone" type="text" class="w-full bg-surface-container-highest p-4 rounded-none border-0 ring-1 ring-inset ring-outline-variant/30 focus:ring-2 focus:ring-secondary transition-all" placeholder="e.g. 08123456789" />
          </div>
          <div>
            <label class="block text-sm font-semibold text-on-surface-variant mb-2">Province</label>
            <select v-model="addressForm.province_id" class="w-full bg-surface-container-highest p-4 rounded-none border-0 ring-1 ring-inset ring-outline-variant/30 focus:ring-2 focus:ring-secondary transition-all">
              <option value="">{{ isProvLoading ? 'Loading...' : 'Select Province' }}</option>
              <option v-for="prov in provinces" :key="prov.id || (prov as any).province_id" :value="prov.id || (prov as any).province_id">{{ prov.name || (prov as any).province_name || (prov as any).province }}</option>
            </select>
          </div>
          <div>
            <label class="block text-sm font-semibold text-on-surface-variant mb-2">City</label>
            <select v-model="addressForm.city_id" :disabled="!addressForm.province_id" class="w-full bg-surface-container-highest p-4 rounded-none border-0 ring-1 ring-inset ring-outline-variant/30 focus:ring-2 focus:ring-secondary transition-all">
              <option value="">{{ isCityLoading ? 'Loading...' : 'Select City' }}</option>
              <option v-for="city in cities" :key="city.id" :value="city.id">{{ city.name }}</option>
            </select>
          </div>
          <div>
            <label class="block text-sm font-semibold text-on-surface-variant mb-2">District</label>
            <select v-model="addressForm.district_id" :disabled="!addressForm.city_id" class="w-full bg-surface-container-highest p-4 rounded-none border-0 ring-1 ring-inset ring-outline-variant/30 focus:ring-2 focus:ring-secondary transition-all">
              <option value="">{{ isDistLoading ? 'Loading...' : 'Select District' }}</option>
              <option v-for="dist in districts" :key="dist.id" :value="dist.id">{{ dist.name }}</option>
            </select>
          </div>
          <div class="md:col-span-2">
            <label class="block text-sm font-semibold text-on-surface-variant mb-2">Full Address</label>
            <textarea v-model="addressForm.address" rows="3" class="w-full bg-surface-container-highest p-4 rounded-none border-0 ring-1 ring-inset ring-outline-variant/30 focus:ring-2 focus:ring-secondary transition-all" placeholder="Street name, house number, etc."></textarea>
          </div>
          <div>
            <label class="block text-sm font-semibold text-on-surface-variant mb-2">Postal Code</label>
            <input v-model="addressForm.postal_code" type="text" class="w-full bg-surface-container-highest p-4 rounded-none border-0 ring-1 ring-inset ring-outline-variant/30 focus:ring-2 focus:ring-secondary transition-all" />
          </div>
          <div class="flex items-center gap-3 py-2">
            <input v-model="addressForm.is_default" type="checkbox" id="is_default" class="w-5 h-5 rounded border-outline-variant/30 text-secondary focus:ring-secondary" />
            <label for="is_default" class="text-sm font-medium text-on-surface cursor-pointer select-none">Set as Default Address</label>
          </div>
        </div>

        <div class="mt-10 flex gap-4 sticky bottom-0 bg-surface-container-low pt-4 border-t border-outline-variant/10">
          <button @click="showAddressModal = false" class="flex-grow py-4 rounded-none font-bold text-primary hover:bg-surface-container-highest transition-all">Cancel</button>
          <button @click="saveAddress" :disabled="isSavingAddress" class="flex-grow py-4 rounded-none font-bold bg-primary text-on-primary hover:bg-primary-container transition-all shadow-lg shadow-primary/20 disabled:opacity-50">
            {{ isSavingAddress ? 'Saving...' : 'Save Address' }}
          </button>
        </div>
      </div>
    </div>
  </Teleport>
</template>
