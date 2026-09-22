const STATS = ['hp', 'atk', 'def', 'spa', 'spd', 'spe'];
const CATEGORIES = new Set(['Physical', 'Special', 'Status']);

const MIN_CATALOGUE = {
  species: 300,
  moves: 400,
  abilities: 150,
  items: 100,
  alignments: 20,
};

const MIN_REGULATION = {
  species: 250,
  moves: 400,
  abilities: 150,
  items: 100,
};

export class BaselineDriftError extends Error {}

class SchemaError extends Error {}

function fail(message) {
  throw new SchemaError(`[schema] ${message}`);
}

function uniqueSlugs(rows, label) {
  const seen = new Set();
  for (const row of rows) {
    if (typeof row.slug !== 'string' || row.slug.length === 0) fail(`${label}: slug vacio o no string`);
    if (row.slug !== row.slug.toLowerCase()) fail(`${label}: slug no normalizado "${row.slug}"`);
    if (seen.has(row.slug)) fail(`${label}: slug duplicado "${row.slug}"`);
    seen.add(row.slug);
  }
  return seen;
}

export function validateCatalogue({ species, moves, abilities, items, alignments }) {
  const sets = {};
  for (const [label, rows] of Object.entries({ species, moves, abilities, items, alignments })) {
    if (!Array.isArray(rows)) fail(`${label}: no es un array`);
    if (rows.length < MIN_CATALOGUE[label]) {
      fail(`${label}: solo ${rows.length} filas, se esperaban >= ${MIN_CATALOGUE[label]}. La fuente de Showdown probablemente ha cambiado`);
    }
    sets[label] = uniqueSlugs(rows, label);
  }

  for (const s of species) {
    if (typeof s.spriteId !== 'string' || s.spriteId.length === 0) {
      fail(`species ${s.slug}: spriteId vacio o ausente`);
    }
    if (!/^[a-z0-9-]+$/.test(s.spriteId)) {
      fail(`species ${s.slug}: spriteId con formato inesperado "${s.spriteId}"`);
    }
    if (!Array.isArray(s.types) || s.types.length < 1 || s.types.length > 2) {
      fail(`species ${s.slug}: types debe tener 1 o 2 elementos`);
    }
    for (const stat of STATS) {
      const value = s.baseStats?.[stat];
      if (!Number.isInteger(value) || value < 1 || value > 255) {
        fail(`species ${s.slug}: baseStats.${stat} invalido (${value})`);
      }
    }
    const abilityList = Object.values(s.abilities ?? {});
    if (abilityList.length === 0) fail(`species ${s.slug}: sin habilidades`);
    for (const ability of abilityList) {
      if (!sets.abilities.has(ability)) fail(`species ${s.slug}: habilidad desconocida "${ability}"`);
    }
    if (s.baseFormSlug && !sets.species.has(s.baseFormSlug)) {
      fail(`species ${s.slug}: baseFormSlug "${s.baseFormSlug}" no esta en el catalogo`);
    }
    if (s.requiredItemSlug && !sets.items.has(s.requiredItemSlug)) {
      fail(`species ${s.slug}: requiredItemSlug "${s.requiredItemSlug}" no esta en el catalogo`);
    }
  }

  for (const m of moves) {
    if (!CATEGORIES.has(m.category)) fail(`move ${m.slug}: category invalida "${m.category}"`);
    if (typeof m.target !== 'string' || m.target.length === 0) fail(`move ${m.slug}: target vacio`);
    if (m.category === 'Status' && m.power) fail(`move ${m.slug}: movimiento de estado con potencia`);
    if (!Number.isInteger(m.pp) || m.pp < 1) fail(`move ${m.slug}: pp invalido`);
  }

  for (const a of alignments) {
    const hasPlus = Boolean(a.plusStat);
    const hasMinus = Boolean(a.minusStat);
    if (hasPlus !== hasMinus) fail(`alignment ${a.slug}: plusStat y minusStat deben ir juntos`);
    if (hasPlus && !STATS.includes(a.plusStat)) fail(`alignment ${a.slug}: plusStat invalido "${a.plusStat}"`);
    if (hasMinus && !STATS.includes(a.minusStat)) fail(`alignment ${a.slug}: minusStat invalido "${a.minusStat}"`);
  }

  const neutral = alignments.filter((a) => !a.plusStat).length;
  if (neutral !== 5) fail(`alignments: se esperaban 5 neutras, hay ${neutral}`);

  return { ok: true };
}

export function validateRegulation({ regulation, legality, learnsets }, catalogueSlugs) {
  const code = regulation.code;

  for (const [label, slugs] of Object.entries(legality)) {
    if (!Array.isArray(slugs)) fail(`${code} legality.${label}: no es un array`);
    if (slugs.length < MIN_REGULATION[label]) {
      fail(`${code} legality.${label}: solo ${slugs.length} entradas, se esperaban >= ${MIN_REGULATION[label]}`);
    }
    for (const slug of slugs) {
      if (!catalogueSlugs[label].has(slug)) fail(`${code} legality.${label}: "${slug}" no esta en el catalogo`);
    }
  }

  const legalSpecies = new Set(legality.species);
  const legalMoves = new Set(legality.moves);

  if (!learnsets || typeof learnsets !== 'object') fail(`${code} learnsets: no es un objeto`);

  let empty = 0;
  for (const [slug, list] of Object.entries(learnsets)) {
    if (!legalSpecies.has(slug)) fail(`${code} learnsets: especie no legal "${slug}"`);
    if (!Array.isArray(list)) fail(`${code} learnsets ${slug}: no es un array`);
    if (list.length === 0) empty += 1;
    for (const move of list) {
      if (!legalMoves.has(move)) fail(`${code} learnsets ${slug}: movimiento no legal "${move}"`);
    }
  }

  const missing = [...legalSpecies].filter((slug) => !(slug in learnsets));
  if (missing.length > 0) fail(`${code} learnsets: faltan ${missing.length} especies legales (p.ej. ${missing[0]})`);
  if (empty > 0) fail(`${code} learnsets: ${empty} especies sin movimientos. La extraccion esta rota`);

  return { ok: true };
}

export function checkBaseline(observed, baseline) {
  if (!baseline) return { ok: true, firstRun: true };

  const drift = [];

  for (const [code, counts] of Object.entries(observed)) {
    const previous = baseline[code];

    if (!previous) {
      drift.push(`${code}: regulacion nueva, no estaba en la linea base`);
      continue;
    }

    for (const [key, value] of Object.entries(counts)) {
      if (previous[key] !== value) {
        drift.push(`${code}.${key}: ${previous[key]} -> ${value}`);
      }
    }
  }

  for (const code of Object.keys(baseline)) {
    if (!observed[code]) drift.push(`${code}: desaparecio de la config`);
  }

  if (drift.length === 0) return { ok: true, firstRun: false };

  throw new BaselineDriftError(
    [
      'Los datos han cambiado respecto a la linea base:',
      ...drift.map((line) => `  ${line}`),
      '',
      'Esto es NORMAL si ha entrado un parche o una regulacion nueva.',
      'Pero antes de aceptarlo, comprueba una cosa:',
      '',
      '  El mod base "champions" ES SIEMPRE la regulacion activa.',
      '  Cuando entra una regulacion nueva, la anterior se congela en su',
      '  propio mod (championsregmc, championsregmd...) y "champions" pasa',
      '  a ser la nueva. Si eso ha pasado y no has actualizado',
      '  ingest/config.mjs, estas a punto de guardar los datos de la',
      '  regulacion nueva etiquetados con el codigo de la vieja.',
      '',
      'Si el cambio es correcto, acepta la nueva linea base con:',
      '  node showdown-data.mjs --update-baseline',
    ].join(String.fromCharCode(10)),
  );
}
