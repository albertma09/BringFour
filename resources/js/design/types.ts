export const TYPE_COLORS: Record<string, string> = {
    Normal: '#8d8a7e',
    Fire: '#c2633a',
    Water: '#5680b0',
    Electric: '#c0a13f',
    Grass: '#5f9455',
    Ice: '#6ea5a8',
    Fighting: '#a44f43',
    Poison: '#8a5591',
    Ground: '#a58a54',
    Flying: '#7f8cb8',
    Psychic: '#b5637a',
    Bug: '#849141',
    Rock: '#9a8d63',
    Ghost: '#6a5f8e',
    Dragon: '#6b60ad',
    Dark: '#6b5c52',
    Steel: '#7d8a93',
    Fairy: '#b07a9a',
};

export function typeColor(type: string): string {
    return TYPE_COLORS[type] ?? '#6b7078';
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
