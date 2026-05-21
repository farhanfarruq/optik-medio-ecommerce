<script setup lang="ts">
import { logger } from '../../core/utils/logger';
import { ref, onMounted, computed } from 'vue';
import { settingRepository, type AppSettings } from '../../repositories/SettingRepository';

const settings = ref<AppSettings | null>(null);

onMounted(async () => {
  try {
    settings.value = await settingRepository.getSettings();
  } catch (error) {
    logger.error('Failed to load settings', error);
  }
});

const currentYear = computed(() => new Date().getFullYear());
const storeAddress = computed(() => settings.value?.store_address || 'Pasar, Bandarsari, Lampung Tengah');
const storePhone = computed(() => settings.value?.store_phone || '0813-1196-9585');
const storeHours = computed(() => settings.value?.store_opening_hours || 'Buka 09.00 — 20.30');
const storeLocationUrl = computed(() => settings.value?.store_location_url || '#');
</script>

<template>
  <footer class="site-footer">
    <div class="container-premium site-footer__inner">
      <!-- Trust strip — high-credibility row, sebelum link grid -->
      <ul class="site-footer__trust">
        <li class="site-footer__trust-tile">
          <span class="material-symbols-outlined">verified</span>
          <div>
            <p class="site-footer__trust-title">Produk Original</p>
            <p class="site-footer__trust-meta">Distribusi resmi dengan kartu garansi</p>
          </div>
        </li>
        <li class="site-footer__trust-tile">
          <span class="material-symbols-outlined">visibility</span>
          <div>
            <p class="site-footer__trust-title">Konsultasi Optik</p>
            <p class="site-footer__trust-meta">Refraksi & rekomendasi lensa</p>
          </div>
        </li>
        <li class="site-footer__trust-tile">
          <span class="material-symbols-outlined">workspace_premium</span>
          <div>
            <p class="site-footer__trust-title">Garansi & Servis</p>
            <p class="site-footer__trust-meta">Klaim garansi mudah</p>
          </div>
        </li>
        <li class="site-footer__trust-tile">
          <span class="material-symbols-outlined">storefront</span>
          <div>
            <p class="site-footer__trust-title">Pickup di Toko</p>
            <p class="site-footer__trust-meta">Fitting & ambil sendiri</p>
          </div>
        </li>
      </ul>

      <div class="site-footer__grid">
        <section class="site-footer__brand">
          <div class="site-footer__brand-mark">
            <h2 class="site-footer__logo">Optik <span class="text-gold">Medio</span></h2>
            <span class="site-footer__rule" aria-hidden="true"></span>
          </div>
          <p class="site-footer__lede">
            Destinasi optical commerce di Lampung Tengah untuk frame premium, lensa berkualitas, konsultasi resep, dan pickup di toko.
          </p>

          <ul class="site-footer__social" aria-label="Media sosial Optik Medio">
            <li>
              <a href="#" class="site-footer__social-link" aria-label="Instagram">
                <span class="material-symbols-outlined">photo_camera</span>
              </a>
            </li>
            <li>
              <a href="#" class="site-footer__social-link" aria-label="Facebook">
                <span class="material-symbols-outlined">public</span>
              </a>
            </li>
            <li>
              <a
                :href="storePhone ? `https://wa.me/${storePhone.replace(/\D/g, '')}` : '#'"
                target="_blank"
                rel="noopener noreferrer"
                class="site-footer__social-link"
                aria-label="WhatsApp"
              >
                <span class="material-symbols-outlined">chat</span>
              </a>
            </li>
          </ul>
        </section>

        <nav class="site-footer__nav" aria-label="Belanja">
          <h3 class="site-footer__heading">Belanja</h3>
          <ul class="site-footer__list">
            <li><router-link to="/products" class="site-footer__link">Semua Koleksi</router-link></li>
            <li><router-link to="/products?has_promo=true" class="site-footer__link">Promo Aktif</router-link></li>
            <li><router-link to="/face-shape-quiz" class="site-footer__link">Quiz Bentuk Wajah</router-link></li>
            <li><router-link to="/virtual-try-on" class="site-footer__link">Coba Virtual</router-link></li>
            <li><router-link to="/compare" class="site-footer__link">Bandingkan Produk</router-link></li>
          </ul>
        </nav>

        <nav class="site-footer__nav" aria-label="Bantuan">
          <h3 class="site-footer__heading">Bantuan</h3>
          <ul class="site-footer__list">
            <li><router-link to="/appointment" class="site-footer__link">Booking Konsultasi</router-link></li>
            <li><router-link to="/blog" class="site-footer__link">Blog & Artikel</router-link></li>
            <li><router-link to="/faq" class="site-footer__link">FAQ</router-link></li>
            <li><router-link to="/loyalty" class="site-footer__link">Program Loyalty</router-link></li>
            <li><router-link to="/complaints/new" class="site-footer__link">Komplain</router-link></li>
          </ul>
        </nav>

        <section class="site-footer__contact" aria-label="Kontak Optik Medio">
          <h3 class="site-footer__heading">Hubungi Kami</h3>
          <ul class="site-footer__contact-list">
            <li>
              <span class="material-symbols-outlined">location_on</span>
              <span>{{ storeAddress }}</span>
            </li>
            <li>
              <span class="material-symbols-outlined">call</span>
              <a :href="`tel:${storePhone}`" class="site-footer__link">{{ storePhone }}</a>
            </li>
            <li>
              <span class="material-symbols-outlined">schedule</span>
              <span>{{ storeHours }}</span>
            </li>
          </ul>
          <a
            :href="storeLocationUrl"
            target="_blank"
            rel="noopener noreferrer"
            class="site-footer__map-cta"
          >
            <span class="material-symbols-outlined">directions</span>
            <span>Petunjuk ke Toko</span>
          </a>
        </section>
      </div>

      <div class="site-footer__bottom">
        <p class="site-footer__copy">© {{ currentYear }} Optik Medio. Seluruh hak cipta dilindungi.</p>
        <ul class="site-footer__legal">
          <li><router-link to="/terms" class="site-footer__link">Syarat & Ketentuan</router-link></li>
          <li><router-link to="/privacy" class="site-footer__link">Kebijakan Privasi</router-link></li>
          <li><router-link to="/faq" class="site-footer__link">FAQ</router-link></li>
        </ul>
      </div>
    </div>
  </footer>
</template>

<style scoped>
.site-footer {
  width: 100%;
  background: var(--graphite);
  color: var(--ivory);
  border-top: 1px solid rgba(255, 255, 255, 0.08);
}

.site-footer__inner {
  padding-top: clamp(48px, 6vw, 88px);
  padding-bottom: clamp(28px, 3vw, 36px);
  display: flex;
  flex-direction: column;
  gap: clamp(28px, 4vw, 56px);
}

/* Trust strip */
.site-footer__trust {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 12px;
  padding: 18px;
  background: rgba(255, 255, 255, 0.04);
  border: 1px solid rgba(255, 255, 255, 0.08);
  border-radius: 8px;
}

@media (min-width: 768px) {
  .site-footer__trust {
    grid-template-columns: repeat(4, minmax(0, 1fr));
    padding: 22px;
  }
}

.site-footer__trust-tile {
  display: flex;
  align-items: flex-start;
  gap: 10px;
  padding: 4px;
}

.site-footer__trust-tile .material-symbols-outlined {
  color: var(--gold);
  flex-shrink: 0;
  margin-top: 2px;
}

.site-footer__trust-title {
  font-size: 13px;
  font-weight: 600;
  color: #fff;
  line-height: 1.3;
}

.site-footer__trust-meta {
  margin-top: 2px;
  font-size: 11px;
  font-weight: 500;
  color: rgba(247, 243, 236, 0.62);
  line-height: 1.4;
}

/* Main grid */
.site-footer__grid {
  display: grid;
  gap: clamp(28px, 3vw, 40px);
}

@media (min-width: 768px) {
  .site-footer__grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
}

@media (min-width: 1024px) {
  .site-footer__grid { grid-template-columns: 1.4fr 1fr 1fr 1.1fr; }
}

/* Brand column */
.site-footer__brand-mark {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.site-footer__logo {
  font-family: 'Cormorant Garamond', Georgia, serif;
  font-weight: 600;
  font-size: clamp(1.75rem, 1.4rem + 0.6vw, 2.125rem);
  color: var(--ivory);
  line-height: 1;
  letter-spacing: 0;
}

.site-footer__rule {
  display: block;
  width: 56px;
  height: 1px;
  background: rgba(184, 138, 68, 0.7);
}

.site-footer__lede {
  margin-top: 16px;
  max-width: 38ch;
  font-size: 14px;
  line-height: 1.7;
  color: rgba(247, 243, 236, 0.68);
}

.site-footer__social {
  margin-top: 20px;
  display: flex;
  align-items: center;
  gap: 10px;
}

.site-footer__social-link {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 40px;
  height: 40px;
  border-radius: 999px;
  border: 1px solid rgba(255, 255, 255, 0.10);
  background: rgba(255, 255, 255, 0.03);
  color: var(--gold);
  transition: border-color var(--motion-base), background-color var(--motion-base), color var(--motion-base);
}

.site-footer__social-link:hover {
  border-color: rgba(184, 138, 68, 0.55);
  background: rgba(184, 138, 68, 0.10);
  color: #fff;
}

/* Headings + lists */
.site-footer__heading {
  font-family: 'Montserrat', system-ui, sans-serif;
  font-size: 11px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.18em;
  color: #fff;
  margin-bottom: 18px;
}

.site-footer__list {
  display: grid;
  gap: 12px;
  font-size: 14px;
}

.site-footer__link {
  color: rgba(247, 243, 236, 0.66);
  transition: color var(--motion-base) var(--easing-standard);
}
.site-footer__link:hover { color: var(--ivory); }

/* Contact */
.site-footer__contact-list {
  display: grid;
  gap: 14px;
  font-size: 14px;
  color: rgba(247, 243, 236, 0.72);
}

.site-footer__contact-list li {
  display: flex;
  align-items: flex-start;
  gap: 10px;
}

.site-footer__contact-list .material-symbols-outlined {
  color: var(--gold);
  flex-shrink: 0;
  margin-top: 1px;
}

.site-footer__map-cta {
  margin-top: 20px;
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding: 10px 16px;
  border-radius: 8px;
  border: 1px solid rgba(184, 138, 68, 0.42);
  font-size: 12px;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.12em;
  color: var(--gold);
  transition: background-color var(--motion-base);
}

.site-footer__map-cta:hover { background: rgba(184, 138, 68, 0.10); }

/* Bottom legal */
.site-footer__bottom {
  display: flex;
  flex-direction: column;
  gap: 14px;
  padding-top: 22px;
  border-top: 1px solid rgba(255, 255, 255, 0.08);
  font-size: 11px;
  font-weight: 600;
  color: rgba(247, 243, 236, 0.45);
}

@media (min-width: 768px) {
  .site-footer__bottom {
    flex-direction: row;
    align-items: center;
    justify-content: space-between;
  }
}

.site-footer__legal {
  display: flex;
  flex-wrap: wrap;
  gap: 18px;
}
</style>
