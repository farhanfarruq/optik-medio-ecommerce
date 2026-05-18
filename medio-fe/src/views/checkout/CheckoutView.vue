<script setup lang="ts">
import { ref, onMounted, computed, watch, nextTick } from 'vue';
import { useCartStore } from '../../stores/cartStore';
import { shippingRepository, type Location } from '../../repositories/ShippingRepository';
import { orderRepository } from '../../repositories/OrderRepository';
import { masterDataRepository, type PaymentMethodItem, type BankAccount } from '../../repositories/MasterDataRepository';
import { useRouter } from 'vue-router';
import { apiClient } from '../../core/api/axiosclient';
import { useToast } from '../../composables/useToast';
import { resolveImageUrl } from '../../core/utils/image';
import { useAuthStore } from '../../stores/authStore';
import { useAnalytics } from '../../composables/useAnalytics';

const { showToast } = useToast();
const cartStore = useCartStore();
const authStore = useAuthStore();
const router = useRouter();
const { trackCheckoutStarted, trackCheckoutFailed } = useAnalytics();

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

// Metode pemenuhan: 'delivery' = dikirim ke alamat, 'store_pickup' = ambil di toko
const fulfillmentMethod = ref<'delivery' | 'store_pickup'>('delivery');

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

// State Payment Method & Bank
const paymentMethods = ref<PaymentMethodItem[]>([]);
const bankAccounts = ref<BankAccount[]>([]);
const selectedPaymentMethodId = ref<number | null>(null);
const selectedBankId = ref<number | null>(null);
const isLoadingPayment = ref(false);

// State Store Close
const storeStatus = ref<{ is_closed: boolean; current_close: any | null } | null>(null);
const isLoadingStoreStatus = ref(false);

const selectedPaymentMethod = computed(() =>
  paymentMethods.value.find(m => m.id === selectedPaymentMethodId.value) || null
);

const isManualPayment = computed(() => {
  const pm = selectedPaymentMethod.value;
  return pm && pm.provider !== 'xendit';
});

const needsBankSelection = computed(() =>
  selectedPaymentMethod.value?.requires_bank_selection ?? false
);

// ── Xendit Payment Modal ─────────────────────────────────────────────────────
const showXenditModal = ref(false);
const xenditCheckoutUrl = ref('');
const xenditOrderId = ref<number | null>(null);
const xenditPickupQuery = ref<Record<string, string> | null>(null);
const isPollingPayment = ref(false);

const openXenditModal = (url: string, orderId: number, pickupQuery: Record<string, string> | null = null) => {
  xenditCheckoutUrl.value = url;
  xenditOrderId.value = orderId;
  xenditPickupQuery.value = pickupQuery;
  showXenditModal.value = true;
  startPaymentPolling(orderId);
};

const closeXenditModal = () => {
  showXenditModal.value = false;
  stopPaymentPolling();
  if (xenditOrderId.value) {
    // Jika pickup: ke waiting-payment dulu (pembayaran belum tentu selesai)
    router.push(`/waiting-payment/${xenditOrderId.value}`);
  }
};

let paymentPollInterval: ReturnType<typeof setInterval> | null = null;

const startPaymentPolling = (orderId: number) => {
  isPollingPayment.value = true;
  paymentPollInterval = setInterval(async () => {
    try {
      const { data } = await apiClient.get(`/orders/${orderId}`);
      const status = data?.status || data?.data?.status;
      if (status && !['unpaid', 'pending'].includes(status.toLowerCase())) {
        stopPaymentPolling();
        showXenditModal.value = false;
        showToast('Pembayaran berhasil! Pesanan sedang diproses.', 'success');
        // Jika pickup: redirect ke halaman booking setelah pembayaran lunas
        if (xenditPickupQuery.value) {
          router.push({ path: '/appointment', query: xenditPickupQuery.value });
        } else {
          router.push(`/orders/${orderId}`);
        }
      }
    } catch (e: any) {
      // Stop polling jika 401 (user tidak terautentikasi) atau 404 (order tidak ditemukan)
      if (e?.response?.status === 401 || e?.response?.status === 404) {
        stopPaymentPolling();
      }
      // Error lain (network, 500) — biarkan polling lanjut
    }
  }, 3000);
};

const stopPaymentPolling = () => {
  isPollingPayment.value = false;
  if (paymentPollInterval) {
    clearInterval(paymentPollInterval);
    paymentPollInterval = null;
  }
};

// State Diskon
const couponCode = ref('');
const appliedDiscount = ref<any>(null);
const isValidatingCoupon = ref(false);

// State Loyalty Points
const loyaltyPointsToUse = ref(0);
const userLoyaltyPoints = computed(() => authStore.user?.loyalty_points || 0);
const userLevelMember = computed(() => authStore.user?.current_level_membership?.level_member || null);
const levelDiscountAmount = computed(() => {
  if (!userLevelMember.value || !userLevelMember.value.discount_percentage) return 0;
  return Math.round((cartStore.cartTotal * userLevelMember.value.discount_percentage) / 100);
});
const maxLoyaltyPoints = computed(() => {
  // Maks 5% dari subtotal, 1 poin = Rp 1.000
  const maxDiscount = Math.floor(cartStore.cartTotal * 0.05);
  return Math.min(userLoyaltyPoints.value, Math.ceil(maxDiscount / 1000));
});
const selectedLoyaltyPoints = computed(() =>
  loyaltyPointsToUse.value > 0 ? Math.min(loyaltyPointsToUse.value, maxLoyaltyPoints.value) : 0
);
const loyaltyDiscountAmount = computed(() => {
  return selectedLoyaltyPoints.value * 1000;
});
const shippingProtectionOpted = ref(false);
const shippingProtectionFee = computed(() => Number(cartStore.calculatedData?.shipping_protection_fee || 0));
const shippingProtectionSummary = computed(() => {
  if (!selectedShipping.value) {
    return 'Pilih layanan kirim terlebih dahulu untuk melihat biaya proteksi.';
  }

  if (!shippingProtectionOpted.value) {
    return 'Opsional. Tambahkan proteksi jika ingin ada perlindungan tambahan saat barang hilang atau rusak di pengiriman.';
  }

  return `Proteksi aktif dengan biaya Rp ${shippingProtectionFee.value.toLocaleString('id-ID')}.`;
});

const applyCoupon = async () => {
  if (!couponCode.value) return;
  
  isValidatingCoupon.value = true;
  try {
    const response = await apiClient.post('/discounts/validate', { code: couponCode.value });
    appliedDiscount.value = response.data.discount;
    showToast('Kupon berhasil diterapkan!', 'success');
    await cartStore.setPromo(null); // Remove promo if applying discount
    await calculateCheckoutTotals(selectedShippingCost.value);
  } catch (error: any) {
    appliedDiscount.value = null;
    const msg = error.response?.data?.message || 'Gagal menerapkan kupon.';
    showToast(msg, 'error');
  } finally {
    isValidatingCoupon.value = false;
  }
};

const removeCoupon = async () => {
  appliedDiscount.value = null;
  couponCode.value = '';
  await calculateCheckoutTotals(selectedShippingCost.value);
};

const handlePromoSelect = async (promoId: number) => {
  if (appliedDiscount.value) {
    await removeCoupon(); // Cannot stack
  }
  
  const targetId = cartStore.appliedPromoId === promoId ? null : promoId;
  
  try {
    await cartStore.setPromo(
      targetId,
      appliedDiscount.value?.id,
      selectedShippingCost.value,
      selectedLoyaltyPoints.value,
      form.value.id,
      shippingProtectionOpted.value,
    );
    if (targetId) {
      showToast('Promo berhasil diterapkan!', 'success');
    } else {
      showToast('Promo dilepas.', 'info');
    }
  } catch (err: any) {
    const msg = err.response?.data?.message || 'Gagal menerapkan promo.';
    showToast(msg, 'error');
  }
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
const formatPromoDescription = (desc: string) => {
  if (!desc) return '';
  return desc.replace(/(\d+)\.00%/g, '$1%');
};

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
    
    // Re-affirm the address ID after all reactive changes have settled
    // This prevents the watcher below from clearing the ID on the final district_id change
    await nextTick();
    form.value.id = addr.id;
    
  } catch (error) {
    console.error('Failed to select address', error);
  } finally {
    // Use nextTick so all pending watchers fire with isAutoFilling=true before we release the guard
    await nextTick();
    isAutoFilling.value = false;
  }
};

const calculateCheckoutTotals = async (shippingCost = selectedShippingCost.value) => {
  try {
    await cartStore.calculateCart(
      appliedDiscount.value?.id,
      fulfillmentMethod.value === 'store_pickup' ? 0 : shippingCost,
      selectedLoyaltyPoints.value,
      fulfillmentMethod.value === 'store_pickup' ? null : form.value.id,
      fulfillmentMethod.value === 'store_pickup' ? false : shippingProtectionOpted.value,
      fulfillmentMethod.value,
    );
  } catch (error: any) {
    checkoutError.value = error?.response?.data?.message || 'Gagal menghitung total checkout.';
  }
};

// Fetch Data Awal & Pre-fill
onMounted(async () => {
  // Track checkout started
  trackCheckoutStarted(
    cartStore.items.length,
    cartStore.items.reduce((sum, item) => sum + (item.price * (item.quantity || 1)), 0)
  );

  try {
    isProvLoading.value = true;
    isLoadingPayment.value = true;
    isLoadingStoreStatus.value = true;

    // Fetch semua data awal secara paralel
    const [, , paymentData, bankData, storeData] = await Promise.all([
      shippingRepository.getProvinces().then(d => { provinces.value = d; }),
      cartStore.fetchPromos(),
      masterDataRepository.getPaymentMethods(),
      masterDataRepository.getBanks(),
      masterDataRepository.getStoreStatus(),
    ]);

    paymentMethods.value = paymentData;
    bankAccounts.value = bankData;
    storeStatus.value = storeData;

    // Auto-select metode pembayaran pertama
    if (paymentMethods.value.length > 0) {
      selectedPaymentMethodId.value = paymentMethods.value[0].id;
    }

    const userResponse = await apiClient.get('/auth/me');
    const user = userResponse.data;
    if (user) {
        form.value.recipient_name = user.name;
        userAddresses.value = user.addresses || [];
        
        const defaultAddr = userAddresses.value.find((a: any) => a.is_default) || userAddresses.value[0];
        if (defaultAddr) {
          await selectAddress(defaultAddr);
        }
    }

    await calculateCheckoutTotals(0);
  } catch (error) {
    console.error('Failed to initialize checkout', error);
  } finally {
    isProvLoading.value = false;
    isLoadingPayment.value = false;
    isLoadingStoreStatus.value = false;
  }
});

watch(() => form.value.selected_service, async (newVal) => {
  if (newVal && selectedShippingCost.value > 0) {
    await calculateCheckoutTotals(selectedShippingCost.value);
  }
});

watch(selectedLoyaltyPoints, async () => {
  await calculateCheckoutTotals(selectedShippingCost.value);
});

watch(shippingProtectionOpted, async () => {
  if (!selectedShipping.value) return;
  await calculateCheckoutTotals(selectedShippingCost.value);
});

// Recalculate saat metode pemenuhan berubah
watch(fulfillmentMethod, async () => {
  await calculateCheckoutTotals(0);
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
  // Saat ambil di toko, alamat tidak diperlukan
  if (fulfillmentMethod.value === 'store_pickup') return true;

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
      if (shippingResults.value.length > 0) {
        await calculateCheckoutTotals(shippingResults.value[0].cost);
      }
    }
  }
};

const selectedShipping = computed(() => {
  if (!form.value.selected_service) return null;
  return shippingResults.value.find(s => `${s.courier}_${s.service}` === form.value.selected_service);
});

const selectedShippingCost = computed(() => {
  if (fulfillmentMethod.value === 'store_pickup') return 0;
  return selectedShipping.value ? selectedShipping.value.cost : 0;
});

const grandTotal = computed(() => {
  // CART-004: Gunakan total dari API sebagai sumber kebenaran jika tersedia
  if (cartStore.calculatedData) {
    return Math.max(0, cartStore.calculatedData.total_price);
  }
  const subtotalAfterDiscount = Math.max(0, cartStore.cartTotal - discountAmount.value);
  return Math.max(0, subtotalAfterDiscount + selectedShippingCost.value + shippingProtectionFee.value - levelDiscountAmount.value - loyaltyDiscountAmount.value);
});

const submitOrder = async () => {
  checkoutError.value = '';

  if (storeStatus.value?.is_closed) {
    showToast('Toko sedang tutup. Checkout tidak dapat diproses.', 'error');
    return;
  }

  if (!isAddressComplete.value) {
    showToast('Lengkapi semua data alamat pengiriman terlebih dahulu.', 'error');
    return;
  }

  const isPickup = fulfillmentMethod.value === 'store_pickup';

  if (!isPickup) {
    const selected = selectedShipping.value;
    if (!selected) {
      showToast('Pilih layanan pengiriman terlebih dahulu.', 'error');
      return;
    }
  }

  isSubmitting.value = true;
  try {
    let shippingAddressId: number | null = null;

    if (!isPickup) {
      shippingAddressId = form.value.id;

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
    }

    const selected = selectedShipping.value;
    const itemsPayload = cartStore.buildCheckoutItemsPayload();

    const payload: any = {
      fulfillment_method: fulfillmentMethod.value,
      payment_method_id: selectedPaymentMethodId.value,
      bank_id: needsBankSelection.value ? selectedBankId.value : null,
      discount_id: appliedDiscount.value?.id || null,
      promo_id: cartStore.appliedPromoId || null,
      loyalty_points_used: selectedLoyaltyPoints.value,
      items: itemsPayload,
      notes: ''
    };

    if (!isPickup && shippingAddressId) {
      payload.shipping_address_id = shippingAddressId;
      payload.courier = selected!.courier;
      payload.courier_service = selected!.service;
      payload.shipping_cost = selected!.cost;
      payload.shipping_protection_opted = shippingProtectionOpted.value;
    }

    const orderResponse: any = await orderRepository.createOrder(payload);
    cartStore.clearCart();

    // Query params untuk redirect ke booking setelah pembayaran (hanya saat pickup)
    const pickupQuery = isPickup ? {
      service: 'pickup',
      order_id: String(orderResponse.id),
      order_number: orderResponse.order_number,
      source_label: `Pesanan #${orderResponse.order_number}`,
    } : null;

    if (orderResponse.payment?.checkout_url) {
      // Xendit: buka modal pembayaran. Setelah selesai, WaitingPayment/callback
      // akan menangani redirect ke booking jika pickup.
      openXenditModal(orderResponse.payment.checkout_url, orderResponse.id, pickupQuery);
    } else if (isManualPayment.value) {
      showToast('Pesanan berhasil! Silakan selesaikan pembayaran.', 'success');
      // Untuk pickup + manual: ke waiting-payment dulu, setelah lunas baru booking
      router.push(`/waiting-payment/${orderResponse.id}`);
    } else if (isPickup) {
      // Metode bayar yang tidak butuh konfirmasi (misal COD/bayar di toko):
      // langsung ke booking
      showToast('Pesanan berhasil! Silakan booking jadwal pengambilan di toko.', 'success');
      router.push({ path: '/appointment', query: pickupQuery! });
    } else {
      showToast('Pesanan berhasil dibuat!', 'success');
      router.push('/orders');
    }
  } catch (error: any) {
    console.error('Order failed', error);
    const validationErrors = error.response?.data?.errors;
    if (validationErrors) {
      const firstError = Object.values(validationErrors)[0];
      const msg = Array.isArray(firstError) ? String(firstError[0]) : 'Data tidak valid.';
      checkoutError.value = msg;
      showToast(msg, 'error');
      trackCheckoutFailed('validation_error', msg);
    } else {
      const msg = error.response?.data?.message || 'Terjadi kesalahan saat membuat pesanan.';
      checkoutError.value = msg;
      showToast(msg, 'error');
      trackCheckoutFailed('server_error', msg);
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

        <div class="relative z-10 h-full max-w-[1440px] mx-auto px-6 md:px-12 flex flex-col justify-between" :style="{ paddingTop: 'calc(var(--header-height, 96px) + 16px)', paddingBottom: '56px' }">
          <!-- Breadcrumb + Back -->
          <div>
            <nav class="flex items-center gap-2 text-xs font-medium mb-2" style="color: rgba(255,255,255,0.55);">
              <router-link to="/" class="hover:text-white transition-colors">Beranda</router-link>
              <span class="material-symbols-outlined text-sm">chevron_right</span>
              <router-link to="/cart" class="hover:text-white transition-colors">Keranjang</router-link>
              <span class="material-symbols-outlined text-sm">chevron_right</span>
              <span class="text-white">Checkout</span>
            </nav>
            <router-link to="/" class="flex items-center gap-2 text-sm font-bold group w-fit transition-all" style="color: rgba(193,154,81,0.9);">
              <span class="material-symbols-outlined text-lg group-hover:-translate-x-1 transition-transform">arrow_back</span>
              Kembali ke Beranda
            </router-link>
          </div>
          <!-- Page Title -->
          <h1 class="text-4xl font-black tracking-tight text-white" style="font-family: 'Outfit', sans-serif;">Checkout</h1>
        </div>
      </div>
    </div>

    <main class="relative z-10 max-w-7xl mx-auto px-6 pb-24" style="padding-top: calc(var(--header-height, 96px) + 40px);">

      <!-- Store Close Alert Banner -->
      <div v-if="storeStatus?.is_closed" class="mb-8 p-5 border flex items-start gap-4" style="background: rgba(220,38,38,0.06); border-color: rgba(220,38,38,0.3);">
        <span class="material-symbols-outlined text-2xl mt-0.5 shrink-0" style="color: #dc2626;">store</span>
        <div>
          <p class="font-black text-sm" style="color: #dc2626;">Toko Sedang Tutup</p>
          <p class="text-xs mt-1" style="color: #b91c1c;" v-if="storeStatus.current_close?.reason">{{ storeStatus.current_close.reason }}</p>
          <p class="text-xs mt-1" style="color: #ef4444;">Checkout tidak dapat diproses saat ini. Silakan coba lagi nanti.</p>
        </div>
      </div>

      <div class="flex flex-col lg:flex-row gap-12 lg:gap-16">
        <!-- Left Column: Forms -->
        <div class="w-full lg:w-3/5 xl:w-2/3 flex flex-col gap-8">
          
          <!-- ── Pilihan Metode Pemenuhan ─────────────────────────────────── -->
          <section class="bg-white p-8 rounded-none shadow-sm border border-stone-200">
            <div class="flex items-center gap-3 mb-6">
              <div class="w-10 h-10 rounded-none bg-stone-100 flex items-center justify-center text-stone-600">
                <span class="material-symbols-outlined">package_2</span>
              </div>
              <div>
                <h2 class="text-xl font-bold text-stone-900" style="font-family: 'Outfit', sans-serif;">Cara Mendapatkan Pesanan</h2>
                <p class="text-xs text-stone-500">Pilih apakah pesanan dikirim atau diambil langsung di toko</p>
              </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
              <!-- Opsi: Dikirim ke Alamat -->
              <label
                class="flex items-start gap-4 p-5 border-2 rounded-none cursor-pointer transition-all hover:bg-stone-50"
                :class="fulfillmentMethod === 'delivery' ? 'border-[#c19a51] bg-amber-50/30 ring-1 ring-[#c19a51]/40' : 'border-stone-200'"
              >
                <input type="radio" v-model="fulfillmentMethod" value="delivery" class="accent-amber-700 w-5 h-5 mt-0.5 shrink-0" />
                <div>
                  <div class="flex items-center gap-2 mb-1">
                    <span class="material-symbols-outlined text-lg" style="color: #c19a51;">local_shipping</span>
                    <p class="font-bold text-sm text-stone-900">Dikirim ke Alamat</p>
                  </div>
                  <p class="text-xs text-stone-500 leading-relaxed">Pesanan dikirim ke alamat tujuan Anda. Biaya ongkir sesuai kurir yang dipilih.</p>
                </div>
              </label>

              <!-- Opsi: Ambil di Toko -->
              <label
                class="flex items-start gap-4 p-5 border-2 rounded-none cursor-pointer transition-all hover:bg-stone-50"
                :class="fulfillmentMethod === 'store_pickup' ? 'border-[#c19a51] bg-amber-50/30 ring-1 ring-[#c19a51]/40' : 'border-stone-200'"
              >
                <input type="radio" v-model="fulfillmentMethod" value="store_pickup" class="accent-amber-700 w-5 h-5 mt-0.5 shrink-0" />
                <div>
                  <div class="flex items-center gap-2 mb-1">
                    <span class="material-symbols-outlined text-lg" style="color: #c19a51;">storefront</span>
                    <p class="font-bold text-sm text-stone-900">Ambil di Toko</p>
                    <span class="text-[9px] font-black uppercase px-2 py-0.5 rounded" style="background: rgba(34,197,94,0.12); color: #15803d;">Gratis Ongkir</span>
                  </div>
                  <p class="text-xs text-stone-500 leading-relaxed">Beli online, ambil langsung di toko kami. Tidak ada biaya pengiriman. Setelah checkout, Anda akan diarahkan untuk booking jadwal pengambilan.</p>
                </div>
              </label>
            </div>

            <!-- Info banner saat pickup dipilih -->
            <div v-if="fulfillmentMethod === 'store_pickup'" class="mt-5 p-4 flex items-start gap-3 rounded-none" style="background: rgba(193,154,81,0.08); border: 1px solid rgba(193,154,81,0.3);">
              <span class="material-symbols-outlined text-xl shrink-0 mt-0.5" style="color: #c19a51;">info</span>
              <div class="text-xs leading-relaxed" style="color: #7a6230;">
                <p class="font-bold mb-1">Cara kerja Ambil di Toko:</p>
                <ol class="list-decimal list-inside space-y-1">
                  <li>Selesaikan pembayaran online seperti biasa.</li>
                  <li>Setelah pesanan dikonfirmasi, Anda akan diarahkan untuk booking jadwal pengambilan.</li>
                  <li>Datang ke toko sesuai jadwal yang dipilih dan tunjukkan nomor pesanan.</li>
                </ol>
                <p class="mt-2 font-semibold">Harga yang berlaku adalah harga online. Promo dan diskon online tetap berlaku.</p>
              </div>
            </div>
          </section>

          <!-- Shipping Destination Section (hanya tampil saat delivery) -->
          <section v-if="fulfillmentMethod === 'delivery'" class="bg-white p-8 rounded-none shadow-sm border border-stone-200 group relative">
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

                    <!-- Promos & Discount Section -->
          <section class="bg-white p-8 rounded-none shadow-sm border border-stone-200">
            <div class="flex items-center gap-3 mb-6">
              <div class="w-10 h-10 rounded-none bg-stone-100 flex items-center justify-center text-stone-600">
                <span class="material-symbols-outlined">sell</span>
              </div>
              <h2 class="text-xl font-bold text-stone-900" style="font-family: 'Outfit', sans-serif;">Punya Promo atau Diskon?</h2>
            </div>
            
            <p class="text-xs text-stone-500 mb-4">* Anda hanya dapat menggunakan salah satu: Promo Eksklusif ATAU Kode Diskon.</p>

            <div v-if="cartStore.applicablePromos.length > 0" class="mb-6 flex flex-col gap-3">
              <h3 class="font-bold text-sm text-stone-800">Pilih Promo Eksklusif</h3>
              <div 
                v-for="promo in cartStore.applicablePromos" 
                :key="promo.id"
                @click="handlePromoSelect(promo.id)"
                class="p-4 border rounded-none cursor-pointer transition-all hover:bg-stone-50 flex justify-between items-center"
                :class="cartStore.appliedPromoId === promo.id ? 'border-primary bg-primary/5 ring-1 ring-primary' : 'border-stone-100'"
              >
                <div>
                  <p class="font-bold text-stone-900 text-sm">{{ promo.name }}</p>
                  <p class="text-xs text-stone-500 mt-1">{{ formatPromoDescription(promo.description) }}</p>
                </div>
                <div v-if="cartStore.appliedPromoId === promo.id" class="text-primary">
                  <span class="material-symbols-outlined">check_circle</span>
                </div>
              </div>
            </div>

            <div class="h-px bg-stone-100 mb-6"></div>

            <h3 class="font-bold text-sm text-stone-800 mb-3">Atau Masukkan Kode Diskon</h3>
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
                  <p class="font-bold text-stone-900 text-sm">Diskon Terpasang: <span class="text-primary uppercase">{{ appliedDiscount.code }}</span></p>
                  <p class="text-[10px] text-stone-500" v-if="cartStore.calculatedData">Potongan sebesar Rp {{ cartStore.calculatedData.discount_amount.toLocaleString('id-ID') }}</p>
                </div>
              </div>
              <button @click="removeCoupon" class="text-xs font-bold text-red-500 hover:underline">Hapus</button>
            </div>
          </section>

          <!-- Delivery Method Section -->
          <section v-if="fulfillmentMethod === 'delivery' && form.district_id" class="bg-white p-8 rounded-none shadow-sm border border-stone-200">
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

          <!-- Payment Method Section -->
          <section class="bg-white p-8 rounded-none shadow-sm border border-stone-200">
            <div class="flex items-center gap-3 mb-6">
              <div class="w-10 h-10 rounded-none bg-stone-100 flex items-center justify-center text-stone-600">
                <span class="material-symbols-outlined">credit_card</span>
              </div>
              <div>
                <h2 class="font-black text-lg" style="color: #1a1209; font-family: 'Outfit', sans-serif;">Metode Pembayaran</h2>
                <p class="text-xs text-stone-500 mt-0.5">Pilih cara pembayaran yang Anda inginkan</p>
              </div>
            </div>

            <div v-if="isLoadingPayment" class="flex items-center gap-2 py-6">
              <span class="material-symbols-outlined animate-spin" style="color: #c19a51;">sync</span>
              <span class="text-sm text-stone-500">Memuat metode pembayaran...</span>
            </div>

            <div v-else-if="paymentMethods.length === 0" class="py-6 text-center text-sm text-stone-400">
              Tidak ada metode pembayaran tersedia.
            </div>

            <div v-else class="flex flex-col gap-3">
              <label
                v-for="pm in paymentMethods"
                :key="pm.id"
                class="flex items-start gap-4 p-4 border cursor-pointer transition-all hover:bg-stone-50 rounded-none"
                :class="selectedPaymentMethodId === pm.id ? 'border-amber-700 bg-amber-50/30 ring-1 ring-amber-700/30' : 'border-stone-100'"
              >
                <input type="radio" v-model="selectedPaymentMethodId" :value="pm.id" class="accent-amber-700 w-4 h-4 mt-0.5 shrink-0"/>
                <div class="flex-grow min-w-0">
                  <div class="flex items-center gap-2 mb-1">
                    <span class="material-symbols-outlined text-base" style="color: #c19a51;">{{ pm.type === 'online' ? 'account_balance' : 'swap_horiz' }}</span>
                    <p class="font-bold text-sm" style="color: #1a1209;">{{ pm.name }}</p>
                    <span v-if="pm.provider === 'xendit'" class="text-[9px] font-black uppercase px-2 py-0.5 rounded" style="background: rgba(193,154,81,0.12); color: #7a6230;">Otomatis</span>
                  </div>
                  <p v-if="pm.instructions" class="text-xs text-stone-500 leading-relaxed">{{ pm.instructions }}</p>
                </div>
              </label>
            </div>

            <div class="mt-6 pt-6 border-t border-stone-100">
              <div class="flex items-start justify-between gap-4 p-4 border border-stone-200 bg-stone-50">
                <div class="min-w-0">
                  <p class="text-sm font-bold" style="color: #1a1209;">Proteksi Pengiriman</p>
                  <p class="text-xs leading-relaxed text-stone-500 mt-1">
                    {{ shippingProtectionSummary }}
                  </p>
                </div>
                <label class="shrink-0 inline-flex items-center gap-3 cursor-pointer">
                  <span class="text-xs font-bold text-stone-700">
                    {{ shippingProtectionOpted ? 'Aktif' : 'Tidak' }}
                  </span>
                  <input
                    v-model="shippingProtectionOpted"
                    type="checkbox"
                    class="h-4 w-4 accent-amber-700"
                    :disabled="!selectedShipping"
                  />
                </label>
              </div>
            </div>

            <!-- Bank Account Selection -->
            <div v-if="needsBankSelection && bankAccounts.length > 0" class="mt-6 pt-6 border-t border-stone-100">
              <p class="text-sm font-bold mb-3" style="color: #1a1209;">Rekening Tujuan Transfer</p>
              <div class="flex flex-col gap-3">
                <label
                  v-for="bank in bankAccounts"
                  :key="bank.id"
                  class="flex items-center gap-4 p-4 border cursor-pointer transition-all hover:bg-stone-50 rounded-none"
                  :class="selectedBankId === bank.id ? 'border-amber-700 bg-amber-50/30 ring-1 ring-amber-700/30' : 'border-stone-100'"
                >
                  <input type="radio" v-model="selectedBankId" :value="bank.id" class="accent-amber-700 w-4 h-4 shrink-0"/>
                  <div>
                    <p class="font-bold text-sm" style="color: #1a1209;">{{ bank.name }}</p>
                    <p class="text-xs text-stone-500">{{ bank.account_number }} a/n {{ bank.account_name }}</p>
                    <p v-if="bank.branch" class="text-[10px] text-stone-400 mt-0.5">Cabang: {{ bank.branch }}</p>
                  </div>
                </label>
              </div>
            </div>
          </section>

        </div>

        <!-- Right Column: Summary -->
        <div class="w-full lg:w-2/5 xl:w-1/3">
          <div class="sticky top-28 bg-white p-8 rounded-none shadow-lg border border-stone-100">
            <h2 class="text-xl font-bold text-stone-900 mb-8" style="font-family: 'Outfit', sans-serif;">Ringkasan Pesanan</h2>

            <div class="space-y-4 mb-8">
              <div
                v-for="item in cartStore.items"
                :key="item.id + '-' + (item.variant?.color || '') + '-' + (item.lens_option_id || '')"
                class="flex gap-3 border-b border-stone-100 pb-4 last:border-b-0 last:pb-0"
              >
                <div class="w-16 h-16 border border-stone-100 bg-stone-50 flex items-center justify-center shrink-0">
                  <img :src="resolveImageUrl(item.image_url || item.images?.[0], item.name)" :alt="item.name" class="w-full h-full object-contain p-1" />
                </div>
                <div class="min-w-0 flex-1">
                  <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                      <h3 class="text-sm font-black text-stone-900 line-clamp-2">{{ item.name }}</h3>
                      <p class="text-xs text-stone-500 mt-1">Qty {{ item.quantity }}</p>
                    </div>
                    <p class="text-sm font-black text-stone-900 whitespace-nowrap">Rp {{ (item.price * item.quantity).toLocaleString('id-ID') }}</p>
                  </div>
                  <div v-if="item.configuration_snapshot" class="mt-3 p-3 text-xs space-y-1" style="background: rgba(193,154,81,0.08); color: #5a5248;">
                    <p v-if="item.configuration_snapshot.lens_option"><b>Lensa:</b> {{ item.configuration_snapshot.lens_option.name }} (+Rp {{ Number(item.configuration_snapshot.lens_option.base_price || 0).toLocaleString('id-ID') }})</p>
                    <p v-if="item.configuration_snapshot.lens_coating"><b>Coating:</b> {{ item.configuration_snapshot.lens_coating.name }} (+Rp {{ Number(item.configuration_snapshot.lens_coating.price || 0).toLocaleString('id-ID') }})</p>
                    <p v-if="item.configuration_snapshot.prescription_profile_id"><b>Resep:</b> Profil #{{ item.configuration_snapshot.prescription_profile_id }}</p>
                    <p v-else-if="item.configuration_snapshot.prescription"><b>Resep:</b> Input manual</p>
                  </div>
                </div>
              </div>
            </div>

            <div class="flex flex-col gap-4 text-sm mb-8">
                            <div class="flex justify-between text-stone-500">
                <span>Subtotal ({{ cartStore.calculatedData ? cartStore.calculatedData.items.length : cartStore.items.length }} item)</span>
                <span class="font-bold text-stone-900">Rp {{ (cartStore.calculatedData ? cartStore.calculatedData.subtotal : cartStore.cartTotal).toLocaleString('id-ID') }}</span>
              </div>
              <div class="flex justify-between text-stone-500">
                <span>Ongkos Kirim</span>
                <span class="font-bold" :class="fulfillmentMethod === 'store_pickup' ? 'text-green-600' : 'text-stone-900'">
                  {{ fulfillmentMethod === 'store_pickup' ? 'Gratis (Ambil di Toko)' : 'Rp ' + selectedShippingCost.toLocaleString('id-ID') }}
                </span>
              </div>
              <div v-if="shippingProtectionOpted" class="flex justify-between text-stone-500">
                <span>Proteksi Pengiriman</span>
                <span class="font-bold text-stone-900">Rp {{ shippingProtectionFee.toLocaleString('id-ID') }}</span>
              </div>
              <div v-if="cartStore.calculatedData && cartStore.calculatedData.discount_amount > 0" class="flex justify-between text-green-600">
                <span>Diskon Promo Code</span>
                <span class="font-bold">-Rp {{ cartStore.calculatedData.discount_amount.toLocaleString('id-ID') }}</span>
              </div>
              <div v-if="cartStore.calculatedData && cartStore.calculatedData.promo_discount_amount > 0" class="flex justify-between text-green-600">
                <span>Promo Eksklusif</span>
                <span class="font-bold">-Rp {{ cartStore.calculatedData.promo_discount_amount.toLocaleString('id-ID') }}</span>
              </div>

              <!-- Level Member Discount -->
              <div v-if="levelDiscountAmount > 0" class="flex justify-between text-amber-600">
                <span class="flex items-center gap-1">
                  <span class="material-symbols-outlined text-sm">stars</span>
                  Diskon Member {{ userLevelMember?.name }} ({{ userLevelMember?.discount_percentage }}%)
                </span>
                <span class="font-bold">-Rp {{ levelDiscountAmount.toLocaleString('id-ID') }}</span>
              </div>

              <!-- Loyalty Points Redemption -->
              <div v-if="userLoyaltyPoints > 0" class="border rounded-none p-3 mt-1" style="background: #fffdf7; border-color: rgba(193,154,81,0.25);">
                <div class="flex items-center justify-between mb-2">
                  <span class="text-xs font-black uppercase tracking-wider" style="color: #8a7a60;">
                    <span class="material-symbols-outlined text-sm align-middle" style="color: #c19a51;">toll</span>
                    Gunakan Loyalty Points
                  </span>
                  <span class="text-xs font-bold" style="color: #c19a51;">{{ userLoyaltyPoints.toLocaleString('id-ID') }} poin tersedia</span>
                </div>
                <div class="flex items-center gap-2">
                  <input
                    v-model.number="loyaltyPointsToUse"
                    type="number"
                    :min="0"
                    :max="maxLoyaltyPoints"
                    class="w-24 border px-2 py-1.5 text-sm text-center font-bold"
                    style="border-color: rgba(193,154,81,0.4); color: #1a1209;"
                    placeholder="0"
                  />
                  <span class="text-xs" style="color: #8a7a60;">poin = Rp {{ loyaltyDiscountAmount.toLocaleString('id-ID') }}</span>
                  <button v-if="loyaltyPointsToUse > 0" @click="loyaltyPointsToUse = 0" class="ml-auto text-xs font-bold underline" style="color: #dc2626;">Hapus</button>
                </div>
                <p class="text-[10px] mt-1.5" style="color: #b0a590;">Maks {{ maxLoyaltyPoints.toLocaleString('id-ID') }} poin (5% dari subtotal). 1 poin = Rp 1.000.</p>
                <div v-if="loyaltyDiscountAmount > 0" class="flex justify-between mt-2 pt-2 border-t text-green-600" style="border-color: rgba(193,154,81,0.2);">
                  <span class="text-xs">Potongan Loyalty Points</span>
                  <span class="text-xs font-bold">-Rp {{ loyaltyDiscountAmount.toLocaleString('id-ID') }}</span>
                </div>
              </div>
              
              <!-- Free Items -->
              <div v-if="cartStore.calculatedData" class="mt-4 border-t border-stone-50 pt-4">
                <div v-for="cItem in cartStore.calculatedData.items" :key="cItem.product_id + (cItem.name || cItem.product_name)">
                  <div v-if="cItem.is_free" class="flex items-center gap-3 p-3 bg-primary/5 border border-primary/10 mb-2">
                    <div class="w-12 h-12 rounded-none bg-white border border-primary/10 flex items-center justify-center p-1 shrink-0">
                      <img :src="resolveImageUrl(cItem.image, cItem.name || cItem.product_name)" class="w-full h-full object-contain" />
                    </div>
                    <div class="flex flex-col flex-grow min-w-0">
                      <p class="text-[9px] font-black uppercase tracking-[0.1em] text-primary mb-0.5">Bonus Hadiah</p>
                      <h3 class="text-[11px] font-bold text-stone-900 leading-tight line-clamp-2">{{ cItem.name || cItem.product_name }}</h3>
                      <p class="text-[10px] text-stone-500 mt-1 font-bold">Qty: {{ cItem.quantity }} <span class="ml-2 text-primary">GRATIS</span></p>
                    </div>
                  </div>
                </div>
              </div>

              <div class="h-px bg-stone-100 my-2"></div>
              <div class="flex justify-between items-center">
                <span class="text-base font-bold text-stone-900">Total Pembayaran</span>
                <span class="text-2xl font-black text-primary" style="color: #c19a51;">Rp {{ grandTotal.toLocaleString('id-ID') }}</span>
              </div>
              <p v-if="loyaltyPointsToUse > 0 || levelDiscountAmount > 0" class="text-[10px] text-right" style="color: #8a7a60;">
                Hemat Rp {{ (levelDiscountAmount + loyaltyDiscountAmount + (cartStore.calculatedData?.discount_amount || 0) + (cartStore.calculatedData?.promo_discount_amount || 0)).toLocaleString('id-ID') }} dari total belanja
              </p>
            </div>

            <button 
              @click="submitOrder" 
              :disabled="isSubmitting || (fulfillmentMethod === 'delivery' && !form.selected_service) || !isAddressComplete" 
              class="w-full bg-[#1a1209] text-white py-4 rounded-none font-bold text-sm transition-all hover:scale-[1.02] active:scale-95 disabled:opacity-50 disabled:hover:scale-100 flex items-center justify-center gap-2 shadow-xl shadow-stone-200"
            >
              <span v-if="isSubmitting" class="material-symbols-outlined animate-spin">sync</span>
              {{ isSubmitting ? 'Memproses...' : (fulfillmentMethod === 'store_pickup' ? 'Bayar & Booking Pengambilan' : 'Bayar Sekarang') }}
              <span v-if="!isSubmitting" class="material-symbols-outlined text-lg">{{ fulfillmentMethod === 'store_pickup' ? 'storefront' : 'arrow_forward' }}</span>
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

    <!-- ╔══════════════════════════════════════════════════╗ -->
    <!-- ║         XENDIT PAYMENT MODAL OVERLAY            ║ -->
    <!-- ╚══════════════════════════════════════════════════╝ -->
    <Teleport to="body">
      <div
        v-if="showXenditModal"
        class="fixed inset-0 z-[9999] flex items-center justify-center"
        style="background: rgba(10,8,5,0.75); backdrop-filter: blur(20px);"
      >
        <div
          class="relative w-full flex flex-col"
          style="max-width: 520px; max-height: 92vh; background: #faf8f5; border: 1px solid rgba(193,154,81,0.2); box-shadow: 0 30px 80px rgba(0,0,0,0.35);"
        >
          <!-- Header -->
          <div class="flex items-center justify-between px-6 py-4 border-b" style="border-color: rgba(193,154,81,0.15);">
            <div class="flex items-center gap-3">
              <div class="w-8 h-8 flex items-center justify-center" style="background: rgba(193,154,81,0.1);">
                <span class="material-symbols-outlined text-base" style="color: #c19a51;">lock</span>
              </div>
              <div>
                <p class="text-xs font-black uppercase tracking-[0.2em]" style="color: #1a1209;">Pembayaran Aman</p>
                <p class="text-[10px]" style="color: #8a7a60;">Diproses oleh Xendit · SSL Terenkripsi</p>
              </div>
            </div>
            <div class="flex items-center gap-3">
              <!-- Polling indicator -->
              <div v-if="isPollingPayment" class="flex items-center gap-1.5">
                <div class="w-1.5 h-1.5 rounded-full animate-pulse" style="background: #c19a51;"></div>
                <span class="text-[10px]" style="color: #8a7a60;">Menunggu pembayaran...</span>
              </div>
              <button
                @click="closeXenditModal"
                class="w-9 h-9 flex items-center justify-center transition-all hover:opacity-70"
                style="background: rgba(193,154,81,0.1); color: #7a6230;"
                title="Tutup dan lanjutkan nanti"
              >
                <span class="material-symbols-outlined text-base">close</span>
              </button>
            </div>
          </div>

          <!-- Iframe Xendit -->
          <div class="flex-1 relative" style="min-height: 500px;">
            <iframe
              v-if="xenditCheckoutUrl"
              :src="xenditCheckoutUrl"
              class="w-full h-full border-0"
              style="min-height: 500px;"
              allow="payment"
              title="Xendit Payment"
            ></iframe>
            <div v-else class="flex items-center justify-center h-full">
              <div class="w-8 h-8 rounded-none border-4 border-t-transparent animate-spin" style="border-color: rgba(193,154,81,0.25); border-top-color: #c19a51;"></div>
            </div>
          </div>

          <!-- Footer -->
          <div class="px-6 py-3 border-t flex items-center justify-between" style="border-color: rgba(193,154,81,0.15); background: rgba(245,242,238,0.6);">
            <p class="text-[10px]" style="color: #8a7a60;">Tutup jendela ini untuk melanjutkan pembayaran nanti dari halaman pesanan.</p>
            <button
              @click="closeXenditModal"
              class="text-[10px] font-black uppercase tracking-wider underline flex-shrink-0 ml-4"
              style="color: #8a7a60;"
            >
              Bayar Nanti
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
