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

## 4. Nombre — ✅ CERRADO: **BringFour** (`bringfour.gg`)

Historial: la primera propuesta fue **Turn Zero**, descartada el 22-sep-2026 porque `turnzero.gg` ya lo usa una web de Warhammer 40K.

Candidatos verificados (DNS real + búsqueda de colisiones de marca):

| Nombre | .gg | .com | .app | Colisión |
|---|---|---|---|---|
| Turn Zero | ❌ Warhammer 40K | — | — | — |
| Outspeed | libre | registrado | libre | ❌ startup de IA financiada + marca de esports |
| TeamShape | libre | ❌ ocupado | libre | ⚠️ software de RRHH holandés (2006) |
| **BringFour** | ✅ libre | ✅ libre | ✅ libre | ✅ ninguna |

Por qué BringFour:
- **Único libre en los tres TLD a la vez**, lo que da consistencia de marca completa.
- Sin colisión de marca conocida.
- En dobles VGC ves 6 en el Team Preview y **traes 4**: es la decisión que enseña el producto, y el dato diferencial (bring rates condicionados al matchup) es exactamente eso. Nombre y foso dicen lo mismo.
- Sin "Pokémon" en la marca (mitigación legal de la sección 4 de la investigación).
- No colisiona con ChampDex, ChampsDex, ChampTeams, championslab.xyz, VGC.tools, VGC Lite ni VGC Helper.

Pega asumida: es específico de dobles. Si algún día se añade singles (BSS trae 3), el nombre queda algo torcido. Aceptable, porque la decisión 2 fijó dobles como formato del producto.

> ⚠️ **Pendiente:** el dominio está libre por DNS pero **no se ha registrado**. Tampoco se ha hecho búsqueda formal en registros de marcas.

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
- **Base de datos de test aparte (`bringfour_test`)** con `DatabaseTransactions`, en vez de sqlite en memoria: es un proyecto de capa de datos, testear contra sqlite no probaría nada.
- **Catálogo global + legalidad por regulación**, en vez de duplicar especies por regulación. Obligado por casos reales como Floette-Eternal (legal) con Floette (ilegal) como forma base.
- **`items.mega_evolutions` como jsonb**, no un slug: en Champions `megaStone` es un mapa base→mega por la existencia de Z-Megas.
- **Aritmética entera en las fórmulas de estadísticas.** Con floats aparecen errores de un punto.
- **Constraint de SP `<= 66`, no `= 66`**, porque el builder debe permitir equipos a medio construir. El "gasta los 66" es una guía de UI, no una regla de integridad.
- **`data/` commiteado, `data/raw/` fuera de git.** Producción nunca depende de la fuente en caliente.

## Pendiente de decidir (no bloquea)

- **Dónde vive el archivo de replays a largo plazo.** El workflow `collect-replays.yml` está en `workflow_dispatch` (sin cron) apuntando a una rama `replay-archive`. Con ~4.600 replays/día y compresión al 13%, el archivo crece **~90 MB/mes, ~1,1 GB/año**. Opciones: rama huérfana en este repo, repo de datos aparte, o disco del VPS. Hay que elegir antes de activar el cron. El backfill funciona, así que esperar unos días no pierde datos.
- **Dominio y marca.** `bringfour.gg` sin verificar disponibilidad ni registro de marca.

---

# FASE 1.5 — PARSER TURNO A TURNO

**Fecha:** 22-sep-2026

- **La vida que se guarda en cada acción es la del principio del turno**, no la de después del daño. Es la única que el jugador tenía delante al elegir. Guardar la de después mezclaría la decisión con su resultado, y toda la herramienta va de explicar qué información existía al decidir.
- **`decision_seconds` es del turno, no del jugador.** El reloj de Showdown corre para los dos a la vez y el log no permite separar quién tardó. Por eso la columna vive en `replay_turns`. Se calcula como la diferencia entre marcas `|t:|` consecutivas, de modo que el tiempo gastado en elegir un relevo obligado no se suma al turno siguiente. Se descarta cualquier valor por encima de 1800 s (desconexiones).
- **El actor de una acción se resuelve por el hueco (`p1a`), nunca por la etiqueta del log.** La etiqueta es el mote del jugador o el nombre de la forma base: `|move|p1b: Samurott|` puede ser Samurott-Hisui. Sin el mapa de huecos el parser produciría datos falsos en silencio.
- **La megaevolución se aplica al turno en que se declara.** Se elige a la vez que el movimiento, así que el actor y el estado del campo de ese turno usan ya la forma mega. Así actor y contexto no se contradicen dentro del mismo turno.
- **Un `|cant|` solo cuenta si ese hueco no había hecho nada más ese turno.** Showdown emite `cant` también sobre quien *bloquea* el movimiento ajeno (Cola Armadura contra Amago), y ese Pokémon sí actuó.
- **Los `cant` no entran en los priors.** Cuando no hay un `|move|` previo, sé que no actuó pero no sé qué había elegido.
- **Los cambios forzados no son decisiones.** Un relevo tras debilitarse, un `drag` (Rugido) o un cambio con etiqueta `[from]` (Voltiocambio) se marcan `forced` y quedan fuera de los priors.
- **Contexto de un prior: `{actor, rival, fase}`.** Una decisión cuenta una vez por cada rival activo: son probabilidades condicionadas distintas, no duplicados. `fase` separa el turno 1 del resto porque si no Amago domina la tabla.

## Formatos Bo3

Se comprobó sobre 540 replays de `gen9championsvgc2026regmcbo3`: **cada replay es una partida, no un set** (un `|teampreview|`, un `|start|`, un `|win|`). El riesgo no es mezclar partidas dentro de un log, sino que las 2-3 partidas de un mismo set comparten equipos y jugadores, y en la segunda y la tercera ya se conoce el equipo rival. Eso rompe la independencia de las observaciones, así que **se recolectan pero no se procesan**.

El flag vive en `ingest/config.mjs` → `data/regulations.json` → tabla `formats`. Cambiar `config.mjs` no basta: hay que **regenerar `data/`** con `node ingest/showdown-data.mjs` y volver a lanzar `artisan data:sync`. Se detectó porque 3.060 replays Bo3 se habían importado con el flag viejo.

## Limitaciones conocidas

- **`fase` se calcula por número de turno, no por turnos desde que el Pokémon entró al campo.** Un Rillaboom que entra en el turno 4 y usa Amago aparece como `medio`. Sería más informativo contar desde su entrada.
- **Los priors no distinguen el objetivo elegido en dobles**, ni la vida como parte del contexto: con 3.100 replays fragmentaría la muestra hasta dejarla inservible.
- **Todo cae en el bucket de ELO 0.** El ladder de Champions en Showdown no pasa de 1622, así que los cortes de Smogon (1500/1630/1760) todavía no separan nada.
