// SEO-2 (Phase 6): Audit useSeoMeta coverage di views/.
//
// List view yang sudah pakai useSeoMeta vs yang belum, sebagai panduan
// untuk gradual SEO meta extraction.

import fs from 'node:fs';
import path from 'node:path';

const VIEWS_DIR = path.resolve('src/views');

function walk(d) {
  const out = [];
  for (const e of fs.readdirSync(d, { withFileTypes: true })) {
    const f = path.join(d, e.name);
    if (e.isDirectory()) {
      out.push(...walk(f));
    } else if (/\.vue$/.test(e.name)) {
      out.push(f);
    }
  }
  return out;
}

const using = [];
const missing = [];

for (const file of walk(VIEWS_DIR)) {
  const content = fs.readFileSync(file, 'utf8');
  const rel = path.relative(VIEWS_DIR, file);
  if (/useSeoMeta/.test(content)) {
    using.push(rel);
  } else {
    missing.push(rel);
  }
}

console.log(`\n=== SEO Meta Coverage Audit ===\n`);
console.log(`Total view files: ${using.length + missing.length}`);
console.log(`Using useSeoMeta: ${using.length} (${((using.length / (using.length + missing.length)) * 100).toFixed(0)}%)`);
console.log(`Missing useSeoMeta: ${missing.length}`);
console.log('');
console.log('--- Sudah pakai (good) ---');
using.forEach((f) => console.log(`  ✅ ${f}`));
console.log('');
console.log('--- Belum pakai (action needed for SEO/Open Graph) ---');
missing.forEach((f) => console.log(`  ⚠️  ${f}`));
console.log('');
console.log('Tip: setiap public route (yang di-Allow di robots.txt) WAJIB pakai useSeoMeta.');
console.log('Authenticated route (Profile, Cart, dst) opsional karena di-Disallow.');
