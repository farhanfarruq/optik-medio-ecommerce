// One-off script (P1-13 Phase 3): replace console.log/error/warn/info → logger.*
// di semua file Vue + TS dalam medio-fe/src.
// Auto-inject `import { logger } from '../path/to/logger'` jika belum ada.
//
// Usage: node scripts/replace_console.mjs
// Aman dijalankan ulang (idempotent) — tidak duplicate import.

import fs from 'node:fs';
import path from 'node:path';

const SRC = '/home/farhan/Documents/VsCode Project/optik-medio-ecommerce/medio-fe/src';
const LOGGER_FILE = path.join(SRC, 'core/utils/logger.ts');

function relativeImport(fromFile) {
  const fromDir = path.dirname(fromFile);
  let rel = path.relative(fromDir, LOGGER_FILE).replace(/\\/g, '/');
  rel = rel.replace(/\.ts$/, '');
  if (!rel.startsWith('.')) rel = './' + rel;
  return rel;
}

function walk(dir) {
  const out = [];
  for (const ent of fs.readdirSync(dir, { withFileTypes: true })) {
    const full = path.join(dir, ent.name);
    if (ent.isDirectory()) {
      if (['node_modules', 'dist', '.vite'].includes(ent.name)) continue;
      out.push(...walk(full));
    } else if (/\.(ts|vue)$/.test(ent.name)) {
      out.push(full);
    }
  }
  return out;
}

const SKIP_FILES = new Set([LOGGER_FILE]);

const files = walk(SRC).filter((f) => !SKIP_FILES.has(f));

let totalEdited = 0;
let totalReplacements = 0;

for (const file of files) {
  const original = fs.readFileSync(file, 'utf8');
  let updated = original;

  const map = { log: 'debug', error: 'error', warn: 'warn', info: 'info' };
  let hits = 0;
  for (const [from, to] of Object.entries(map)) {
    const re = new RegExp(`\\bconsole\\.${from}\\(`, 'g');
    const matches = updated.match(re);
    if (matches) {
      hits += matches.length;
      updated = updated.replace(re, `logger.${to}(`);
    }
  }

  if (hits === 0) continue;

  const hasImport =
    /from\s+['"][^'"]*core\/utils\/logger['"]/.test(updated) ||
    /from\s+['"]@\/core\/utils\/logger['"]/.test(updated);

  if (!hasImport) {
    const importPath = relativeImport(file);
    const importLine = `import { logger } from '${importPath}';`;

    if (file.endsWith('.vue')) {
      const scriptOpen = updated.match(/<script[^>]*>\s*\n/);
      if (scriptOpen) {
        const idx = scriptOpen.index + scriptOpen[0].length;
        updated = updated.slice(0, idx) + importLine + '\n' + updated.slice(idx);
      } else {
        console.warn(`SKIP_INJECT: ${file} (no <script> tag)`);
      }
    } else {
      const lines = updated.split('\n');
      let insertAt = 0;
      let inBlockComment = false;
      for (let i = 0; i < lines.length; i++) {
        const l = lines[i].trim();
        if (l.startsWith('/*')) inBlockComment = true;
        if (inBlockComment) {
          if (l.endsWith('*/')) inBlockComment = false;
          continue;
        }
        if (l === '' || l.startsWith('//') || l.startsWith('*')) continue;
        if (l.startsWith('import ')) {
          let lastImportIdx = i;
          for (let j = i + 1; j < lines.length; j++) {
            const lj = lines[j].trim();
            if (lj.startsWith('import ')) lastImportIdx = j;
            else if (lj === '') continue;
            else break;
          }
          insertAt = lastImportIdx + 1;
        } else {
          insertAt = i;
        }
        break;
      }
      lines.splice(insertAt, 0, importLine);
      updated = lines.join('\n');
    }
  }

  fs.writeFileSync(file, updated, 'utf8');
  totalEdited++;
  totalReplacements += hits;
  console.log(`EDITED: ${path.relative(SRC, file)} (${hits} replacements)`);
}

console.log(`\nDONE. Edited ${totalEdited} files, ${totalReplacements} console.* → logger.* replacements.`);
