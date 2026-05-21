// Audit button: identifikasi button yang kemungkinan icon-only (tanpa text content)
// dan tidak punya aria-label.
//
// Heuristik: button yang isinya hanya <span class="material-symbols-...">, atau
// SVG / image, atau kombinasi icon + class tanpa text node.

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

// Match <button ...>...</button> tags (greedy, multi-line)
const BUTTON_REGEX = /<button\b([\s\S]*?)>([\s\S]*?)<\/button>/g;

const findings = [];

for (const file of walk(SRC)) {
  const content = fs.readFileSync(file, 'utf8');
  const lines = content.split('\n');
  let m;
  const re = new RegExp(BUTTON_REGEX.source, 'g');
  while ((m = re.exec(content)) !== null) {
    const attrs = m[1];
    const inner = m[2];

    // Has aria-label / aria-labelledby?
    const hasAria = /(\s|^)(aria-label|aria-labelledby|:aria-label|:aria-labelledby)\s*=/.test(attrs);
    if (hasAria) continue;

    // Strip HTML tags from inner — kalau hasil trim hanya icon class, considered icon-only.
    const innerTextOnly = inner
      .replace(/<[^>]+>/g, '')
      .replace(/\{\{[^}]*\}\}/g, '') // strip Vue interpolation (could be text)
      .replace(/\s+/g, ' ')
      .trim();

    // Heuristic: kalau inner mengandung material-symbols atau svg/icon, 
    // dan trimmed innerTextOnly < 3 char (probably empty after stripping tags) → icon-only.
    const hasIconClass = /material-symbols|<svg\b|<i\s+class=|<icon/i.test(inner);

    if (hasIconClass && innerTextOnly.length < 3) {
      // Compute line number
      const idx = m.index;
      const lineNo = content.slice(0, idx).split('\n').length;
      findings.push({
        file: path.relative(SRC, file),
        line: lineNo,
        innerPreview: inner.slice(0, 80).replace(/\s+/g, ' ').trim(),
      });
    }
  }
}

console.log(`Icon-only buttons WITHOUT aria-label: ${findings.length}`);
console.log('---');
for (const f of findings.slice(0, 30)) {
  console.log(`  ${f.file}:${f.line}  ${f.innerPreview}`);
}
if (findings.length > 30) console.log(`  ... (${findings.length - 30} more)`);
