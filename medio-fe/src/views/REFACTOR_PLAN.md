# Frontend God Components — Refactor Plan

P1-9 → P1-12 (Phase 3) audit menemukan 4 file Vue dengan LOC > 1000:

| File | LOC | Klasifikasi |
|---|---|---|
| `views/Profile.vue` | 1.520 | 🔴 God component |
| `views/checkout/CheckoutView.vue` | 1.375 | 🔴 God component |
| `views/ProductDetail.vue` | 1.330 | 🔴 God component |
| `views/Product.vue` | 1.189 | 🔴 God component |

## Status Phase 3

Refactor lengkap menjadi sub-component tree butuh ~3 sprint dedicated. Untuk Phase 3 yang sudah dieksekusi:

✅ **Composables shared dibuat** (langkah pertama refactor):
- `composables/useOrderStatus.ts` — status tabs, label, class color (mengganti duplikat di Profile, OrderDetail, Tracking)
- `composables/useFormatMoney.ts` — Intl formatter Rupiah (mengganti duplikat di 6+ files)

✅ **Logger.ts** dibuat dan semua `console.*` di-replace (P1-13). Itu sendiri mengurangi noise di setiap god component.

✅ **`vite.config.ts`** strip `console.*` di production build — safety net.

⏸️ **Sub-component extraction** ditunda ke sprint khusus karena scope-nya butuh:
- 1 minggu untuk Profile.vue (5 sub-domain: Account / Orders / Affiliate / Prescriptions / Loyalty)
- 1 minggu untuk CheckoutView.vue (Cart Summary / Address / Shipping / Payment / Promo)
- 1 minggu untuk Product.vue + ProductDetail.vue (List view filters + Detail tabs)

## Pattern Refactor yang Direkomendasikan

```
views/Profile.vue (1.520 LOC)  →  views/profile/ProfileLayout.vue (~120 LOC, hanya layout & router)
                                  + components/profile/ProfileSidebar.vue
                                  + components/profile/sections/AccountSection.vue
                                  + components/profile/sections/OrdersSection.vue
                                  + components/profile/sections/AffiliateSection.vue
                                  + components/profile/sections/PrescriptionsSection.vue
                                  + components/profile/sections/LoyaltySection.vue
                                  + composables/useProfileOrders.ts        (fetch + pagination)
                                  + composables/useProfileAffiliate.ts     (profile + summary + payouts)
                                  + composables/useProfilePrescriptions.ts (CRUD prescription profiles)
                                  + composables/useProfileLoyalty.ts       (history pagination)
```

Target: **maksimal 300 LOC per .vue file**, logic ke `composables/`.

## Migration Markers

Grep `// FIXME P1-9`, `// FIXME P1-10`, dst untuk find spot extraction berikutnya:

```bash
grep -rn 'FIXME P1-' medio-fe/src/views/
```

## Rules saat extract

1. **Behavior preservation**: refactor tanpa perubahan UX. Selalu test manual flow setelah extract.
2. **Type safety**: definisikan interface props/emit dengan jelas untuk setiap sub-component.
3. **Avoid prop drilling > 2 level**: kalau lebih, gunakan `provide`/`inject` atau Pinia store.
4. **Test coverage**: tambah test Vitest untuk setiap composable saat extract.
5. **Commit per section**: 1 PR = 1 section ekstraksi (e.g., "extract OrdersSection from Profile").
