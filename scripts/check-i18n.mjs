import { readFileSync, readdirSync, statSync } from 'node:fs';
import { join } from 'node:path';

function ficheros(dir) {
  return readdirSync(dir).flatMap((e) => {
    const p = join(dir, e);
    return statSync(p).isDirectory() ? ficheros(p) : [p];
  });
}

function aplanar(obj, prefijo = '') {
  return Object.entries(obj).flatMap(([k, v]) =>
    typeof v === 'object' && v !== null ? aplanar(v, `${prefijo}${k}.`) : [`${prefijo}${k}`],
  );
}

const cargar = (ruta) => {
  const texto = readFileSync(ruta, 'utf8').replace(/^export default /, '').replace(/;\s*$/, '');
  return eval(`(${texto})`);
};

const es = new Set(aplanar(cargar('resources/js/i18n/es.ts')));
const en = new Set(aplanar(cargar('resources/js/i18n/en.ts')));

const usadas = new Set();
const dinamicas = [];

for (const f of ficheros('resources/js').filter((f) => f.endsWith('.vue') || f.endsWith('.ts'))) {
  const src = readFileSync(f, 'utf8');
  for (const m of src.matchAll(/\$t\('([^']+)'/g)) usadas.add(m[1]);
  for (const m of src.matchAll(/\$t\(`([^`]+)`/g)) dinamicas.push(`${f}: ${m[1]}`);
}

const faltanEs = [...usadas].filter((k) => !es.has(k));
const faltanEn = [...usadas].filter((k) => !en.has(k));
const soloEs = [...es].filter((k) => !en.has(k));
const soloEn = [...en].filter((k) => !es.has(k));

console.log(`claves usadas: ${usadas.size}  es: ${es.size}  en: ${en.size}`);
if (faltanEs.length) console.log('FALTAN EN ES:', faltanEs);
if (faltanEn.length) console.log('FALTAN EN EN:', faltanEn);
if (soloEs.length) console.log('solo en es:', soloEs);
if (soloEn.length) console.log('solo en en:', soloEn);
console.log('dinamicas a revisar a mano:');
for (const d of dinamicas) console.log('  ', d);
if (faltanEs.length || faltanEn.length || soloEs.length || soloEn.length) process.exit(1);
