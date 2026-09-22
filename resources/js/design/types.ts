export const TYPE_COLORS: Record<string, string> = {
    Normal: '#9a9789',
    Fire: '#d1703f',
    Water: '#5f8cc4',
    Electric: '#cfae45',
    Grass: '#68a25c',
    Ice: '#77b4b7',
    Fighting: '#b3574a',
    Poison: '#976099',
    Ground: '#b3965c',
    Flying: '#8b99c8',
    Psychic: '#c46e84',
    Bug: '#8f9c47',
    Rock: '#a6986b',
    Ghost: '#75699b',
    Dragon: '#766abb',
    Dark: '#77675c',
    Steel: '#8896a0',
    Fairy: '#c085a6',
};

export function typeColor(type: string): string {
    return TYPE_COLORS[type] ?? '#78808f';
}

export const TYPE_ES: Record<string, string> = {
    Normal: 'Normal',
    Fire: 'Fuego',
    Water: 'Agua',
    Electric: 'Eléctrico',
    Grass: 'Planta',
    Ice: 'Hielo',
    Fighting: 'Lucha',
    Poison: 'Veneno',
    Ground: 'Tierra',
    Flying: 'Volador',
    Psychic: 'Psíquico',
    Bug: 'Bicho',
    Rock: 'Roca',
    Ghost: 'Fantasma',
    Dragon: 'Dragón',
    Dark: 'Siniestro',
    Steel: 'Acero',
    Fairy: 'Hada',
};

export const STATS = ['hp', 'atk', 'def', 'spa', 'spd', 'spe'] as const;

export type Stat = (typeof STATS)[number];

export const STAT_ES: Record<Stat, string> = {
    hp: 'PS',
    atk: 'Ataque',
    def: 'Defensa',
    spa: 'At. Esp.',
    spd: 'Def. Esp.',
    spe: 'Velocidad',
};

export const STAT_EN: Record<Stat, string> = {
    hp: 'HP',
    atk: 'Attack',
    def: 'Defense',
    spa: 'Sp. Atk',
    spd: 'Sp. Def',
    spe: 'Speed',
};
