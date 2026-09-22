import type { Stat } from '@/design/types';

export const SP_TOTAL = 66;
export const SP_TOPE = 32;

export type Reparto = Record<Stat, number>;

export function repartoVacio(): Reparto {
    return { hp: 0, atk: 0, def: 0, spa: 0, spd: 0, spe: 0 };
}

export function gastados(reparto: Reparto): number {
    return Object.values(reparto).reduce((total, valor) => total + valor, 0);
}

export function restantes(reparto: Reparto): number {
    return SP_TOTAL - gastados(reparto);
}

export function valorFinal(stat: Stat, base: number, sp: number, plus: string | null, minus: string | null): number {
    if (stat === 'hp') {
        return base + 75 + sp;
    }

    const bruto = base + 20 + sp;

    if (plus === stat && minus !== stat) return Math.floor((bruto * 11) / 10);
    if (minus === stat && plus !== stat) return Math.floor((bruto * 9) / 10);

    return bruto;
}

export function spAEvs(sp: number): number {
    return sp <= 0 ? 0 : 4 + (sp - 1) * 8;
}
