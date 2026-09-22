import { createI18n } from 'vue-i18n';
import es from './es';
import en from './en';

const GUARDADO = 'bringfour:idioma';

function idiomaInicial(): string {
    try {
        const guardado = localStorage.getItem(GUARDADO);
        if (guardado === 'es' || guardado === 'en') return guardado;
    } catch {
        // el navegador puede tener el almacenamiento bloqueado
    }

    return navigator.language?.startsWith('en') ? 'en' : 'es';
}

export const i18n = createI18n({
    legacy: false,
    locale: idiomaInicial(),
    fallbackLocale: 'es',
    messages: { es, en },
});

export function cambiarIdioma(locale: 'es' | 'en'): void {
    i18n.global.locale.value = locale;
    document.documentElement.lang = locale;

    try {
        localStorage.setItem(GUARDADO, locale);
    } catch {
        // sin almacenamiento el idioma dura lo que la sesion
    }
}
