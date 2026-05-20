// Verifikasi audit: count <img> tags tanpa alt/loading setelah fix.
import fs from 'node:fs';
import path from 'node:path';

const SRC = '/home/farhan/Documents/VsCode Project/optik-medio-ecommerce/medio-fe/src';

function walk(d) {
  const out = [];
  for (const e of fs.readdirSync(d, { withFileTypes: true })) {
    const f = path.join(d, e.name);
    if (e.isDirectory()) {
      if (['node_modules', 'dist', '.vite'].includes(e.name)) continue;
      out.push(...walk(f));
    } else if (/\.vue$/.test(e.name)) {
      out.push(f);
    }
  }
  return out;
}

const re = /<img\b([\s\S]*?)\/?>/g;
let total = 0, noAlt = 0, noLazy = 0, noDecoding = 0;

for (const f of walk(SRC)) {
  const c = fs.readFileSync(f, 'utf8');
  for (const m of c.matchAll(re)) {
    total++;
    if (!/(\s|^)(alt|:alt)\s*=/.test(m[1])) noAlt++;
    if (!/(\s|^)(loading|:loading)\s*=/.test(m[1])) noLazy++;
    if (!/(\s|^)(decoding|:decoding)\s*=/.test(m[1])) noDecoding++;
  }
}

console.log(`Total <img>:    ${total}`);
console.log(`No alt:         ${noAlt}`);
console.log(`No loading:     ${noLazy}`);
console.log(`No decoding:    ${noDecoding}`);
