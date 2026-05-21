// Phase 4 (A11Y-1, A11Y-2, PERF-2 helper):
// - Tambah `alt=""` (decorative fallback) untuk <img> tanpa alt sama sekali
// - Tambah `loading="lazy"` ke <img> yang belum punya
// - Tambah `decoding="async"` ke <img> yang belum punya
//
// Strategy: parse <img ...> dengan regex multi-line, periksa attributes,
// tambah yang missing tanpa overwrite yang sudah ada.
//
// Usage: node scripts/fix_image_a11y.mjs

import fs from 'node:fs';
import path from 'node:path';

const SRC = '/home/farhan/Documents/VsCode Project/optik-medio-ecommerce/medio-fe/src';

function walk(dir) {
  const out = [];
  for (const ent of fs.readdirSync(dir, { withFileTypes: true })) {
    const full = path.join(dir, ent.name);
    if (ent.isDirectory()) {
      if (['node_modules', 'dist', '.vite'].includes(ent.name)) continue;
      out.push(...walk(full));
    } else if (/\.vue$/.test(ent.name)) {
      out.push(full);
    }
  }
  return out;
}

// Match <img ...> tags (single or multi-line, opening or self-closing).
// Captures the full tag content (without leading <img and trailing /> or >).
const IMG_REGEX = /<img\b([\s\S]*?)(\/?)>/g;

let totalFiles = 0;
let totalAltAdded = 0;
let totalLazyAdded = 0;
let totalDecodingAdded = 0;

for (const file of walk(SRC)) {
  const original = fs.readFileSync(file, 'utf8');
  let edited = original;
  let altAdded = 0;
  let lazyAdded = 0;
  let decodingAdded = 0;

  edited = edited.replace(IMG_REGEX, (full, attrs, selfClose) => {
    let newAttrs = attrs;
    let changed = false;

    // 1. alt attribute
    if (!/(\s|^)alt\s*=/.test(newAttrs) && !/(\s|^):alt\s*=/.test(newAttrs)) {
      // Image tanpa alt sama sekali — tambah alt="" (decorative fallback).
      // Developer harus replace dengan alt yang descriptive saat refactor
      // sub-component (P1-9..P1-12).
      newAttrs = ' alt=""' + newAttrs;
      altAdded++;
      changed = true;
    }

    // 2. loading="lazy"
    if (!/(\s|^)loading\s*=/.test(newAttrs) && !/(\s|^):loading\s*=/.test(newAttrs)) {
      newAttrs = newAttrs.replace(/\s*$/, '') + ' loading="lazy"';
      lazyAdded++;
      changed = true;
    }

    // 3. decoding="async"
    if (!/(\s|^)decoding\s*=/.test(newAttrs) && !/(\s|^):decoding\s*=/.test(newAttrs)) {
      newAttrs = newAttrs.replace(/\s*$/, '') + ' decoding="async"';
      decodingAdded++;
      changed = true;
    }

    if (!changed) return full;
    return `<img${newAttrs}${selfClose ? ' />' : '>'}`;
  });

  if (altAdded || lazyAdded || decodingAdded) {
    fs.writeFileSync(file, edited, 'utf8');
    totalFiles++;
    totalAltAdded += altAdded;
    totalLazyAdded += lazyAdded;
    totalDecodingAdded += decodingAdded;
    console.log(`EDITED: ${path.relative(SRC, file)} (alt+${altAdded}, lazy+${lazyAdded}, decoding+${decodingAdded})`);
  }
}

console.log(`\nDONE.`);
console.log(`Files edited: ${totalFiles}`);
console.log(`Alt attribute added: ${totalAltAdded}`);
console.log(`loading="lazy" added: ${totalLazyAdded}`);
console.log(`decoding="async" added: ${totalDecodingAdded}`);
