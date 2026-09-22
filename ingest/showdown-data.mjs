import { createRequire } from 'node:module';
import { existsSync } from 'node:fs';
import { mkdir, readFile, writeFile } from 'node:fs/promises';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';
import ps from 'pokemon-showdown';
import { REGULATIONS } from './config.mjs';
import { checkBaseline, validateCatalogue, validateRegulation } from './schema.mjs';

const { Dex } = ps;
const require = createRequire(import.meta.url);
const ROOT = join(dirname(fileURLToPath(import.meta.url)), '..');
const DATA_DIR = join(ROOT, 'data');
const TEXT_MOD = 'champions';
const BASELINE_PATH = join(dirname(fileURLToPath(import.meta.url)), '..', 'data', 'baseline.json');
const UPDATE_BASELINE = process.argv.includes('--update-baseline');
const LOCALES = ['es'];

const available = (entry) => entry.exists && entry.isNonstandard === null;
const buildable = (species) => available(species) && species.tier !== 'Illegal';

function loadText(kind, locale = null) {
  const path = locale
    ? `pokemon-showdown/dist/data/text/${locale}/${kind}.js`
    : `pokemon-showdown/dist/data/text/${kind}.js`;
  const mod = require(path);
  return mod[Object.keys(mod)[0]] ?? {};
}

const TEXT = {
  moves: loadText('moves'),
  abilities: loadText('abilities'),
  items: loadText('items'),
};

function describe(kind, slug) {
  const entry = TEXT[kind]?.[slug];
  if (!entry) return null;
  const override = entry[TEXT_MOD] ?? {};
  return override.shortDesc ?? entry.shortDesc ?? override.desc ?? entry.desc ?? null;
}

function mapSpecies(dex, species) {
  return {
    slug: species.id,
    name: species.name,
    spriteId: species.spriteid,
    nationalDex: species.num > 0 ? species.num : null,
    types: species.types,
    baseStats: {
      hp: species.baseStats.hp,
      atk: species.baseStats.atk,
      def: species.baseStats.def,
      spa: species.baseStats.spa,
      spd: species.baseStats.spd,
      spe: species.baseStats.spe,
    },
    abilities: Object.fromEntries(
      Object.entries(species.abilities).map(([slot, name]) => [slot, dex.abilities.get(name).id]),
    ),
    isMega: species.forme === 'Mega' || species.forme.startsWith('Mega-'),
    isBuildable: buildable(species),
    baseFormSlug:
      species.baseSpecies && species.baseSpecies !== species.name
        ? dex.species.get(species.baseSpecies).id
        : null,
    requiredItemSlug: species.requiredItem ? dex.items.get(species.requiredItem).id : null,
    weightKg: species.weightkg ?? null,
  };
}

function mapMove(move) {
  return {
    slug: move.id,
    name: move.name,
    type: move.type,
    category: move.category,
    power: move.basePower || null,
    accuracy: move.accuracy === true ? null : move.accuracy,
    pp: move.pp,
    priority: move.priority,
    target: move.target,
    flags: move.flags ?? {},
    secondary: move.secondary ?? null,
    description: describe('moves', move.id),
  };
}

function mapAbility(ability) {
  return {
    slug: ability.id,
    name: ability.name,
    description: describe('abilities', ability.id),
    effect: { rating: ability.rating ?? null },
  };
}

function mapMegaEvolutions(dex, megaStone) {
  if (!megaStone) return null;
  const pairs = typeof megaStone === 'string' ? { '': megaStone } : megaStone;
  const out = {};
  for (const [base, mega] of Object.entries(pairs)) {
    const baseSpecies = base ? dex.species.get(base) : null;
    const megaSpecies = dex.species.get(mega);
    if (!megaSpecies?.id) continue;
    out[baseSpecies?.id || megaSpecies.baseSpecies.toLowerCase()] = megaSpecies.id;
  }
  return Object.keys(out).length > 0 ? out : null;
}

function mapItem(dex, item) {
  return {
    slug: item.id,
    name: item.name,
    spriteNum: item.spritenum ?? null,
    description: describe('items', item.id),
    effect: {
      naturalGift: item.naturalGift ?? null,
      fling: item.fling ?? null,
      isBerry: Boolean(item.isBerry),
      isChoice: Boolean(item.isChoice),
    },
    isMegaStone: Boolean(item.megaStone),
    megaEvolutions: mapMegaEvolutions(dex, item.megaStone),
  };
}

function mapAlignment(nature) {
  return {
    slug: nature.id,
    name: nature.name,
    plusStat: nature.plus ?? null,
    minusStat: nature.minus ?? null,
  };
}

const EFECTO = { 0: 1, 1: 2, 2: 0.5, 3: 0 };

function mapTypes(dex) {
  const tipos = dex.types.all().filter((tipo) => tipo.exists && tipo.name !== '???' && tipo.name !== 'Stellar');
  const nombres = tipos.map((tipo) => tipo.name);

  return nombres.map((defensor) => ({
    slug: defensor.toLowerCase(),
    name: defensor,
    recibe: Object.fromEntries(
      nombres.map((atacante) => [
        atacante.toLowerCase(),
        EFECTO[dex.types.get(defensor).damageTaken[atacante] ?? 0] ?? 1,
      ]),
    ),
  }));
}

function collectLearnset(dex, slug, legalMoves) {
  const collected = new Set();
  const seen = new Set();
  let current = dex.species.get(slug);
  while (current?.exists && !seen.has(current.id)) {
    seen.add(current.id);
    const data = dex.species.getLearnsetData(current.id);
    for (const move of Object.keys(data?.learnset ?? {})) {
      if (legalMoves.has(move)) collected.add(move);
    }
    const parent = current.changesFrom || current.baseSpecies;
    current = parent && parent !== current.name ? dex.species.get(parent) : null;
  }
  return [...collected].sort();
}

async function writeJson(path, payload) {
  await mkdir(dirname(path), { recursive: true });
  await writeFile(path, `${JSON.stringify(payload, null, 2)}\n`, 'utf8');
}

function sortBySlug(rows) {
  return [...rows].sort((a, b) => a.slug.localeCompare(b.slug));
}

async function main() {
  const catalogue = { species: new Map(), moves: new Map(), abilities: new Map(), items: new Map(), alignments: new Map() };
  const perRegulation = [];

  for (const regulation of REGULATIONS) {
    const dex = Dex.mod(regulation.mod);

    const speciesList = dex.species.all().filter(available);
    const moveList = dex.moves.all().filter(available);
    const abilityList = dex.abilities.all().filter(available);
    const itemList = dex.items.all().filter(available);

    for (const species of speciesList) {
      catalogue.species.set(species.id, mapSpecies(dex, species));
      if (species.baseSpecies && species.baseSpecies !== species.name) {
        const base = dex.species.get(species.baseSpecies);
        if (base.exists && !catalogue.species.has(base.id)) {
          catalogue.species.set(base.id, { ...mapSpecies(dex, base), isBuildable: false });
        }
      }
    }
    for (const move of moveList) catalogue.moves.set(move.id, mapMove(move));
    for (const ability of abilityList) catalogue.abilities.set(ability.id, mapAbility(ability));
    for (const item of itemList) catalogue.items.set(item.id, mapItem(dex, item));

    const referencedAbilities = new Set();
    const referencedItems = new Set();
    for (const species of speciesList) {
      for (const name of Object.values(species.abilities)) referencedAbilities.add(dex.abilities.get(name).id);
      if (species.requiredItem) referencedItems.add(dex.items.get(species.requiredItem).id);
    }
    for (const slug of referencedAbilities) {
      if (!catalogue.abilities.has(slug)) catalogue.abilities.set(slug, mapAbility(dex.abilities.get(slug)));
    }
    for (const slug of referencedItems) {
      if (!catalogue.items.has(slug)) catalogue.items.set(slug, mapItem(dex, dex.items.get(slug)));
    }
    for (const nature of dex.natures.all()) {
      if (nature.exists) catalogue.alignments.set(nature.id, mapAlignment(nature));
    }

    const legalMoves = new Set(moveList.map((m) => m.id));
    const buildableSpecies = speciesList.filter(buildable);
    const legalAbilities = new Set(abilityList.map((a) => a.id));
    const legalItems = new Set(itemList.map((i) => i.id));
    for (const species of buildableSpecies) {
      for (const name of Object.values(species.abilities)) legalAbilities.add(dex.abilities.get(name).id);
      if (species.requiredItem) legalItems.add(dex.items.get(species.requiredItem).id);
    }
    const learnsets = {};
    for (const species of buildableSpecies) {
      learnsets[species.id] = collectLearnset(dex, species.id, legalMoves);
    }

    perRegulation.push({
      regulation,
      legality: {
        species: buildableSpecies.map((s) => s.id).sort(),
        moves: [...legalMoves].sort(),
        abilities: [...legalAbilities].sort(),
        items: [...legalItems].sort(),
      },
      learnsets,
    });
  }

  const species = sortBySlug([...catalogue.species.values()]);
  const moves = sortBySlug([...catalogue.moves.values()]);
  const abilities = sortBySlug([...catalogue.abilities.values()]);
  const items = sortBySlug([...catalogue.items.values()]);
  const alignments = sortBySlug([...catalogue.alignments.values()]);

  validateCatalogue({ species, moves, abilities, items, alignments });

  const catalogueSlugs = {
    species: new Set(species.map((s) => s.slug)),
    moves: new Set(moves.map((m) => m.slug)),
    abilities: new Set(abilities.map((a) => a.slug)),
    items: new Set(items.map((i) => i.slug)),
  };

  const observed = {};

  for (const entry of perRegulation) {
    validateRegulation(entry, catalogueSlugs);
    observed[entry.regulation.code] = {
      mod: entry.regulation.mod,
      species: entry.legality.species.length,
      moves: entry.legality.moves.length,
      abilities: entry.legality.abilities.length,
      items: entry.legality.items.length,
    };
  }

  const baseline = existsSync(BASELINE_PATH)
    ? JSON.parse(await readFile(BASELINE_PATH, 'utf8'))
    : null;

  if (UPDATE_BASELINE) {
    await writeJson(BASELINE_PATH, observed);
    console.log('Linea base actualizada.');
  } else {
    checkBaseline(observed, baseline);
    if (!baseline) {
      await writeJson(BASELINE_PATH, observed);
      console.log('Linea base creada.');
    }
  }

  await writeJson(join(DATA_DIR, 'species.json'), species);
  await writeJson(join(DATA_DIR, 'moves.json'), moves);
  await writeJson(join(DATA_DIR, 'abilities.json'), abilities);
  await writeJson(join(DATA_DIR, 'items.json'), items);
  await writeJson(join(DATA_DIR, 'alignments.json'), alignments);
  await writeJson(join(DATA_DIR, 'types.json'), mapTypes(Dex.mod(REGULATIONS[0].mod)));

  for (const locale of LOCALES) {
    const names = { species: {}, moves: {}, abilities: {}, items: {} };
    const sources = {
      species: [loadText('pokedex', locale), species],
      moves: [loadText('moves', locale), moves],
      abilities: [loadText('abilities', locale), abilities],
      items: [loadText('items', locale), items],
    };
    for (const [kind, [text, rows]] of Object.entries(sources)) {
      for (const row of rows) {
        const translated = text?.[row.slug]?.name;
        if (translated && translated !== row.name) names[kind][row.slug] = translated;
      }
    }
    await writeJson(join(DATA_DIR, `names.${locale}.json`), names);
  }

  await writeJson(
    join(DATA_DIR, 'regulations.json'),
    REGULATIONS.map(({ code, name, mod, isActive, startsAt, endsAt, formats }) => ({
      code,
      name,
      mod,
      isActive,
      startsAt,
      endsAt,
      formats,
    })),
  );

  for (const entry of perRegulation) {
    const dir = join(DATA_DIR, entry.regulation.code);
    await writeJson(join(dir, 'legality.json'), entry.legality);
    await writeJson(join(dir, 'learnsets.json'), entry.learnsets);
  }

  console.log(
    `catalogo  species=${species.length} (buildable=${species.filter((s) => s.isBuildable).length})  moves=${moves.length}  abilities=${abilities.length}  items=${items.length}  alignments=${alignments.length}`,
  );
  for (const entry of perRegulation) {
    const pairs = Object.values(entry.learnsets).reduce((sum, list) => sum + list.length, 0);
    console.log(
      `${entry.regulation.code.padEnd(5)} legal-species=${String(entry.legality.species.length).padStart(4)}  legal-moves=${String(entry.legality.moves.length).padStart(4)}  legal-items=${String(entry.legality.items.length).padStart(4)}  learnset-pairs=${pairs}`,
    );
  }
}

main().catch((error) => {
  console.error(String(error.message ?? error));
  process.exit(1);
});
