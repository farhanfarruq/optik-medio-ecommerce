<script setup lang="ts">
import { ref, onMounted, computed, watch } from 'vue';
import { useCartStore } from '../../stores/cartStore';
import { shippingRepository, type Location } from '../../repositories/ShippingRepository';
import { orderRepository } from '../../repositories/OrderRepository';
import { useRouter } from 'vue-router';
import { apiClient } from '../../core/api/axiosclient';
import { useToast } from '../../composables/useToast';

const { showToast } = useToast();

const cartStore = useCartStore();
const router = useRouter();

// State Form Pengiriman
const form = ref({
  id: null as number | null,
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
  courier: 'jne',
  selected_service: ''
});

// State Data RajaOngkir
const provinces = ref<Location[]>([]);
const cities = ref<Location[]>([]);
const districts = ref<Location[]>([]);
const isCalculating = ref(false);
const isSubmitting = ref(false);

const shippingResults = ref<any[]>([]);
const shippingError = ref('');
const checkoutError = ref('');

const isProvLoading = ref(false);
const isCityLoading = ref(false);
const isDistLoading = ref(false);
const isAutoFilling = ref(false);
const userAddresses = ref<any[]>([]);
const showAddressModal = ref(false);

// State Diskon
const couponCode = ref('');
const appliedDiscount = ref<any>(null);
const isValidatingCoupon = ref(false);

const applyCoupon = async () => {
  if (!couponCode.value) return;
  
  isValidatingCoupon.value = true;
  try {
    const response = await apiClient.post('/discounts/validate', { code: couponCode.value });
    appliedDiscount.value = response.data.discount;
    showToast('Kupon berhasil diterapkan!', 'success');
  } catch (error: any) {
    appliedDiscount.value = null;
    const msg = error.response?.data?.message || 'Gagal menerapkan kupon.';
    showToast(msg, 'error');
  } finally {
    isValidatingCoupon.value = false;
  }
};

const removeCoupon = () => {
  appliedDiscount.value = null;
  couponCode.value = '';
};

const discountAmount = computed(() => {
  if (!appliedDiscount.value) return 0;
  const subtotal = cartStore.cartTotal;
  if (appliedDiscount.value.type === 'percentage') {
    return (subtotal * appliedDiscount.value.value) / 100;
  }
  // Cap flat discount at subtotal to prevent negative totals
  return Math.min(subtotal, Number(appliedDiscount.value.value));
});

const selectAddress = async (addr: any) => {
  try {
    isAutoFilling.value = true;
    showAddressModal.value = false;
    
    form.value.id = addr.id;
    form.value.recipient_name = addr.recipient_name;
    form.value.phone = addr.phone;
    form.value.address = addr.address;
    form.value.postal_code = addr.postal_code;
    
    // Sequence to load dependent fields
    form.value.province_id = String(addr.province_id);
    
    const citiesData = await shippingRepository.getCities(form.value.province_id);
    cities.value = citiesData.map((c: any) => ({
      id: String(c.id || c.city_id || ''),
      name: c.name || c.city_name || `${c.type} ${c.city}`
    }));
    form.value.city_id = String(addr.city_id);

    const districtsData = await shippingRepository.getDistricts(form.value.city_id);
    districts.value = districtsData.map((d: any) => ({
      id: String(d.id || d.subdistrict_id || d.district_id || ''),
      name: d.name || d.subdistrict_name || d.district_name || d.district,
      postal_code: String(d.zip_code || d.postal_code || '')
    }));
    form.value.district_id = String(addr.district_id);
    
  } catch (error) {
    console.error('Failed to select address', error);
  } finally {
    isAutoFilling.value = false;
  }
};

// Fetch Data Awal & Pre-fill
onMounted(async () => {
  try {
    isProvLoading.value = true;
    // Load provinces first to ensure names can be resolved
    provinces.value = await shippingRepository.getProvinces();

    const userResponse = await apiClient.get('/auth/me');
    const user = userResponse.data;
    if (user) {
        form.value.recipient_name = user.name;
        userAddresses.value = user.addresses || [];
        
        // Find default address
        const defaultAddr = userAddresses.value.find((a: any) => a.is_default) || userAddresses.value[0];
        if (defaultAddr) {
          console.log('Auto-filling with default address:', defaultAddr);
          await selectAddress(defaultAddr);
        }
    }
  } catch (error) {
    console.error('Failed to initialize checkout', error);
  } finally {
    isProvLoading.value = false;
  }
});

// Watchers
watch(() => form.value.province_id, async (newVal) => {
  if (newVal) {
    console.log('Fetching cities for province_id:', newVal);
    const selectedProv = provinces.value.find(p => (p.id || (p as any).province_id) == newVal) as any;
    form.value.province = selectedProv ? (selectedProv.name || selectedProv.province_name || selectedProv.province) : '';

    if (isAutoFilling.value) return;

    form.value.city_id = '';
    form.value.district_id = '';
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

watch(() => form.value.city_id, async (newVal) => {
  if (newVal) {
    const selectedCity = cities.value.find(c => (c.id || (c as any).city_id) == newVal) as any;
    form.value.city = selectedCity ? (selectedCity.name || selectedCity.city_name || selectedCity.city) : '';

    if (isAutoFilling.value) return;

    form.value.district_id = '';
    districts.value = [];

    try {
      isDistLoading.value = true;
      const data = await shippingRepository.getDistricts(newVal);
      districts.value = data.map((d: any) => ({
        id: String(d.id || d.subdistrict_id || d.district_id || ''),
        name: d.name || d.subdistrict_name || d.district_name || d.district,
        postal_code: String(d.zip_code || d.postal_code || '')
      }));
    } catch (e) {
      console.error('Failed to load districts', e);
    } finally {
      isDistLoading.value = false;
    }
  }
});

watch(() => form.value.district_id, async (newVal) => {
    if (newVal) {
        const selectedDist = districts.value.find(d => String(d.id) === String(newVal));
        form.value.district = selectedDist ? selectedDist.name : '';
        form.value.postal_code = (selectedDist as any)?.postal_code || form.value.postal_code;
        checkoutError.value = '';
        calculateShipping();
    }
});

// Watch for manual edits to reset the selected address ID
watch([
  () => form.value.recipient_name,
  () => form.value.phone,
  () => form.value.address,
  () => form.value.province_id,
  () => form.value.city_id,
  () => form.value.district_id,
  () => form.value.postal_code
], () => {
  if (!isAutoFilling.value) {
    form.value.id = null;
  }
});

const isAddressComplete = computed(() => {
  return !!(
    form.value.recipient_name.trim() &&
    form.value.phone.trim() &&
    form.value.address.trim() &&
    form.value.province_id &&
    form.value.province.trim() &&
    form.value.city_id &&
    form.value.city.trim() &&
    form.value.district_id &&
    form.value.district.trim() &&
    form.value.postal_code.trim()
  );
});

const calculateShipping = async () => {
  if (form.value.district_id && cartStore.items.length > 0) {
    isCalculating.value = true;
    shippingResults.value = [];
    shippingError.value = '';

    try {
      const totalWeight = cartStore.items.reduce((sum: number, item: any) => {
        const itemWeight = Number(item.weight || 0);
        const itemQty = Number(item.quantity || 0);
        return sum + (itemWeight * itemQty);
      }, 0);

      if (!Number.isFinite(totalWeight) || totalWeight <= 0) {
        shippingError.value = 'Berat produk di keranjang tidak valid, jadi ongkir belum bisa dihitung.';
        return;
      }

      const response = await shippingRepository.calculateCost(
        form.value.district_id,
        Math.round(totalWeight)
      );

      shippingResults.value = (response || []).map((item: any) => ({
        courier: String(item.courier || '').toLowerCase(),
        service: item.service,
        description: item.description,
        cost: Number(item.cost || 0),
        etd: item.etd
      }));

      if (shippingResults.value.length > 0) {
        form.value.selected_service = `${shippingResults.value[0].courier}_${shippingResults.value[0].service}`;
      } else {
        shippingError.value = 'Tidak ada layanan pengiriman yang tersedia untuk kecamatan ini.';
      }
    } catch (error: any) {
      console.error('Failed to calculate shipping', error);
      shippingError.value = error?.response?.data?.message || 'Gagal menghitung ongkir untuk tujuan ini.';
    } finally {
      isCalculating.value = false;
    }
  }
};

const selectedShipping = computed(() => {
  if (!form.value.selected_service) return null;
  return shippingResults.value.find(s => `${s.courier}_${s.service}` === form.value.selected_service);
});

const selectedShippingCost = computed(() => {
  return selectedShipping.value ? selectedShipping.value.cost : 0;
});

const grandTotal = computed(() => {
  const subtotalAfterDiscount = Math.max(0, cartStore.cartTotal - discountAmount.value);
  return subtotalAfterDiscount + selectedShippingCost.value;
});

const submitOrder = async () => {
  checkoutError.value = '';

  if (!isAddressComplete.value) {
    showToast('Lengkapi semua data alamat pengiriman terlebih dahulu.', 'error');
    return;
  }

  const selected = selectedShipping.value;
  if (!selected) {
    showToast('Pilih layanan pengiriman terlebih dahulu.', 'error');
    return;
  }

  isSubmitting.value = true;
  try {
    let shippingAddressId = form.value.id;

    if (!shippingAddressId) {
        const addressPayload = {
            recipient_name: form.value.recipient_name.trim(),
            phone: form.value.phone.trim(),
            province: form.value.province.trim(),
            province_id: String(form.value.province_id).trim(),
            city: form.value.city.trim(),
            city_id: String(form.value.city_id).trim(),
            district: form.value.district.trim(),
            district_id: String(form.value.district_id).trim(),
            postal_code: form.value.postal_code.trim(),
            address: form.value.address.trim(),
            is_default: true
        };

        const addressResponse = await apiClient.post('/addresses', addressPayload);
         shippingAddressId = addressResponse.data.id;
    }

    // Build items list — map frames first, then find their attached lenses
    const frameItems = cartStore.items.filter((item: any) => !item.parent_item_id);

    const itemsPayload = frameItems.flatMap((frame: any, frameIndex: number) => {
      const lens = cartStore.items.find((i: any) => i.parent_item_id === frame.cart_id);
      const frameEntry = {
        product_id: frame.id,
        quantity: frame.quantity || 1,
        variant: frame.variant || null,
        prescription: frame.prescription || null,
      };
      if (!lens) return [frameEntry];

      const lensEntry = {
        product_id: lens.id,
        quantity: lens.quantity || 1,
        variant: lens.variant || null,
        prescription: null,
        linked_item_index: frameIndex, // tells backend this is a child lens
      };
      return [frameEntry, lensEntry];
    });

    const payload = {
      shipping_address_id: shippingAddressId,
      courier: selected.courier,
      courier_service: selected.service,
      shipping_cost: selected.cost,
      discount_id: appliedDiscount.value?.id || null,
      items: itemsPayload,
      notes: ''
    };

    const orderResponse: any = await orderRepository.createOrder(payload);
    cartStore.clearCart();
    if (orderResponse.payment?.checkout_url) {
      window.location.href = orderResponse.payment.checkout_url;
    } else {
      showToast('Pesanan berhasil dibuat!', 'success');
      router.push(`/profile`);
    }
  } catch (error: any) {
    console.error('Order failed', error);
    const validationErrors = error.response?.data?.errors;
    if (validationErrors) {
      const firstError = Object.values(validationErrors)[0];
      const msg = Array.isArray(firstError) ? String(firstError[0]) : 'Data tidak valid.';
      checkoutError.value = msg;
      showToast(msg, 'error');
    } else {
      const msg = error.response?.data?.message || 'Terjadi kesalahan saat membuat pesanan.';
      checkoutError.value = msg;
      showToast(msg, 'error');
    }
  } finally {
    isSubmitting.value = false;
  }
};
</script>

<template>
  <div class="checkout-page">
    <!-- Mini Hero with gradient bleed -->
    <div class="relative w-full" style="margin-bottom: -80px;">
      <div class="relative overflow-hidden" style="height: 280px;">
        <img src="/gambar/hero-bg.jpeg" alt="" class="absolute inset-0 w-full h-full object-cover object-center" style="transform: scale(1.08); object-position: center 40%;" />
        <div class="absolute inset-0" style="background: linear-gradient(135deg, rgba(10,8,5,0.65) 0%, rgba(30,20,10,0.45) 100%);"></div>
        <!-- Gradient bleed -->
        <div class="absolute bottom-0 left-0 right-0" style="height: 100px; background: linear-gradient(to bottom, transparent 0%, #F5F2EE 100%);"></div>
        <div class="absolute" style="bottom: 100px; left: 0; right: 0; height: 1px; background: linear-gradient(90deg, transparent, rgba(193,154,81,0.6), transparent);"></div>

        <div class="relative z-10 h-full max-w-[1440px] mx-auto px-6 md:px-12 flex flex-col justify-end pb-24 pt-36">
          <router-link to="/" class="flex items-center gap-2 text-sm font-bold mb-3 group w-fit transition-all" style="color: rgba(193,154,81,0.9);">
            <span class="material-symbols-outlined text-lg group-hover:-translate-x-1 transition-transform">arrow_back</span>
            Kembali ke Beranda
          </router-link>
          <h1 class="text-4xl font-black tracking-tight text-white" style="font-family: 'Outfit', sans-serif;">Checkout</h1>
        </div>
      </div>
    </div>

    <main class="relative z-10 max-w-7xl mx-auto px-6 pb-24" style="padding-top: 160px;">
      <div class="flex flex-col lg:flex-row gap-12 lg:gap-16">
        <!-- Left Column: Forms -->
        <div class="w-full lg:w-3/5 xl:w-2/3 flex flex-col gap-8">
          
          <!-- Shipping Destination Section -->
          <section class="bg-white p-8 rounded-none shadow-sm border border-stone-200 group relative">
            <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-8">
              <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-none bg-stone-100 flex items-center justify-center text-stone-600">
                  <span class="material-symbols-outlined">location_on</span>
                </div>
                <div>
                  <h2 class="text-xl font-bold text-stone-900" style="font-family: 'Outfit', sans-serif;">Alamat Pengiriman</h2>
                  <p class="text-xs text-stone-500">Kirim pesanan Anda ke lokasi tujuan</p>
                </div>
              </div>
              
              <div class="flex items-center gap-2">
                <button 
                  v-if="userAddresses.length > 0" 
                  @click="showAddressModal = true" 
                  class="flex items-center gap-2 px-4 py-2 rounded-none text-sm font-bold transition-all bg-stone-100 hover:bg-stone-200 text-stone-700"
                >
                  <span class="material-symbols-outlined text-sm">list_alt</span>
                  Pilih Alamat
                </button>
                <router-link 
                  to="/profile" 
                  class="flex items-center gap-2 px-4 py-2 rounded-none text-sm font-bold transition-all bg-primary/10 hover:bg-primary/20 text-primary"
                  style="color: #c19a51;"
                >
                  <span class="material-symbols-outlined text-sm">add</span>
                  Tambah Baru
                </router-link>
              </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
               <div>
                  <label class="font-label text-sm mb-1 block text-stone-500">Nama Penerima</label>
                  <input v-model="form.recipient_name" type="text" class="w-full bg-stone-50 border border-stone-200 rounded-none p-3 focus:border-primary focus:ring-1 focus:ring-primary outline-none transition-all" placeholder="Nama Lengkap" />
               </div>
               <div>
                  <label class="font-label text-sm mb-1 block text-stone-500">No. Telepon</label>
                  <input v-model="form.phone" type="text" class="w-full bg-stone-50 border border-stone-200 rounded-none p-3 focus:border-primary focus:ring-1 focus:ring-primary outline-none transition-all" placeholder="08xxx" />
               </div>
               <div class="md:col-span-2">
                  <label class="font-label text-sm mb-1 block text-stone-500">Alamat Lengkap</label>
                  <textarea v-model="form.address" class="w-full bg-stone-50 border border-stone-200 rounded-none p-3 focus:border-primary focus:ring-1 focus:ring-primary outline-none transition-all" rows="3" placeholder="Jl. Raya No..."></textarea>
               </div>

               <div>
                  <label class="font-label text-sm mb-1 block text-stone-500">Provinsi</label>
                  <select v-model="form.province_id" class="w-full bg-stone-50 border border-stone-200 rounded-none p-3 focus:border-primary focus:ring-1 focus:ring-primary outline-none transition-all cursor-pointer">
                    <option value="">{{ isProvLoading ? 'Memuat Provinsi...' : 'Pilih Provinsi' }}</option>
                    <option v-for="prov in provinces" :key="prov.id || (prov as any).province_id" :value="prov.id || (prov as any).province_id">{{ prov.name || (prov as any).province_name || (prov as any).province }}</option>
                  </select>
               </div>

               <div>
                  <label class="font-label text-sm mb-1 block text-stone-500">Kota/Kabupaten</label>
                  <select v-model="form.city_id" :disabled="!form.province_id || isCityLoading" class="w-full bg-stone-50 border border-stone-200 rounded-none p-3 focus:border-primary focus:ring-1 focus:ring-primary outline-none transition-all cursor-pointer disabled:opacity-50">
                    <option value="">{{ isCityLoading ? 'Memuat Kota...' : 'Pilih Kota' }}</option>
                    <option v-for="city in cities" :key="city.id" :value="city.id">{{ city.name }}</option>
                  </select>
               </div>

               <div>
                  <label class="font-label text-sm mb-1 block text-stone-500">Kecamatan</label>
                  <select v-model="form.district_id" :disabled="!form.city_id || isDistLoading" class="w-full bg-stone-50 border border-stone-200 rounded-none p-3 focus:border-primary focus:ring-1 focus:ring-primary outline-none transition-all cursor-pointer disabled:opacity-50">
                    <option value="">{{ isDistLoading ? 'Memuat Kecamatan...' : 'Pilih Kecamatan' }}</option>
                    <option v-for="dist in districts" :key="dist.id" :value="dist.id">{{ dist.name }}</option>
                  </select>
               </div>

               <div>
                  <label class="font-label text-sm mb-1 block text-stone-500">Kode Pos</label>
                  <input v-model="form.postal_code" type="text" class="w-full bg-stone-50 border border-stone-200 rounded-none p-3 focus:border-primary focus:ring-1 focus:ring-primary outline-none transition-all" placeholder="12345" />
               </div>
            </div>
          </section>

          <!-- Discount Section -->
          <section class="bg-white p-8 rounded-none shadow-sm border border-stone-200">
            <div class="flex items-center gap-3 mb-6">
              <div class="w-10 h-10 rounded-none bg-stone-100 flex items-center justify-center text-stone-600">
                <span class="material-symbols-outlined">sell</span>
              </div>
              <h2 class="text-xl font-bold text-stone-900" style="font-family: 'Outfit', sans-serif;">Punya Kode Promo?</h2>
            </div>

            <div v-if="!appliedDiscount" class="flex gap-2">
              <input 
                v-model="couponCode" 
                type="text" 
                class="flex-grow bg-stone-50 border border-stone-200 rounded-none px-4 py-3 focus:border-primary outline-none uppercase font-bold tracking-widest text-xs" 
                placeholder="MASUKKAN KODE" 
                @keyup.enter="applyCoupon"
              />
              <button 
                @click="applyCoupon" 
                :disabled="isValidatingCoupon || !couponCode"
                class="shrink-0 px-4 bg-[#1a1209] text-white rounded-none font-bold text-xs transition-all hover:bg-stone-800 disabled:opacity-50 flex items-center justify-center min-w-[90px]"
              >
                {{ isValidatingCoupon ? 'Cek...' : 'Terapkan' }}
              </button>
            </div>
            
            <div v-else class="flex items-center justify-between p-4 bg-primary/5 border border-primary/30 rounded-none">
              <div class="flex items-center gap-3">
                <span class="material-symbols-outlined text-primary">verified</span>
                <div>
                  <p class="font-bold text-stone-900 text-sm">Promo Terpasang: <span class="text-primary uppercase">{{ appliedDiscount.code }}</span></p>
                  <p class="text-[10px] text-stone-500">Potongan sebesar Rp {{ discountAmount.toLocaleString('id-ID') }}</p>
                </div>
              </div>
              <button @click="removeCoupon" class="text-xs font-bold text-red-500 hover:underline">Hapus</button>
            </div>
          </section>

          <!-- Delivery Method Section -->
          <section v-if="form.district_id" class="bg-white p-8 rounded-none shadow-sm border border-stone-200">
            <div class="flex items-center gap-3 mb-8">
              <div class="w-10 h-10 rounded-none bg-stone-100 flex items-center justify-center text-stone-600">
                <span class="material-symbols-outlined">local_shipping</span>
              </div>
              <h2 class="text-xl font-bold text-stone-900" style="font-family: 'Outfit', sans-serif;">Metode Pengiriman</h2>
            </div>

            <div class="flex flex-col gap-4">
               <div v-if="isCalculating" class="flex flex-col gap-3">
                 <div v-for="i in 3" :key="i" class="h-20 bg-stone-50 animate-pulse rounded-none border border-stone-100"></div>
                 <p class="text-xs text-stone-400 text-center mt-2">Menghitung ongkos kirim...</p>
               </div>

               <div v-else-if="shippingResults.length > 0" class="flex flex-col gap-3">
                 <label v-for="res in shippingResults" :key="`${res.courier}_${res.service}`"
                   class="flex items-center justify-between p-4 border rounded-none cursor-pointer transition-all hover:bg-stone-50"
                   :class="form.selected_service === `${res.courier}_${res.service}` ? 'border-primary bg-primary/5 ring-1 ring-primary' : 'border-stone-100'">
                   <div class="flex items-center gap-4">
                      <input type="radio" v-model="form.selected_service" :value="`${res.courier}_${res.service}`" class="accent-primary w-5 h-5"/>
                      <div>
                        <p class="font-bold text-stone-900 uppercase text-sm">{{ res.courier }} - {{ res.service }}</p>
                        <p class="text-xs text-stone-500">{{ res.description }}</p>
                        <p class="text-[10px] font-bold mt-1" style="color: #c19a51;">Estimasi: {{ res.etd }} Hari</p>
                      </div>
                   </div>
                   <span class="font-bold text-stone-900">Rp {{ res.cost.toLocaleString('id-ID') }}</span>
                 </label>
               </div>
               <div v-else class="text-sm text-red-600 bg-red-50 p-4 rounded-none border border-red-100">
                 {{ shippingError || 'Layanan tidak tersedia. Coba ganti alamat atau kurir.' }}
               </div>
            </div>
          </section>
        </div>

        <!-- Right Column: Summary -->
        <div class="w-full lg:w-2/5 xl:w-1/3">
          <div class="sticky top-28 bg-white p-8 rounded-none shadow-lg border border-stone-100">
            <h2 class="text-xl font-bold text-stone-900 mb-8" style="font-family: 'Outfit', sans-serif;">Ringkasan Pesanan</h2>

            <div class="flex flex-col gap-4 text-sm mb-8">
              <div class="flex justify-between text-stone-500">
                <span>Subtotal ({{ cartStore.items.length }} item)</span>
                <span class="font-bold text-stone-900">Rp {{ cartStore.cartTotal.toLocaleString('id-ID') }}</span>
              </div>
              <div class="flex justify-between text-stone-500">
                <span>Ongkos Kirim</span>
                <span class="font-bold text-stone-900">Rp {{ selectedShippingCost.toLocaleString('id-ID') }}</span>
              </div>
              <div v-if="discountAmount > 0" class="flex justify-between text-green-600">
                <span>Diskon Promo</span>
                <span class="font-bold">-Rp {{ discountAmount.toLocaleString('id-ID') }}</span>
              </div>
              <div class="h-px bg-stone-100 my-2"></div>
              <div class="flex justify-between items-center">
                <span class="text-base font-bold text-stone-900">Total Pembayaran</span>
                <span class="text-2xl font-black text-primary" style="color: #c19a51;">Rp {{ grandTotal.toLocaleString('id-ID') }}</span>
              </div>
            </div>

            <button 
              @click="submitOrder" 
              :disabled="isSubmitting || !form.selected_service || !isAddressComplete" 
              class="w-full bg-[#1a1209] text-white py-4 rounded-none font-bold text-sm transition-all hover:scale-[1.02] active:scale-95 disabled:opacity-50 disabled:hover:scale-100 flex items-center justify-center gap-2 shadow-xl shadow-stone-200"
            >
              <span v-if="isSubmitting" class="material-symbols-outlined animate-spin">sync</span>
              {{ isSubmitting ? 'Memproses...' : 'Bayar Sekarang' }}
              <span v-if="!isSubmitting" class="material-symbols-outlined text-lg">arrow_forward</span>
            </button>
            
            <div v-if="checkoutError" class="mt-4 text-xs text-red-600 bg-red-50 p-3 rounded-none text-center">
              {{ checkoutError }}
            </div>
          </div>
        </div>
      </div>
    </main>

    <!-- Address Selector Modal -->
    <Teleport to="body">
      <div v-if="showAddressModal" class="fixed inset-0 z-[100] flex items-center justify-center bg-black/60 backdrop-blur-sm p-4">
        <div class="bg-white w-full max-w-lg rounded-none shadow-2xl p-8 flex flex-col max-h-[80vh]">
          <div class="flex items-center justify-between mb-8">
            <h2 class="text-2xl font-bold text-stone-900" style="font-family: 'Outfit', sans-serif;">Pilih Alamat</h2>
            <button @click="showAddressModal = false" class="w-10 h-10 rounded-none hover:bg-stone-100 flex items-center justify-center transition-all">
              <span class="material-symbols-outlined">close</span>
            </button>
          </div>

          <div class="flex-grow overflow-y-auto pr-2 custom-scrollbar flex flex-col gap-4">
            <div v-for="addr in userAddresses" :key="addr.id" 
              @click="selectAddress(addr)"
              class="p-5 border-2 border-stone-100 rounded-none cursor-pointer hover:border-primary hover:bg-stone-50 transition-all relative group">
              <div v-if="addr.is_default" class="absolute top-4 right-4 px-2 py-1 bg-primary/10 text-primary text-[10px] font-bold rounded-lg uppercase" style="color: #c19a51;">Default</div>
              <p class="font-bold text-stone-900">{{ addr.recipient_name }}</p>
              <p class="text-xs text-stone-500 mt-1">{{ addr.phone }}</p>
              <p class="text-sm text-stone-700 mt-3 leading-relaxed">{{ addr.address }}</p>
              <p class="text-xs text-stone-500 mt-2">{{ addr.district }}, {{ addr.city }}, {{ addr.province }} {{ addr.postal_code }}</p>
            </div>
          </div>

          <div class="mt-8 pt-6 border-t border-stone-100">
            <button @click="showAddressModal = false" class="w-full py-4 rounded-none font-bold text-stone-500 hover:bg-stone-50 transition-all">
              Batal
            </button>
          </div>
        </div>
      </div>
    </Teleport>
  </div>
</template>

<style scoped>
.custom-scrollbar::-webkit-scrollbar {
  width: 4px;
}
.custom-scrollbar::-webkit-scrollbar-track {
  background: transparent;
}
.custom-scrollbar::-webkit-scrollbar-thumb {
  background: #e5e7eb;
  border-radius: 10px;
}
.custom-scrollbar::-webkit-scrollbar-thumb:hover {
  background: #d1d5db;
}
</style>
