# DECISIONES APROBADAS

Respuestas del usuario a las 6 preguntas de la sección 11 de [`INVESTIGACION-FASE-1.md`](INVESTIGACION-FASE-1.md).

**Fecha:** 21-sep-2026
**Estado:** arquitectura aprobada. **Desarrollo pendiente de arrancar (día siguiente).** Sigue sin haber código.

---

## 1. Arquitectura — ✅ APROBADA la opción C

Node **solo en build-time**, `@smogon/calc` en el navegador, Laravel para API + rule engine.

```
GitHub Actions (Node)  →  JSON versionado en Git  →  Postgres (seeders Laravel)
Vue 3 + TS  →  @smogon/calc en cliente (cálculo de daño)
Laravel  →  REST API + RuleEngine (PHP, determinista) + LLM Gateway (opcional, premium)
```

Consecuencias cerradas:
- **No se reimplementa la calculadora de daño en PHP.**
- **No hay microservicio Node en producción.** Una sola máquina: PHP-FPM + Postgres + estáticos.
- La ingesta nunca corre en caliente: si una fuente cae, la web sigue con el último JSON commiteado.

## 2. Formato — ✅ DOBLES (VGC)

Dobles es el formato principal. Singles queda como posible extensión futura, no entra en el MVP.

El rule engine se diseña para dobles: Fake Out, Intimidate, redirección, Wide Guard, spread damage, posicionamiento de los 4 slots.

## 3. Idiomas — ✅ ES + EN EN PARALELO

Estructura i18n desde el día 1. **Contenido en EN primero, ES en paralelo.** Las plantillas de explicación del rule engine son claves i18n con parámetros desde el principio, nunca texto incrustado.

## 4. Nombre — ✅ DELEGADO → propuesta: **Turn Zero**

Propuesta principal: **Turn Zero** (`turnzero.gg`).

Por qué encaja:
- El "turno cero" es todo lo que ocurre **antes** de que empiece la partida: construir el equipo, leer el Team Preview, preparar el plan. Es literalmente el alcance del producto.
- Refuerza la filosofía: la herramienta no juega tus turnos, trabaja en el turno cero.
- Corto, pronunciable en ES y EN, sin "Pokémon" en la marca (mitigación legal de la sección 4).
- No colisiona con ChampDex, ChampsDex, ChampTeams ni championslab.xyz.

Alternativas si el dominio no está libre: `Outspeed`, `Open Sheet`, `Sixth Slot`, `Mindgame`.

> ⚠️ **Pendiente:** verificar disponibilidad real del dominio y que no haya marca registrada. No se ha comprobado.

## 5. Repositorio — ✅ PÚBLICO, cuenta personal

- Repo **público** (GitHub Actions gratis ilimitado + credibilidad en la escena competitiva).
- **Misma cuenta que `prediction-scoreboard`** (cuenta personal).
- 🚫 **NO subir a la cuenta de empresa.**

> ⚠️ **Comprobar antes del primer commit:** el email de git de este proyecto **no** puede ser el corporativo (`web@neobookings.com`). Configurar `git config user.email` con el personal **a nivel de repositorio**, no global, antes de commitear nada.

## 6. Documento de referencia de mecánicas — ✅ APROBADO, pendiente de escribir

Escribir un `--read-claude-champions-mechanics.md` con mecánicas, fórmulas, rutas de datos y patrones, para que las sesiones futuras no redescubran nada.

**Estado:** aprobado pero **no escrito todavía** (el usuario pidió dejar solo las decisiones documentadas hoy). Parte del contenido ya está resumido en [`CLAUDE.md`](CLAUDE.md); el documento completo se escribe mañana, al inicio de la Fase 1.

---

## PRÓXIMO PASO (mañana)

1. Escribir `--read-claude-champions-mechanics.md` (decisión 6).
2. Verificar dominio y nombre definitivo (decisión 4).
3. Arrancar **Fase 1 — Data layer**: ingesta Showdown → JSON → Postgres, versionado por `regulation_id`, migraciones, seeders y tests de integridad.

Antes de tocar varios ficheros, presentar el **listado de objetivos** con checkboxes y pedir confirmación.

---

# FASE 1 — DECISIONES TOMADAS DURANTE LA IMPLEMENTACIÓN

**Fecha:** 22-sep-2026

Respuestas del usuario a las preguntas abiertas de la investigación de replays:

| # | Pregunta | Respuesta |
|---|---|---|
| 1 | ¿Recolector de replays en la Fase 1? | ✅ Sí |
| 2 | Umbral de N mínimo | ✅ **30** |
| 3 | ¿Segmentar por ELO? | ✅ Sí, cortes de Smogon (0/1500/1630/1760) |
| 4 | ¿Guardar el log crudo? | ✅ Sí, comprimido |
| 5 | ¿Recolectar singles? | ✅ Sí, recolectar sin procesar |

Decisiones técnicas tomadas al implementar, con su motivo:

- **PHP 8.3.6 de WAMP con un `php.ini` propio del proyecto**, en vez de instalar PHP o tocar la configuración global. El `php` del PATH es 7.4 y es el del proyecto del trabajo: no se toca.
- **PostgreSQL en el puerto 5434**, porque `scoreboard-db` ya ocupa el 5433.
- **PHPUnit + Pest 4.7.** Pest 5 exige PHP 8.4, que WAMP no tiene.
- **Base de datos de test aparte (`turnzero_test`)** con `DatabaseTransactions`, en vez de sqlite en memoria: es un proyecto de capa de datos, testear contra sqlite no probaría nada.
- **Catálogo global + legalidad por regulación**, en vez de duplicar especies por regulación. Obligado por casos reales como Floette-Eternal (legal) con Floette (ilegal) como forma base.
- **`items.mega_evolutions` como jsonb**, no un slug: en Champions `megaStone` es un mapa base→mega por la existencia de Z-Megas.
- **Aritmética entera en las fórmulas de estadísticas.** Con floats aparecen errores de un punto.
- **Constraint de SP `<= 66`, no `= 66`**, porque el builder debe permitir equipos a medio construir. El "gasta los 66" es una guía de UI, no una regla de integridad.
- **`data/` commiteado, `data/raw/` fuera de git.** Producción nunca depende de la fuente en caliente.

## Pendiente de decidir (no bloquea)

- **Dónde vive el archivo de replays a largo plazo.** El workflow `collect-replays.yml` está en `workflow_dispatch` (sin cron) apuntando a una rama `replay-archive`. Con ~4.600 replays/día y compresión al 13%, el archivo crece **~90 MB/mes, ~1,1 GB/año**. Opciones: rama huérfana en este repo, repo de datos aparte, o disco del VPS. Hay que elegir antes de activar el cron. El backfill funciona, así que esperar unos días no pierde datos.
- **Dominio y marca.** `turnzero.gg` sin verificar disponibilidad ni registro de marca.
