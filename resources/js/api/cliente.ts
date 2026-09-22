export interface Muestra {
    total?: number;
    completos?: number;
    descartados?: number;
    enfrentados?: number;
    ocultas?: number;
    n?: number;
    elo_bucket: number;
    min_sample: number;
    fuente: string;
}

export interface Especie {
    slug: string;
    name: string;
    name_es: string | null;
    types: string[];
    base_stats?: Record<string, number>;
    sprite: string | null;
    sprite_stone: string | null;
    is_mega?: boolean;
}

export interface Habilidad {
    slug: string;
    name: string;
    name_es: string | null;
}

export interface FichaEspecie extends Especie {
    abilities: Habilidad[];
    legal: boolean;
    national_dex: number | null;
    megapiedra: { slug: string; name: string; name_es: string | null } | null;
}

export interface FilaBringRate extends Especie {
    llevado: number;
    usage_pct: number;
    n: number;
    traido: number;
    bring_pct: number;
    lead_pct: number;
}

export interface FilaMatchup {
    slug: string;
    name: string;
    name_es: string | null;
    n: number;
    n_sin: number;
    bring_pct: number;
    bring_pct_sin: number;
    delta: number;
    significativo: boolean;
}

export interface ContextoConducta {
    rival: string;
    rival_name: string;
    rival_name_es: string | null;
    fase: string;
    n: number;
}

export interface AccionConducta {
    action_key: string;
    tipo: 'move' | 'switch';
    move_name: string | null;
    move_name_es: string | null;
    move_type: string | null;
    n: number;
    pct: number;
    intervalo: [number, number];
}

export interface Core {
    n: number;
    miembros: Especie[];
}

export interface EquipoResumen {
    id: string;
    n: number;
    miembros: Especie[];
}

export interface MiembroEquipo extends Especie {
    n: number;
    bring_pct: number;
    lead_pct: number;
}

export interface EquipoDetalle {
    id: string;
    n: number;
    completos: number;
    miembros: MiembroEquipo[];
}

export interface Formato {
    showdown_id: string;
    slug: string;
    battle_type: string;
    bring_count: number | null;
    regulacion: string;
    desde: string | null;
    hasta: string | null;
    cortes: Record<string, number>;
}

export interface Alineamiento {
    slug: string;
    name: string;
    plus: string | null;
    minus: string | null;
    neutral: boolean;
}

export interface Movimiento {
    slug: string;
    name: string;
    name_es: string | null;
    type: string;
    category: string;
    power: number | null;
    accuracy: number | null;
    pp: number;
    priority: number;
    target: string;
    description: string | null;
}

export interface ObjetoBuilder {
    slug: string;
    name: string;
    name_es: string | null;
    mega: string | null;
}

export interface Companero extends Especie {
    n: number;
    juntos_pct: number;
    general_pct: number;
    veces: number | null;
    afinidad: number;
}

export interface Companeros {
    n: number;
    equipos: number;
    elegidos: string[];
    companeros: Companero[];
    muestra: { min_sample: number };
}

export interface MovimientoVisto {
    slug: string;
    name: string;
    name_es: string | null;
    type: string;
    category: string;
    power: number | null;
    n: number;
    base: number;
    pct: number;
}

export interface RevelacionVista {
    slug: string;
    name: string;
    name_es: string | null;
    n: number;
    reveladas: number;
    traidas: number;
    pct: number;
}

export interface ConjuntoVisto {
    especie: string;
    traidas: number;
    movimientos: MovimientoVisto[];
    habilidades: RevelacionVista[];
    objetos: RevelacionVista[];
    muestra: { min_sample: number };
}

export interface RazonReparto {
    stat: string;
    clave: string;
    sp: number;
}

export interface RepartoSugerido {
    especie: string;
    sp: Record<string, number>;
    total: number;
    papel: string;
    ritmo: string;
    ofensiva: string;
    defensa: string;
    razones: RazonReparto[];
    alineamiento: string | null;
    tope: number;
    medido: boolean;
    velocidad: { base: number; percentil: number; ritmo: string; muestra: number } | null;
}

export interface MovimientoTaller {
    slug: string;
    name: string;
    name_es: string | null;
    type: string;
    category: string;
    power: number | null;
    accuracy: number | null;
    target: string;
    motivo: string;
    efectiva?: number;
    por_objetivo?: number;
    stab?: boolean;
    habilidad?: string | null;
    area?: boolean;
    golpea_aliado?: boolean;
    infalible?: boolean;
    funcion?: string;
}

export interface Cobertura {
    pct: number;
    resisten: { slug: string; name: string; name_es: string | null; x: number }[];
}

export interface Analisis {
    especie: FichaEspecie;
    velocidad: { base: number; percentil: number; ritmo: string; muestra: number };
    papel: Papel;
    cierre: TasaCierre | null;
    conjunto: {
        categoria: string;
        habilidad: string | null;
        movimientos: MovimientoTaller[];
        alternativas: MovimientoTaller[];
        utilidad: MovimientoTaller[];
        cobertura: Cobertura;
    };
    meta: { especies: number; traidas: number };
    deducido: boolean;
}

export interface Amenaza extends Especie {
    tipos: string[];
    peso: number;
    movimiento: string;
    movimiento_es: string | null;
    tipo_golpe: string;
    x: number;
    antes: boolean;
    n: number | null;
}

export interface Amenazas {
    especie: string;
    velocidad_base: number;
    debilidades: Record<string, number>;
    resistencias: Record<string, number>;
    confirmadas: Amenaza[];
    posibles: Amenaza[];
    meta: { especies: number };
}

export interface RazonEstructural {
    clave: string;
    tipos?: string[];
    movimientos?: string[];
}

export interface TasaCierre {
    ganadas: number;
    cierra: number;
    pct: number;
    intervalo: [number, number];
}

export interface Papel {
    eje: string;
    etiquetas: string[];
    cubos: string[];
}

export interface CompaneroEstructural extends Especie {
    tipos: string[];
    peso: number;
    eje: string;
    etiquetas: string[];
    razones: RazonEstructural[];
    encaje: number;
    cierre: TasaCierre | null;
}

export interface CompanerosEstructurales {
    especie: string;
    ritmo: string;
    cubos: Record<string, CompaneroEstructural[]>;
}

async function get<T>(ruta: string, params: Record<string, string | number> = {}): Promise<T> {
    const query = new URLSearchParams();

    for (const [clave, valor] of Object.entries(params)) {
        if (valor !== '' && valor !== undefined && valor !== null) query.set(clave, String(valor));
    }

    const cadena = query.toString();
    const respuesta = await fetch(`/api${ruta}${cadena ? `?${cadena}` : ''}`, {
        headers: { Accept: 'application/json' },
    });

    if (!respuesta.ok) {
        throw new Error(String(respuesta.status));
    }

    return respuesta.json() as Promise<T>;
}

type Comunes = { format: string; elo: number };

export const api = {
    formatos: () => get<{ formatos: Formato[] }>('/formats'),

    bringRates: (c: Comunes, top = 200) =>
        get<{ muestra: Muestra; especies: FilaBringRate[] }>('/meta/bring-rates', { ...c, top }),

    catalogo: (c: Comunes, q = '') =>
        get<{ total: number; especies: Especie[] }>('/species', { format: c.format, q }),

    especie: (slug: string, c: Comunes) => get<{ especie: FichaEspecie }>(`/species/${slug}`, { format: c.format }),

    matchups: (slug: string, c: Comunes) =>
        get<{ rival: Especie; muestra: Muestra; especies: FilaMatchup[] }>(`/meta/species/${slug}/matchups`, c),

    contextos: (slug: string, c: Comunes) =>
        get<{ contextos: ContextoConducta[] }>(`/meta/species/${slug}/behavior`, c),

    conducta: (slug: string, rival: string, fase: string, c: Comunes) =>
        get<{ muestra: Muestra; acciones: AccionConducta[] }>(`/meta/species/${slug}/behavior`, {
            ...c,
            vs: rival,
            fase,
        }),

    cores: (c: Comunes, size: number, top = 24) =>
        get<{ tamano: number; muestra: Muestra; cores: Core[] }>('/teams/cores', { ...c, size, top }),

    equipos: (c: Comunes, top = 24) =>
        get<{ muestra: Muestra; equipos: EquipoResumen[] }>('/teams', { ...c, top }),

    equipo: (id: string, c: Comunes) => get<{ muestra: Muestra; equipo: EquipoDetalle }>(`/teams/${id}`, c),

    alineamientos: () => get<{ alineamientos: Alineamiento[] }>('/builder/alignments'),

    companeros: (c: Comunes, slugs: string[], top = 8) =>
        get<Companeros>('/builder/partners', { ...c, species: slugs.join(','), top }),

    conjunto: (slug: string, c: Comunes) => get<ConjuntoVisto>(`/builder/species/${slug}/set`, c),

    reparto: (slug: string, c: Comunes, alignment: string | null) =>
        get<RepartoSugerido>(`/builder/species/${slug}/spread`, {
            format: c.format,
            alignment: alignment ?? '',
        }),

    analisis: (slug: string, c: Comunes) => get<Analisis>(`/build/species/${slug}/analysis`, c),

    amenazas: (slug: string, c: Comunes) => get<Amenazas>(`/build/species/${slug}/threats`, c),

    estructurales: (slug: string, c: Comunes, equipo: string[] = []) =>
        get<CompanerosEstructurales>(`/build/species/${slug}/structural-partners`, {
            ...c,
            equipo: equipo.join(','),
        }),

    opciones: (slug: string, c: Comunes) =>
        get<{ especie: Especie & { abilities: Habilidad[] }; movimientos: Movimiento[]; objetos: ObjetoBuilder[] }>(
            `/builder/species/${slug}`,
            { format: c.format },
        ),
};
