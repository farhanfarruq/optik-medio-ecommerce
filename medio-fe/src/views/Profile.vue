<script setup lang="ts">
import { ref, onMounted, watch } from 'vue';
import { useAuthStore } from '../stores/authStore';
import { orderRepository } from '../repositories/OrderRepository';
import { shippingRepository, type Location } from '../repositories/ShippingRepository';
import { useRouter } from 'vue-router';
import { apiClient } from '../core/api/axiosclient';
import { useToast } from '../composables/useToast';

const { showToast } = useToast();

const authStore = useAuthStore();
const router = useRouter();
const activeTab = ref('profile');
const orders = ref<any[]>([]);
const isLoadingOrders = ref(false);

onMounted(async () => {
  if (!authStore.isAuthenticated) {
    router.push('/login');
    return;
  }
  
  isLoadingOrders.value = true;
  try {
    await authStore.fetchUser();
    orders.value = await orderRepository.getUserOrders();
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
      <div class="relative z-10 h-full max-w-[1000px] mx-auto px-6 flex flex-col justify-end pb-24 pt-36">
        <router-link to="/" class="flex items-center gap-2 text-sm font-bold mb-3 group w-fit transition-all" style="color: rgba(193,154,81,0.9);">
          <span class="material-symbols-outlined text-lg group-hover:-translate-x-1 transition-transform">arrow_back</span>
          Kembali ke Beranda
        </router-link>
        <h1 class="text-4xl font-black tracking-tight text-white" style="font-family: 'Outfit', sans-serif;">
          Akun Saya
        </h1>
      </div>
    </div>
  </div>

  <main class="max-w-[1000px] mx-auto w-full px-6 pb-20 flex-grow" style="padding-top: 160px;">
    <div class="flex flex-col md:flex-row gap-10">

      <aside class="w-full md:w-56 shrink-0">
        <nav class="flex flex-col gap-1 rounded-none overflow-hidden border p-2" style="background: white; border-color: rgba(193,154,81,0.15); box-shadow: 0 2px 12px rgba(0,0,0,0.05);">
          <button
            @click="activeTab = 'profile'"
            class="flex items-center gap-3 text-left px-4 py-3 rounded-none text-sm font-bold transition-all"
            :style="activeTab === 'profile'
              ? 'background: linear-gradient(135deg, #1a1209, #3d2c0e); color: white;'
              : 'color: #5a5248; background: transparent;'"
          >
            <span class="material-symbols-outlined text-base">person</span>
            Profil Saya
          </button>
          <button
            @click="activeTab = 'addresses'"
            class="flex items-center gap-3 text-left px-4 py-3 rounded-none text-sm font-bold transition-all"
            :style="activeTab === 'addresses'
              ? 'background: linear-gradient(135deg, #1a1209, #3d2c0e); color: white;'
              : 'color: #5a5248; background: transparent;'"
          >
            <span class="material-symbols-outlined text-base">location_on</span>
            Alamat Saya
          </button>
          <button
            @click="activeTab = 'orders'"
            class="flex items-center gap-3 text-left px-4 py-3 rounded-none text-sm font-bold transition-all"
            :style="activeTab === 'orders'
              ? 'background: linear-gradient(135deg, #1a1209, #3d2c0e); color: white;'
              : 'color: #5a5248; background: transparent;'"
          >
            <span class="material-symbols-outlined text-base">receipt_long</span>
            Riwayat Pesanan
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
        
        <div v-if="activeTab === 'profile'" class="bg-surface-container-low p-8 rounded-none border border-outline-variant/15">
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
        </div>

        <div v-if="activeTab === 'addresses'">
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

        <div v-if="activeTab === 'orders'">
          <h2 class="font-headline text-2xl text-primary mb-6">Order History</h2>
          
          <div v-if="isLoadingOrders" class="animate-pulse flex flex-col gap-4">
            <div class="h-24 bg-surface-container-low rounded-none w-full"></div>
            <div class="h-24 bg-surface-container-low rounded-none w-full"></div>
          </div>
          
          <div v-else-if="orders.length === 0" class="text-center py-12 bg-surface-container-low rounded-none">
            <p class="text-on-surface-variant">You haven't placed any orders yet.</p>
          </div>
          
          <div v-else class="flex flex-col gap-4">
            <div v-for="order in orders" :key="order.id" class="bg-surface-container-low p-6 rounded-none border border-outline-variant/15 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 hover:shadow-md transition-shadow">
              <div>
                <p class="text-xs text-on-surface-variant uppercase tracking-wider mb-1">Order #{{ order.order_number }}</p>
                <p class="font-medium text-primary">Rp {{ (Number(order.total_price) || 0).toLocaleString('id-ID') }}</p>
                <p class="text-sm text-on-surface-variant mt-1">{{ new Date(order.created_at).toLocaleDateString('id-ID', { year: 'numeric', month: 'long', day: 'numeric' }) }}</p>
              </div>
              
              <div class="flex items-center gap-4">
                <span :class="['px-3 py-1 rounded text-xs font-medium uppercase', 
                  order.status?.toUpperCase() === 'PAID' ? 'bg-green-100 text-green-800' : 
                  order.status?.toUpperCase() === 'SHIPPED' ? 'bg-blue-100 text-blue-800' : 
                  order.status?.toUpperCase() === 'DELIVERED' ? 'bg-purple-100 text-purple-800' :
                  'bg-secondary-fixed/30 text-secondary']">
                  {{ order.status }}
                </span>
                <button @click="router.push(`/orders/${order.id}`)" class="text-sm font-medium text-primary hover:text-secondary transition-colors underline underline-offset-4">
                  View Details
                </button>
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