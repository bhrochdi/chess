// Tests unitaires du front : charge les scripts classiques dans un bac à sable et lance chaque *.test.js.
import { readFileSync, readdirSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';
import vm from 'node:vm';

const here = dirname(fileURLToPath(import.meta.url));
const root = join(here, '..', '..', '..');
const sources = ['public/js/core/fen.js', 'public/js/core/game-state.js'];

let failed = 0;
let passed = 0;
for (const file of readdirSync(here).filter((f) => f.endsWith('.test.js')).sort()) {
  const context = vm.createContext({ console });
  for (const src of sources) vm.runInContext(readFileSync(join(root, src), 'utf8'), context, { filename: src });
  const tests = [];
  context.test = (name, fn) => tests.push([name, fn]);
  context.assertEqual = (actual, expected, label) => {
    const a = JSON.stringify(actual);
    const e = JSON.stringify(expected);
    if (a !== e) throw new Error((label ? label + ' : ' : '') + 'attendu ' + e + ', obtenu ' + a);
  };
  vm.runInContext(readFileSync(join(here, file), 'utf8'), context, { filename: file });
  for (const [name, fn] of tests) {
    try {
      fn();
      passed++;
    } catch (e) {
      failed++;
      console.error('ÉCHEC ' + file + ' › ' + name + ' : ' + e.message);
    }
  }
}
console.log(failed === 0 ? 'TOUS LES TESTS PASSENT (' + passed + ')' : failed + ' test(s) en échec sur ' + (passed + failed));
process.exit(failed === 0 ? 0 : 1);
