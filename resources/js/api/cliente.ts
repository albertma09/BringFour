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

export interface FilaBringRate extends Especie {
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

async function get<T>(ruta: string): Promise<T> {
    const respuesta = await fetch(`/api${ruta}`, {
        headers: { Accept: 'application/json' },
    });

    if (!respuesta.ok) {
        throw new Error(String(respuesta.status));
    }

    return respuesta.json() as Promise<T>;
}

export const api = {
    bringRates: (params = '') =>
        get<{ muestra: Muestra; especies: FilaBringRate[] }>(`/meta/bring-rates${params}`),

    especie: (slug: string) =>
        get<{ especie: Especie & { abilities: string[]; legal: boolean; megapiedra: { slug: string; name: string; name_es: string | null } | null } }>(
            `/species/${slug}`,
        ),

    matchups: (slug: string) =>
        get<{ rival: Especie; muestra: Muestra; especies: FilaMatchup[] }>(`/meta/species/${slug}/matchups`),

    contextos: (slug: string) =>
        get<{ contextos: ContextoConducta[] }>(`/meta/species/${slug}/behavior`),

    conducta: (slug: string, rival: string, fase: string) =>
        get<{ muestra: Muestra; acciones: AccionConducta[] }>(
            `/meta/species/${slug}/behavior?vs=${rival}&fase=${fase}`,
        ),
};
