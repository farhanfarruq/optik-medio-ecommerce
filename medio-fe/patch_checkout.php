<?php
$file = 'src/views/checkout/CheckoutView.vue';
$content = file_get_contents($file);

// Add fetchPromos and calculate on mount
$content = str_replace(
    "provinces.value = await shippingRepository.getProvinces();",
    "provinces.value = await shippingRepository.getProvinces();\n    await cartStore.fetchPromos();\n    await cartStore.calculateCart(appliedDiscount.value?.id, 0);",
    $content
);

// Update calculateShipping to include discount and promo
$content = str_replace(
    "isCalculating.value = false;",
    "isCalculating.value = false;\n      if (shippingResults.value.length > 0) {\n        await cartStore.calculateCart(appliedDiscount.value?.id, shippingResults.value[0].cost);\n      }",
    $content
);

// Watch selected shipping to recalculate
$watchStr = <<<EOT
watch(() => form.value.selected_service, async (newVal) => {
  if (newVal && selectedShippingCost.value > 0) {
    await cartStore.calculateCart(appliedDiscount.value?.id, selectedShippingCost.value);
  }
});
EOT;

$content = str_replace(
    "// Watchers",
    $watchStr . "\n\n// Watchers",
    $content
);

// Re-calculate when applying/removing discount
$content = str_replace(
    "showToast('Kupon berhasil diterapkan!', 'success');",
    "showToast('Kupon berhasil diterapkan!', 'success');\n    cartStore.setPromo(null); // Remove promo if applying discount\n    await cartStore.calculateCart(appliedDiscount.value.id, selectedShippingCost.value);",
    $content
);

$content = str_replace(
    "couponCode.value = '';",
    "couponCode.value = '';\n  cartStore.calculateCart(null, selectedShippingCost.value);",
    $content
);

// Add handler for promo select
$promoHandler = <<<EOT
const handlePromoSelect = async (promoId: number) => {
  if (appliedDiscount.value) {
    removeCoupon(); // Cannot stack
  }
  
  if (cartStore.appliedPromoId === promoId) {
    cartStore.setPromo(null); // Unselect
  } else {
    cartStore.setPromo(promoId);
  }
  await cartStore.calculateCart(null, selectedShippingCost.value);
};
EOT;

$content = str_replace(
    "const removeCoupon = () => {",
    $promoHandler . "\n\nconst removeCoupon = () => {",
    $content
);

// Replace discount section UI with Promo + Discount
$discountSection = <<<EOT
          <!-- Promos & Discount Section -->
          <section class="bg-white p-8 rounded-none shadow-sm border border-stone-200">
            <div class="flex items-center gap-3 mb-6">
              <div class="w-10 h-10 rounded-none bg-stone-100 flex items-center justify-center text-stone-600">
                <span class="material-symbols-outlined">sell</span>
              </div>
              <h2 class="text-xl font-bold text-stone-900" style="font-family: 'Outfit', sans-serif;">Punya Promo atau Diskon?</h2>
            </div>
            
            <p class="text-xs text-stone-500 mb-4">* Anda hanya dapat menggunakan salah satu: Promo Eksklusif ATAU Kode Diskon.</p>

            <div v-if="cartStore.activePromos.length > 0" class="mb-6 flex flex-col gap-3">
              <h3 class="font-bold text-sm text-stone-800">Pilih Promo Eksklusif</h3>
              <div 
                v-for="promo in cartStore.activePromos" 
                :key="promo.id"
                @click="handlePromoSelect(promo.id)"
                class="p-4 border rounded-none cursor-pointer transition-all hover:bg-stone-50 flex justify-between items-center"
                :class="cartStore.appliedPromoId === promo.id ? 'border-primary bg-primary/5 ring-1 ring-primary' : 'border-stone-100'"
              >
                <div>
                  <p class="font-bold text-stone-900 text-sm">{{ promo.name }}</p>
                  <p class="text-xs text-stone-500 mt-1">{{ promo.description }}</p>
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
EOT;

$content = preg_replace(
    "/<\!-- Discount Section -->.*?<\/section>/s",
    $discountSection,
    $content
);

// Update Right Column summary to use calculatedData
$summaryData = <<<EOT
              <div class="flex justify-between text-stone-500">
                <span>Subtotal ({{ cartStore.calculatedData ? cartStore.calculatedData.items.length : cartStore.items.length }} item)</span>
                <span class="font-bold text-stone-900">Rp {{ (cartStore.calculatedData ? cartStore.calculatedData.subtotal : cartStore.cartTotal).toLocaleString('id-ID') }}</span>
              </div>
              <div class="flex justify-between text-stone-500">
                <span>Ongkos Kirim</span>
                <span class="font-bold text-stone-900">Rp {{ selectedShippingCost.toLocaleString('id-ID') }}</span>
              </div>
              <div v-if="cartStore.calculatedData && cartStore.calculatedData.discount_amount > 0" class="flex justify-between text-green-600">
                <span>Diskon Promo Code</span>
                <span class="font-bold">-Rp {{ cartStore.calculatedData.discount_amount.toLocaleString('id-ID') }}</span>
              </div>
              <div v-if="cartStore.calculatedData && cartStore.calculatedData.promo_discount_amount > 0" class="flex justify-between text-green-600">
                <span>Promo Eksklusif</span>
                <span class="font-bold">-Rp {{ cartStore.calculatedData.promo_discount_amount.toLocaleString('id-ID') }}</span>
              </div>
              
              <!-- Free Items -->
              <div v-if="cartStore.calculatedData" class="mt-2">
                <div v-for="cItem in cartStore.calculatedData.items" :key="cItem.product_id + cItem.product_name" class="flex justify-between items-center mb-1">
                  <div v-if="cItem.is_free" class="flex items-center gap-2 text-xs text-primary">
                    <span class="material-symbols-outlined text-sm">card_giftcard</span>
                    <span>{{ cItem.product_name }} (x{{ cItem.quantity }})</span>
                  </div>
                  <span v-if="cItem.is_free" class="font-bold text-xs text-primary">GRATIS</span>
                </div>
              </div>

              <div class="h-px bg-stone-100 my-2"></div>
              <div class="flex justify-between items-center">
                <span class="text-base font-bold text-stone-900">Total Pembayaran</span>
                <span class="text-2xl font-black text-primary" style="color: #c19a51;">Rp {{ (cartStore.calculatedData ? cartStore.calculatedData.total_price : grandTotal).toLocaleString('id-ID') }}</span>
              </div>
EOT;

$content = preg_replace(
    "/<div class=\"flex justify-between text-stone-500\">\s*<span>Subtotal \({{ cartStore\.items\.length }} item\)<\/span>.*?<div class=\"flex justify-between items-center\">\s*<span class=\"text-base font-bold text-stone-900\">Total Pembayaran<\/span>\s*<span class=\"text-2xl font-black text-primary\" style=\"color: #c19a51;\">Rp {{ grandTotal\.toLocaleString\('id-ID'\) }}<\/span>\s*<\/div>/s",
    $summaryData,
    $content
);

// Ensure that payload includes promo_id
$content = str_replace(
    "discount_id: appliedDiscount.value?.id || null,",
    "discount_id: appliedDiscount.value?.id || null,\n      promo_id: cartStore.appliedPromoId || null,",
    $content
);

file_put_contents($file, $content);
