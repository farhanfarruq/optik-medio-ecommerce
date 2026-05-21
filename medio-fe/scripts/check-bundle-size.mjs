// OBS-4 (Phase 6): Performance budget enforcement.
//
// Walk dist/assets/ dan fail kalau ada chunk yang melebihi budget.
// Run setelah `npm run build`. Exit code 1 kalau over budget — bisa di-wire
// ke CI agar block PR.
//
// Usage: node scripts/check-bundle-size.mjs

import fs from 'node:fs';
import path from 'node:path';
import zlib from 'node:zlib';

const DIST_ASSETS = path.resolve('dist/assets');

// Budget (in KB, gzip-compressed) — angka konservatif berdasarkan baseline
// build setelah Phase 4. Naikkan kalau dependency tambahan benar-benar perlu.
const BUDGETS = [
  { pattern: /^index-.*\.js$/, maxKbGzip: 50, description: 'main app entry' },
  { pattern: /^vendor-vue-.*\.js$/, maxKbGzip: 60, description: 'Vue + Router + Pinia' },
  { pattern: /^vendor-utils-.*\.js$/, maxKbGzip: 35, description: 'axios + dompurify + vueuse' },
  { pattern: /^Profile-.*\.js$/, maxKbGzip: 25, description: 'Profile route chunk' },
  { pattern: /^CheckoutView-.*\.js$/, maxKbGzip: 20, description: 'Checkout route chunk' },
  { pattern: /^ProductDetail-.*\.js$/, maxKbGzip: 20, description: 'ProductDetail route chunk' },
];

if (!fs.existsSync(DIST_ASSETS)) {
  console.error('❌ dist/assets/ tidak ditemukan. Jalankan `npm run build` dulu.');
  process.exit(1);
}

const files = fs.readdirSync(DIST_ASSETS).filter((f) => f.endsWith('.js'));

let failed = 0;
let checked = 0;

for (const budget of BUDGETS) {
  const matches = files.filter((f) => budget.pattern.test(f));
  if (matches.length === 0) {
    console.warn(`⚠️  No file matches ${budget.pattern} (${budget.description})`);
    continue;
  }

  for (const file of matches) {
    const full = path.join(DIST_ASSETS, file);
    const raw = fs.readFileSync(full);
    const gzipped = zlib.gzipSync(raw);
    const kbGzip = gzipped.length / 1024;
    const kbRaw = raw.length / 1024;
    checked++;

    const ok = kbGzip <= budget.maxKbGzip;
    const icon = ok ? '✅' : '❌';
    console.log(
      `${icon} ${file.padEnd(40)} ` +
        `gzip ${kbGzip.toFixed(1).padStart(6)} KB ` +
        `(raw ${kbRaw.toFixed(1).padStart(6)} KB) ` +
        `[budget: ${budget.maxKbGzip} KB] — ${budget.description}`
    );

    if (!ok) failed++;
  }
}

console.log('');
if (failed > 0) {
  console.error(`❌ ${failed} chunk(s) melebihi budget. Kurangi size atau update BUDGETS di scripts/check-bundle-size.mjs.`);
  process.exit(1);
}
console.log(`✅ Semua ${checked} chunk dalam budget.`);
