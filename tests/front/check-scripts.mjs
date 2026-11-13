// Cohérence entre public/index.html et public/js/ : chaque <script src> existe, et chaque
// fichier js/**/*.js est chargé exactement une fois.
import { existsSync, readFileSync, readdirSync, statSync } from 'node:fs';
import { join, relative } from 'node:path';

const root = 'public';

const html = readFileSync(join(root, 'index.html'), 'utf8');
const tags = [...html.matchAll(/<script\s+src="([^"]+)"/g)].map((m) => m[1]);
const errors = [];

for (const t of tags) {
  if (!existsSync(join(root, t))) errors.push(`${t} : référencé par index.html mais absent`);
}
const counts = new Map();
for (const t of tags) counts.set(t, (counts.get(t) ?? 0) + 1);
for (const [t, n] of counts) {
  if (n > 1) errors.push(`${t} : chargé ${n} fois`);
}

const walk = (dir) => readdirSync(dir).flatMap((e) => {
  const p = join(dir, e);
  return statSync(p).isDirectory() ? walk(p) : [p];
});
for (const p of walk(join(root, 'js')).map((f) => relative(root, f))) {
  if (p.endsWith('.js') && !counts.has(p)) {
    errors.push(`${p} : jamais chargé par index.html`);
  }
}

if (errors.length) {
  console.error(errors.join('\n'));
  process.exit(1);
}
console.log(`scripts OK : ${tags.length} balises <script src>`);
