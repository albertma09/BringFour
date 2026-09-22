# BringFour

Herramienta educativa de competitivo para **Pokémon Champions**. Filosofía: *enseñar a jugar mejor, no jugar por el usuario*. Prioridad: `UNDERSTANDING > RECOMMENDATION`, `EXPLANATION > AUTOMATION`.

El nombre viene del Team Preview: ves 6 y **traes 4**. Esa es la decisión que la herramienta enseña, y el dato diferencial del proyecto (bring rates condicionados al matchup) es exactamente eso.

## ESTADO

**Fase 0 (investigación) completada. Fase 1 (capa de datos) en curso desde el 22-sep-2026.**

Lee estos ficheros antes de hacer nada:

1. 👉 **[`docs/DECISIONES.md`](docs/DECISIONES.md)** — decisiones cerradas por el usuario. Manda sobre cualquier alternativa que proponga la investigación.
2. **[`docs/INVESTIGACION-FASE-1.md`](docs/INVESTIGACION-FASE-1.md)** — mecánicas del juego, fuentes de datos con licencias, competidores, riesgo legal, arquitectura, esquema de BD y roadmap.
3. **[`docs/INVESTIGACION-BATTLE-LOGS.md`](docs/INVESTIGACION-BATTLE-LOGS.md)** — replays de Showdown como fuente de **datos de comportamiento condicional**. Es el foso competitivo del proyecto.

`AGENTS.md` contiene las guidelines de Laravel Boost que vienen con el framework. Aplican al código Laravel; **este fichero manda sobre ellas** en caso de conflicto.

## REGLAS DE TRABAJO

- 🚫 **Sin comentarios en el código.** Preferencia global del usuario, cualquier lenguaje. Excepción: algún comentario puntual en JS para explicar una función concreta. Las justificaciones van en la respuesta al usuario o en un `.md`.
- Antes de implementar algo que toque varios ficheros: **listado de objetivos** con checkboxes agrupado por tipo de fichero, terminando con la pregunta de confirmación.
- El `CLAUDE.md` global del usuario describe un framework hotelero PHP/XSLT. **Nada de eso aplica aquí**: no hay XSLT, ni CMS, ni esos agentes especializados.

## ENTORNO DE DESARROLLO

⚠️ **El `php` del PATH es 7.4 (WAMP, proyecto del trabajo). NO tocarlo ni modificar la config global de WAMP.**

| Pieza | Cómo se usa |
|---|---|
| PHP | `C:/wamp64/bin/php/php8.3.6/php.exe -c php.ini` — el `php.ini` del proyecto activa `pdo_pgsql` |
| Composer | `php.exe -c php.ini "C:/Users/Albert Mateos/AppData/Local/ComposerSetup/bin/composer.phar"` |
| Atajo | `./dev.ps1 <artisan|composer|...>` |
| PostgreSQL | Docker, contenedor `bringfour-db`, **puerto host 5434** (el 5433 lo ocupa `scoreboard-db`) |
| Node | v22 local, solo para `ingest/` (build-time) |

Laravel 13.32 · PHP 8.3.6 · PostgreSQL 16.

## ARQUITECTURA (opción C, aprobada)

```
GitHub Actions (Node)  →  JSON versionado en Git  →  Postgres (seeders Laravel)
Vue 3 + TS  →  @smogon/calc en cliente (cálculo de daño)
Laravel  →  REST API + RuleEngine (PHP, determinista) + LLM Gateway (opcional, premium)
```

- **No se reimplementa la calculadora de daño en PHP.** `@smogon/calc` corre en el navegador.
- **No hay servicio Node en producción.** Node solo en CI.
- La ingesta nunca corre en caliente: si una fuente cae, la web sigue con el último JSON commiteado.

## HECHOS DEL JUEGO (verificados, no re-investigar)

- Champions salió el **8-abr-2026** (Switch) y **17-jun-2026** (iOS/Android). F2P, cross-play, conecta con Pokémon HOME.
- Es la **plataforma oficial de Play! Pokémon VGC** desde mayo de 2026.
- **No hay IVs** (todos actúan como 31). **No hay EVs.**
- **Stat Points (SP): 66 totales, tope 32 por estadística.** 1 SP = +1 stat a nivel 50.
- Nivel siempre **50** en combate.
- Las naturalezas se llaman **Stat Alignments** (±10%). **Son 25, 5 de ellas neutras** (verificado contra `Dex.natures.all()`; el dato de "21" que circula es falso).
- Fórmulas verificadas:
  - `HP = Base + 75 + SP`
  - `Resto = (Base + 20 + SP) × alignment`
- Conversión legacy: `1 SP = 8 EVs`, salvo el primer SP de cada stat que cuesta 4.
- Los Pokémon se reclutan con **Victory Points (VP)**; reentrenar SP también cuesta VP. El coste en VP es una restricción real de teambuilding.
- **Mega Evolución** disponible. **Terastalización** anunciada, aún no disponible.
- Los **Replica Team codes** son de 10 caracteres, opacos y resueltos en servidor. **No se pueden decodificar ni generar localmente.** El import/export tiene que ser por texto tipo Showdown paste o formato propio.

## REGULACIONES

Cambian cada ~3 meses y alteran roster, objetos, movimientos y habilidades.

| Reg | Vigencia |
|---|---|
| M-B | hasta 8-sep-2026 |
| **M-C** | **9-sep-2026 → 2-dic-2026** (activa) |
| M-D | previsible a partir del 2-dic-2026 |

M-C: dobles 4-6 Pokémon, singles 3-6; 7 min totales, 45 s/turno, 90 s de Team Preview. Añadió 34 Pokémon y 6 Mega Evoluciones respecto a M-B.

> ⚠️ **`regulation_id` es entidad de primera clase en el esquema.** Todo dato competitivo (legalidad, usage, sets, análisis guardados) va versionado por regulación. No versionarlo es el error de diseño más caro del proyecto.

## FUENTES DE DATOS

- **Primaria:** mods `champions` y `championsregmb` en `smogon/pokemon-showdown` (MIT).
- **Meta:** Smogon usage stats mensuales. Formatos: `gen9championsvgc2026regmc`, `gen9championsou`, `gen9championsuu`, `gen9championsbssregmb`, variantes Bo3. Cortes de ELO 0/1500/1630/1760.
- **Cálculo de daño:** `@smogon/calc` (MIT). **No reimplementar en PHP.**
- **Comportamiento (el foso):** replays públicos de Showdown. `replay.pokemonshowdown.com/search.json?format=gen9championsvgc2026regmc` y `<replay-id>.json`. ⚠️ **Requiere cabecera `User-Agent` o devuelve 403.** ~2.200 replays/día en Reg M-C. Ver [`docs/INVESTIGACION-BATTLE-LOGS.md`](docs/INVESTIGACION-BATTLE-LOGS.md).
- **Prohibido:** scraping de Pikalytics; usar el output de `project-red-data` (no tiene licencia); usar el código de `pokemon_champions_battle_logger` (All Rights Reserved, no comercial).
- **No existe API oficial** de Champions.
- **Champions NO exporta logs de combate.** El "View Log" de Reg M-C es solo del combate en curso: no se guarda, no se exporta, no hay replays. El juego sí muestra usage stats oficiales in-game en `Battle > Battle Data` (no extraíbles) — motivo extra para que el producto no sean usage stats.

## RIGOR ESTADÍSTICO (no negociable)

- **N mínimo = 30** para mostrar un porcentaje. Por debajo, no se muestra.
- **Mostrar siempre el N** junto al porcentaje.
- **Segmentar por ELO** con los mismos cortes que Smogon, para poder comparar.
- **Etiquetar siempre la fuente.** Los datos de comportamiento son de Showdown, no del ladder de Champions: población distinta y ELO medio bajo (~1.180).
- El copy nunca dice "deberías". Dice qué hace la gente y cuáles son las consecuencias de cada línea.

## LEGAL

No meter "Pokémon" en marca ni dominio. Disclaimer "Not affiliated with Nintendo / The Pokémon Company". Cobrar por el análisis propio, nunca por los datos. Agregar y anonimizar: el nombre del jugador solo como hash para deduplicar, nunca mostrarlo. Detalle en la sección 4 de la investigación.
