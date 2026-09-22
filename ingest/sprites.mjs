import { existsSync } from 'node:fs';
import { mkdir, readFile, writeFile } from 'node:fs/promises';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';
import { USER_AGENT } from './config.mjs';

const ROOT = join(dirname(fileURLToPath(import.meta.url)), '..');
const DATA_DIR = join(ROOT, 'data');
const OUT_DIR = join(ROOT, 'public', 'sprites', 'pokemon');
const BASE_URL = 'https://play.pokemonshowdown.com/sprites/gen5';
const SHEET_URL = 'https://play.pokemonshowdown.com/sprites/itemicons-sheet.png';
const SHEET_COLUMNS = 16;
const SHEET_ICON_PX = 24;
const DELAY_MS = 120;
const RETRY_DELAYS_MS = [1000, 4000];

const sleep = (ms) => new Promise((resolve) => setTimeout(resolve, ms));

async function readJson(name) {
  return JSON.parse(await readFile(join(DATA_DIR, name), 'utf8'));
}

async function download(url) {
  let lastError = null;

  for (let attempt = 0; attempt < RETRY_DELAYS_MS.length + 1; attempt += 1) {
    if (attempt > 0) await sleep(RETRY_DELAYS_MS[attempt - 1]);

    try {
      const response = await fetch(url, { headers: { 'User-Agent': USER_AGENT } });
      if (response.status === 404) return null;
      if (response.ok) return Buffer.from(await response.arrayBuffer());
      lastError = new Error(`${response.status} ${response.statusText} en ${url}`);
    } catch (error) {
      lastError = new Error(`${error.message ?? error} en ${url}`);
    }
  }

  throw lastError;
}

function megaStoneBySpecies(items) {
  const out = new Map();

  for (const item of items) {
    for (const [base, mega] of Object.entries(item.megaEvolutions ?? {})) {
      out.set(mega, { base, stone: item.slug });
    }
  }

  return out;
}

async function downloadItemSheet(items, map) {
  const necesarias = new Set(Object.values(map).map((entry) => entry.stone).filter(Boolean));

  if (necesarias.size === 0) {
    return;
  }

  const dir = join(ROOT, 'public', 'sprites', 'items');
  await mkdir(dir, { recursive: true });

  const target = join(dir, 'itemicons-sheet.png');

  if (!existsSync(target)) {
    const bytes = await download(SHEET_URL);

    if (bytes === null) {
      throw new Error(`No se pudo bajar la hoja de iconos: ${SHEET_URL}`);
    }

    await writeFile(target, bytes);
  }

  const offsets = {};

  for (const item of items) {
    if (!necesarias.has(item.slug)) {
      continue;
    }

    if (item.spriteNum === null || item.spriteNum === undefined) {
      throw new Error(`La megapiedra ${item.slug} no tiene spriteNum`);
    }

    offsets[item.slug] = {
      x: -(item.spriteNum % SHEET_COLUMNS) * SHEET_ICON_PX,
      y: -Math.floor(item.spriteNum / SHEET_COLUMNS) * SHEET_ICON_PX,
    };
  }

  const faltan = [...necesarias].filter((slug) => !offsets[slug]);

  if (faltan.length > 0) {
    throw new Error(`Megapiedras sin icono: ${faltan.join(', ')}`);
  }

  await writeFile(join(dir, 'icons.json'), `${JSON.stringify(offsets, null, 2)}\n`, 'utf8');
}

async function main() {
  const species = await readJson('species.json');
  const items = await readJson('items.json');
  const stones = megaStoneBySpecies(items);

  await mkdir(OUT_DIR, { recursive: true });

  const map = {};
  const missing = [];
  let descargados = 0;
  let cacheados = 0;

  for (const row of species) {
    const file = `${row.slug}.png`;
    const target = join(OUT_DIR, file);

    if (existsSync(target)) {
      map[row.slug] = { sprite: file };
      cacheados += 1;
      continue;
    }

    const bytes = await download(`${BASE_URL}/${row.spriteId}.png`);
    await sleep(DELAY_MS);

    if (bytes === null) {
      missing.push(row.slug);
      continue;
    }

    await writeFile(target, bytes);
    map[row.slug] = { sprite: file };
    descargados += 1;
  }

  const sinSolucion = [];

  for (const slug of missing) {
    const fallback = stones.get(slug);

    if (!fallback || !map[fallback.base]) {
      sinSolucion.push(slug);
      continue;
    }

    map[slug] = { sprite: map[fallback.base].sprite, stone: fallback.stone };
  }

  if (sinSolucion.length > 0) {
    throw new Error(`Sin sprite ni recambio: ${sinSolucion.join(', ')}`);
  }

  await downloadItemSheet(items, map);

  await writeFile(join(DATA_DIR, 'sprites.json'), `${JSON.stringify(map, null, 2)}\n`, 'utf8');

  console.log(`sprites  descargados=${descargados}  ya estaban=${cacheados}  por megapiedra=${missing.length}`);
}

main().catch((error) => {
  console.error(String(error.message ?? error));
  process.exit(1);
});
