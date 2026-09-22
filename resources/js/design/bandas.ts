import type { FilaBringRate } from '@/api/cliente';

export type ClaveBanda = 'siempre' | 'depende' | 'casos';

export interface Banda {
    clave: ClaveBanda;
    desde: number | null;
    hasta: number | null;
    filas: FilaBringRate[];
}

function cuartil(valores: number[], porcentaje: number): number {
    if (valores.length === 0) return 0;

    const indice = Math.round((porcentaje / 100) * (valores.length - 1));

    return valores[Math.min(valores.length - 1, Math.max(0, indice))];
}

export function repartirEnBandas(filas: FilaBringRate[]): { bandas: Banda[]; corteAlto: number; corteBajo: number } {
    const ordenadas = [...filas].sort((a, b) => b.bring_pct - a.bring_pct);
    const valores = ordenadas.map((fila) => fila.bring_pct).sort((a, b) => a - b);

    const corteAlto = cuartil(valores, 75);
    const corteBajo = cuartil(valores, 25);

    return {
        corteAlto,
        corteBajo,
        bandas: [
            {
                clave: 'siempre',
                desde: corteAlto,
                hasta: null,
                filas: ordenadas.filter((fila) => fila.bring_pct >= corteAlto),
            },
            {
                clave: 'depende',
                desde: corteBajo,
                hasta: corteAlto,
                filas: ordenadas.filter((fila) => fila.bring_pct < corteAlto && fila.bring_pct >= corteBajo),
            },
            {
                clave: 'casos',
                desde: null,
                hasta: corteBajo,
                filas: ordenadas.filter((fila) => fila.bring_pct < corteBajo),
            },
        ],
    };
}

export function extremosDestacados(filas: FilaBringRate[], cuantos = 20): [FilaBringRate, FilaBringRate] | null {
    if (filas.length < 2) return null;

    const masVistos = [...filas].sort((a, b) => b.n - a.n).slice(0, cuantos);
    const porTasa = [...masVistos].sort((a, b) => b.bring_pct - a.bring_pct);

    return [porTasa[0], porTasa[porTasa.length - 1]];
}
