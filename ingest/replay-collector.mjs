import { createReadStream, createWriteStream, existsSync } from 'node:fs';
import { appendFile, mkdir, readFile, readdir, rm, writeFile } from 'node:fs/promises';
import { dirname, join } from 'node:path';
import { pipeline } from 'node:stream/promises';
import { fileURLToPath } from 'node:url';
import { createGzip } from 'node:zlib';
import { REGULATIONS, USER_AGENT } from './config.mjs';

const ROOT = join(dirname(fileURLToPath(import.meta.url)), '..');
const RAW_DIR = process.env.BF_RAW_DIR
  ? join(process.env.BF_RAW_DIR, 'data', 'raw', 'replays')
  : join(ROOT, 'data', 'raw', 'replays');
const SEARCH_URL = 'https://replay.pokemonshowdown.com/search.json';
const PAGE_SIZE = 51;
const REQUEST_DELAY_MS = 350;
const RETRY_DELAYS_MS = [1000, 4000, 16000];

const MAX_PAGES = Number(process.env.BF_MAX_PAGES ?? 40);
const BACKFILL = process.env.BF_BACKFILL === '1';

const sleep = (ms) => new Promise((resolve) => setTimeout(resolve, ms));

async function fetchJson(url) {
  let lastError = null;

  for (let attempt = 0; attempt < RETRY_DELAYS_MS.length + 1; attempt += 1) {
    if (attempt > 0) await sleep(RETRY_DELAYS_MS[attempt - 1]);

    try {
      const response = await fetch(url, { headers: { 'User-Agent': USER_AGENT } });
      if (response.ok) return response.json();
      if (response.status === 404) return null;
      lastError = new Error(`${response.status} ${response.statusText} en ${url}`);
    } catch (error) {
      lastError = new Error(`${error.message ?? error} en ${url}`);
    }
  }

  throw lastError;
}

async function loadState() {
  const path = join(RAW_DIR, 'state.json');
  if (!existsSync(path)) return {};
  return JSON.parse(await readFile(path, 'utf8'));
}

async function saveState(state) {
  await mkdir(RAW_DIR, { recursive: true });
  await writeFile(join(RAW_DIR, 'state.json'), `${JSON.stringify(state, null, 2)}\n`, 'utf8');
}

async function loadSeenIds(formatId) {
  const path = join(RAW_DIR, formatId, 'seen.txt');
  if (!existsSync(path)) return new Set();
  const content = await readFile(path, 'utf8');
  return new Set(content.split('\n').filter(Boolean));
}

function dayOf(uploadtime) {
  return new Date(uploadtime * 1000).toISOString().slice(0, 10);
}

async function listNewReplays(formatId, since) {
  const found = [];
  let before = null;

  for (let page = 0; page < MAX_PAGES; page += 1) {
    const url = before ? `${SEARCH_URL}?format=${formatId}&before=${before}` : `${SEARCH_URL}?format=${formatId}`;
    const rows = await fetchJson(url);
    if (!Array.isArray(rows) || rows.length === 0) break;

    let reachedKnown = false;
    for (const row of rows) {
      if (!BACKFILL && since && row.uploadtime <= since) {
        reachedKnown = true;
        continue;
      }
      found.push(row);
    }

    if (reachedKnown && !BACKFILL) break;
    if (rows.length < PAGE_SIZE) break;

    before = rows[rows.length - 1].uploadtime;
    await sleep(REQUEST_DELAY_MS);
  }

  return found;
}

async function compressFinishedDays(formatDir, today) {
  if (!existsSync(formatDir)) return;
  for (const entry of await readdir(formatDir)) {
    if (!entry.endsWith('.jsonl') || entry.startsWith(today)) continue;
    const source = join(formatDir, entry);
    await pipeline(createReadStream(source), createGzip({ level: 9 }), createWriteStream(`${source}.gz`));
    await rm(source);
  }
}

async function collectFormat(regulation, format, state) {
  const formatId = format.showdownId;
  const formatDir = join(RAW_DIR, formatId);
  await mkdir(formatDir, { recursive: true });

  const seen = await loadSeenIds(formatId);
  const since = state[formatId]?.lastUploadtime ?? null;
  const candidates = (await listNewReplays(formatId, since)).filter((row) => !seen.has(row.id));

  let stored = 0;
  let maxUploadtime = since ?? 0;
  const buffers = new Map();
  const newIds = [];

  for (const row of candidates) {
    const replay = await fetchJson(`https://replay.pokemonshowdown.com/${row.id}.json`);
    await sleep(REQUEST_DELAY_MS);
    if (!replay?.log) continue;

    const record = {
      id: replay.id,
      formatId,
      regulation: regulation.code,
      uploadtime: replay.uploadtime,
      players: replay.players,
      rating: replay.rating ?? null,
      log: replay.log,
    };

    const day = dayOf(replay.uploadtime);
    if (!buffers.has(day)) buffers.set(day, []);
    buffers.get(day).push(JSON.stringify(record));

    newIds.push(replay.id);
    maxUploadtime = Math.max(maxUploadtime, replay.uploadtime);
    stored += 1;
  }

  for (const [day, lines] of buffers) {
    await appendFile(join(formatDir, `${day}.jsonl`), `${lines.join('\n')}\n`, 'utf8');
  }
  if (newIds.length > 0) {
    await appendFile(join(formatDir, 'seen.txt'), `${newIds.join('\n')}\n`, 'utf8');
  }

  await compressFinishedDays(formatDir, new Date().toISOString().slice(0, 10));

  state[formatId] = {
    lastUploadtime: maxUploadtime || since || 0,
    lastRunAt: new Date().toISOString(),
    totalSeen: seen.size + newIds.length,
  };

  return { formatId, candidates: candidates.length, stored };
}

async function main() {
  const state = await loadState();
  const results = [];
  const failures = [];

  for (const regulation of REGULATIONS) {
    for (const format of regulation.formats) {
      if (!format.collectReplays) continue;

      try {
        results.push(await collectFormat(regulation, format, state));
      } catch (error) {
        failures.push({ formatId: format.showdownId, message: String(error.message ?? error) });
      }

      await saveState(state);
    }
  }

  for (const row of results) {
    console.log(`${row.formatId.padEnd(32)} nuevos=${String(row.candidates).padStart(4)} guardados=${String(row.stored).padStart(4)}`);
  }

  for (const row of failures) {
    console.error(`${row.formatId.padEnd(32)} FALLO: ${row.message}`);
  }

  if (failures.length > 0) {
    throw new Error(`${failures.length} formato(s) sin recolectar; el estado de los demas si se ha guardado`);
  }
}

main().catch((error) => {
  console.error(String(error.message ?? error));
  process.exit(1);
});
