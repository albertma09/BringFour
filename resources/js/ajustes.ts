import { computed, reactive, watch } from 'vue';
import { router } from '@/router';

export const CORTES_ELO = [0, 1500, 1630, 1760] as const;

const GUARDADO = 'bringfour:ajustes';

interface Ajustes {
    format: string;
    elo: number;
}

function guardados(): Ajustes {
    try {
        const crudo = localStorage.getItem(GUARDADO);
        if (crudo) {
            const leido = JSON.parse(crudo) as Partial<Ajustes>;
            return {
                format: leido.format ?? 'gen9championsvgc2026regmc',
                elo: CORTES_ELO.includes(leido.elo as never) ? (leido.elo as number) : 0,
            };
        }
    } catch {
        // el navegador puede tener el almacenamiento bloqueado
    }

    return { format: 'gen9championsvgc2026regmc', elo: 0 };
}

export const ajustes = reactive<Ajustes>(guardados());

export const comunes = computed(() => ({ format: ajustes.format, elo: ajustes.elo }));

export function aplicarDesdeUrl(query: Record<string, unknown>): void {
    if (typeof query.format === 'string' && query.format !== '') ajustes.format = query.format;

    const elo = Number(query.elo);
    if (CORTES_ELO.includes(elo as never)) ajustes.elo = elo;
}

watch(
    () => ({ ...ajustes }),
    (valor) => {
        try {
            localStorage.setItem(GUARDADO, JSON.stringify(valor));
        } catch {
            // sin almacenamiento los ajustes duran lo que la sesion
        }

        const actual = router.currentRoute.value;
        const query = { ...actual.query, format: valor.format, elo: String(valor.elo) };

        if (actual.query.format !== query.format || actual.query.elo !== query.elo) {
            router.replace({ query });
        }
    },
    { deep: true },
);
