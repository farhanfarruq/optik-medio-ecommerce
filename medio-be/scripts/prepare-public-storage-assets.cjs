const fs = require('fs');
const path = require('path');

const sourceDir = path.resolve(__dirname, '../public/images/foto_produk');
const targetDir = path.resolve(__dirname, '../storage/app/public/products/foto_produk');

function slugify(filename) {
  const ext = path.extname(filename);
  const name = path.basename(filename, ext)
    .replace(/\+(\d)\.(\d{2})/g, ' plus-$1$2')
    .replace(/\+/g, ' plus ')
    .normalize('NFKD')
    .replace(/[\u0300-\u036f]/g, '')
    .toLowerCase()
    .replace(/[^a-z0-9]+/g, '-')
    .replace(/^-+|-+$/g, '');

  return `${name}${ext.toLowerCase()}`;
}

if (!fs.existsSync(sourceDir)) {
  process.exit(0);
}

fs.mkdirSync(targetDir, { recursive: true });

for (const file of fs.readdirSync(sourceDir)) {
  const source = path.join(sourceDir, file);

  if (!fs.statSync(source).isFile()) {
    continue;
  }

  const ext = path.extname(file).toLowerCase();

  if (!['.jpg', '.jpeg', '.png', '.webp'].includes(ext)) {
    continue;
  }

  const slugName = slugify(file);
  const seedName = slugName.replace(ext, `-seed${ext}`);

  for (const targetName of new Set([file, slugName, seedName])) {
    fs.copyFileSync(source, path.join(targetDir, targetName));
  }
}
