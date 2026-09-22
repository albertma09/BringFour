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

export interface FichaEspecie extends Especie {
    abilities: string[];
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

    opciones: (slug: string, c: Comunes) =>
        get<{ especie: Especie & { abilities: string[] }; movimientos: Movimiento[]; objetos: ObjetoBuilder[] }>(
            `/builder/species/${slug}`,
            { format: c.format },
        ),
};
