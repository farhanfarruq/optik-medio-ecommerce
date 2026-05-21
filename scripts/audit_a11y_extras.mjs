// A11Y-3 audit lanjutan — beyond icon-only buttons:
// 1. Modal/Dialog: cari pattern fixed inset-0 z-XX bg-black tanpa role="dialog"
// 2. Form input tanpa <label> atau aria-label
// 3. Toggle button (state-changing) tanpa aria-pressed/aria-expanded

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

const issues = { modals: [], inputs: [], toggles: [] };

for (const file of walk(SRC)) {
  const content = fs.readFileSync(file, 'utf8');
  const rel = path.relative(SRC, file);

  // 1. Modal pattern: <div ...class="...fixed...inset-0...">
  // Heuristik: <div> dengan class mengandung 'fixed' + 'inset-0' (modal overlay)
  const modalRe = /<div\b([^>]*)>/g;
  let m;
  while ((m = modalRe.exec(content)) !== null) {
    const attrs = m[1];
    if (/class\s*=\s*["'][^"']*\bfixed\b[^"']*\binset-0\b[^"']*["']/.test(attrs) ||
        /:class\s*=\s*["'][^"']*\bfixed\b[^"']*\binset-0\b[^"']*["']/.test(attrs)) {
      // Check if this is a modal/overlay (skip if it's just a backdrop)
      // Modal harus punya role="dialog" + aria-modal="true"
      const hasRole = /\brole\s*=\s*["']dialog["']/.test(attrs);
      const hasAriaModal = /aria-modal\s*=\s*["']true["']/.test(attrs);

      if (!hasRole && !hasAriaModal) {
        // Cek di sekitar (5 lines before/after) — kalau ada nested div dengan class
        // 'rounded' atau 'card' atau 'modal' → kemungkinan ini modal container
        const idx = m.index;
        const lineNo = content.slice(0, idx).split('\n').length;
        const surrounding = content.slice(Math.max(0, idx - 200), idx + 500);
        const looksLikeModal = /\b(modal|dialog|panel)\b/i.test(surrounding) ||
                              /rounded[^"']*shadow/.test(surrounding);
        if (looksLikeModal) {
          issues.modals.push({ file: rel, line: lineNo });
        }
      }
    }
  }
}

console.log(`\n=== A11Y-3 Audit Lanjutan ===\n`);
console.log(`Modal/dialog tanpa role="dialog" + aria-modal: ${issues.modals.length}`);
issues.modals.slice(0, 20).forEach((i) => console.log(`  ${i.file}:${i.line}`));
if (issues.modals.length > 20) console.log(`  ... (${issues.modals.length - 20} more)`);
