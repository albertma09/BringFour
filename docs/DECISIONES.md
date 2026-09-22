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

---

# FASE 2 — META EXPLORER (API + PRIMERA PANTALLA)

**Fecha:** 22-sep-2026

## Recolección

- **Rama huérfana `replay-archive`** para el archivo de replays, decidida por el usuario. Cron diario a las 04:17 UTC. El workflow hace **dos checkouts**: el código desde `main` y el archivo desde `replay-archive`, porque la rama del archivo no tiene `ingest/`. El collector acepta `BF_RAW_DIR` para escribir fuera del repo de código.
- **Reintento con espera creciente (1 s, 4 s, 16 s) ante fallo de red**, y un formato que agota los reintentos ya no aborta los demás: se guarda su estado y se sigue. El proceso termina con error si alguno falló, para que CI lo vea. El backfill se murió con un `fetch failed` porque el `try` solo cubría respuestas HTTP, no excepciones de `fetch`.
- `.gitattributes` con `* -text` en la rama del archivo: sin eso Git convertiría los saltos de línea de los `.jsonl` en Windows.

## Sprites

- **388 de 399 especies tienen sprite propio en Showdown.** Solo 11 megas recientes (Staraptor, Raichu-X/Y, Pyroar, Falinks, Malamar, Scrafty, Scolipede, Barbaracle, Dragalge, Eelektross) no lo tienen y usan el apaño de sprite base + megapiedra. La creencia anterior de que hacían falta las 80 era falsa.
- Los iconos de objeto vienen de la hoja `itemicons-sheet.png` (89 KB, 384×1152, 16 columnas de 24 px). El desplazamiento sale de `spritenum`, que se ha añadido a la ingesta.
- **Alojados en `public/sprites/` (1,9 MB), no enlazados al CDN de Showdown.** Pendiente de revisar con el usuario por el tema de derechos.

## API

- Consultas extraídas a `app/Domain/Meta/*Query.php`, usadas por el comando y por el controlador. Misma razón que `SideQuery`: dos copias del SQL acaban dando números distintos.
- **El mínimo de 30 se impone en la API** (`max(30, ...)`), no en el cliente: desde la terminal se puede bajar para depurar, pero por HTTP no sale un porcentaje con N pequeño ni manipulando la URL.
- Los cortes de ELO que no son uno de los tramos de Smogon se ignoran y caen a 0, en vez de aceptar cualquier número.
- Sin autenticación ni versionado de la API: un solo mantenedor, ningún consumidor externo.

## Frontend

- Vue 3 + TypeScript + vue-router + vue-i18n. SPA sobre una sola vista Blade; `routes/web.php` excluye `api`, `up` y `sprites`.
- **Español e inglés desde el principio**, con el idioma guardado en `localStorage` y envuelto en `try/catch` porque el navegador puede tenerlo bloqueado.
- Diseño oscuro sin neón: carbón por elevación, arena apagada como único acento, y los colores de tipo desaturados haciendo el trabajo cromático. Números tabulares en todas las tablas.
- **`vue-tsc` no funciona con TypeScript 7**, que es lo que instala npm por defecto. Fijado a TypeScript 5.9.
- **`artisan serve` no sirve en este entorno**: lanza un proceso hijo que no hereda `-c php.ini`, así que se queda sin `pdo_pgsql`. Se usa `./dev.ps1 serve`, que arranca el servidor embebido con el ini del proyecto.

## Limitaciones conocidas

- La pantalla no se ha visto en un navegador: aquí no hay con qué abrirlo. Compila, pasa el comprobador de tipos y la API responde, pero el resultado visual está sin revisar.
- El buscador filtra en cliente sobre las 200 filas que devuelve la API, no sobre las 382 especies.

---

# FASE 3 — REPLANTEAMIENTO: CINCO SECCIONES

**Fecha:** 22-sep-2026

## Lo que hay ya en el mercado (investigado el 22-sep-2026)

El panorama cambió mucho desde la investigación de fase 0. Herramientas específicas de Champions ya en marcha:

| Herramienta | Secciones | Fuente |
|---|---|---|
| op.gg/pokemon-champions | Pokédex, Tier, Replica Teams, Sample Builds, Wiki, Best Trainer, Tools | ladder del juego |
| Pikalytics | Pokedex, Teams (top teams/usage/builder), Tournaments, Labs, Damage Calc | torneos + Showdown |
| suckerpunch.gg | Rankings (incl. Matchups), Build Slots, Teams & Players, EV Optimizer, metodología | Limitless, Victory Road, circuito oficial |
| champteams.gg | Tier List, Best Teams, Top Cores, Community Teams | torneos ponderados |
| metavgc.com | Team Builder, Damage calc, Notepad, Team Finder, Forum | torneos (tema claro) |

**Conclusión que manda sobre la arquitectura:** todas dicen *qué se usa* y varias tienen datos de torneo oficial que nosotros no tenemos ni vamos a tener. Ninguna dice *qué hace la gente*. Copiar su estructura sería competir donde somos más débiles, así que la navegación lleva las secciones esperadas pero el producto abre por el diferencial.

## Arquitectura

Cinco secciones: **Meta · Matchups · Equipos · Constructor · Pokédex**, con selector de formato y de corte de ELO globales en la cabecera y reflejados en la URL para poder compartir enlaces.

- **Meta** separa dos métricas que ninguna otra herramienta distingue: cuánto se **lleva** (aparece en el Team Preview, sobre todos los lados) y cuánto se **trae** (entra al combate, solo sobre los lados completos). Las bandas de conducta se cortan por los cuartiles de la propia distribución, no por umbrales inventados.
- **Equipos** sale de un hallazgo al mirar los datos: el mismo equipo de seis aparece hasta **138 veces**. La gente copia equipos, así que las compos tienen muestra de sobra. La ficha de equipo enseña qué cuatro de esos seis se traen, que es lo que no tiene nadie.
- **Constructor** no recomienda: al elegir enseña qué hace la gente con ese Pokémon. Es la línea del proyecto y lo más difícil de copiar.

## Decisiones técnicas

- **El identificador de un equipo usa guion, no punto.** Con punto (`rillaboom.sneasler...`) el servidor lo toma por extensión de fichero y devuelve 404 antes de llegar a Laravel. Los slugs son alfanuméricos, así que el guion es seguro.
- **Paleta pizarra azulada (`#181c25` base) en vez del casi negro anterior**, a petición del usuario. Acento ámbar apagado, **evitando el cian eléctrico de op.gg** para no parecer un clon. Colores de tipo recalibrados para el fondo nuevo.
- **Comprobación automática de traducciones** (`npm run check:i18n`): verifica que toda clave usada en plantillas existe en español y en inglés, y que no hay claves huérfanas. Una clave que falte se ve como texto crudo en pantalla y no lo detectaba nada. Ya va en CI.

## El código de copia del juego: no se puede

Los **Replica Team codes** de Champions son diez caracteres opacos que resuelve el servidor de Nintendo. No se pueden generar ni descifrar desde fuera, así que la petición de "compos con su código de copia" no tiene solución técnica. Lo que se ofrece es el equipo en formato de texto de Showdown y los datos para montarlo a mano. El aviso aparece en la propia interfaz, no solo aquí.

## Limitaciones conocidas

- La interfaz sigue sin revisarse en un navegador desde este entorno: compila, pasa tipos y traducciones, y todas las rutas responden, pero el resultado visual lo juzga el usuario.
- El Constructor guarda en `localStorage`, sin cuentas ni compartir equipos.
- El selector de ELO existe pero solo el tramo 0 tiene datos: el ladder de Champions en Showdown no pasa de 1622.

---

# Fase 4 — Recomendaciones en el Constructor (22-sep-2026)

## Un fallo que habría salido a la cara del usuario

La primera consulta de habilidades reveladas devolvía esto:

```
Rillaboom   Intimidate  272
Sneasler    Intimidate  121
```

Ninguno de los dos tiene Intimidación. El parser enganchaba la revelación a la **última acción del hueco**, y en un cambio el actor de la acción es **el que sale**, no el que entra. Así que una línea como:

```
|switch|p1a: Staraptor|Staraptor, L50, M|100/100
|-ability|p1a: Staraptor|Intimidate|boost
```

acababa atribuyendo la Intimidación de Staraptor al Pokémon que Staraptor acababa de relevar.

**Arreglo:** las revelaciones dejan de colgar de una acción y pasan a tabla propia (`replay_reveals`), atribuidas a **la especie que ocupa el hueco en ese instante** según el estado de combate. Eso además recupera las revelaciones del turno 0 (las habilidades de los iniciales, que son las más informativas), que antes se perdían enteras porque todavía no había turno abierto al que colgarlas.

**Ganancia colateral:** ahora también se leen las habilidades que solo aparecen como etiqueta en otra línea:

```
|-fail|p2a: Oranguru|unboost|atk|[from] ability: Inner Focus|[of] p2a: Oranguru
```

El dueño es el que indica `[of]`. Con eso las revelaciones de habilidad pasan de **2.948 a 10.001**.

**Casos que se descartan a propósito**, porque el objeto o la habilidad no son de quien parece:

- `[from] move: Trick` / `Switcheroo` / `Thief` — el objeto acaba de cambiar de dueño.
- `[from] ability: Trace` / `Receiver` / `Power of Alchemy` — la habilidad mostrada es copiada; la copiadora sí se atribuye a quien copia.
- `Frisk` y `Pickpocket` sí se aceptan: revelan el objeto real del Pokémon señalado.

Se cuenta **una revelación de cada tipo por especie y partida**, no cada activación. Contar activaciones inflaría Intimidación (salta en cada cambio) frente a una habilidad de un solo disparo.

## Compañeros: afinidad, no frecuencia

Ordenar compañeros por veces que coaparecen da siempre el mismo resultado inútil: arriba el Pokémon que está en todos los equipos. Para Rillaboom, el más repetido es Incineroar (769 veces) — pero Incineroar está en el 24 % de **todos** los equipos, así que no dice nada.

La métrica es `P(Y|X) / P(Y)`. Con ella Incineroar cae al octavo puesto y arriba sale Floette-Eternal, que va con Rillaboom 2,13 veces más de lo que le tocaría por su cuenta.

Para ordenar no se usa la afinidad cruda sino la calculada sobre el **suelo del intervalo de Wilson** de `P(Y|X)`. Así una pareja con N=31 y afinidad aparente altísima no adelanta a una con N=213 y afinidad algo menor. Se enseñan las dos cifras: el porcentaje bruto, que se entiende, y el múltiplo, que es el que informa.

## Lo que no se puede sacar de los datos: los SP

**Los replays de Showdown nunca exponen las estadísticas.** La vida se muestra en porcentaje y no hay EVs ni SP en el protocolo. No es difícil: el dato no está.

La alternativa es un motor determinista (`SpreadAdvisor`) **etiquetado como regla en la propia pantalla**, nunca presentado como lo que hace la gente. La restricción real manda sobre el diseño: con 66 puntos y tope de 32, solo caben **dos cosas al máximo**. El reparto es por tanto una elección entre tres pares, decidida por el papel (ofensivo/apoyo) y el ritmo (rápido/medio/lento).

El alineamiento que elige el usuario **manda sobre las estadísticas base**: si baja velocidad, el Pokémon se trata como lento aunque su base sea alta. La herramienta responde a la decisión del usuario en vez de dictarla. Cada punto repartido lleva su motivo.

## Sesgos que se declaran en pantalla

- **El objeto solo se revela si se activa.** Una Baya Zidra se ve cuando baja la vida; unas Gafas Especiales no se ven nunca. Los objetos consumibles salen sobredimensionados y eso se avisa bajo la sugerencia.
- **Los movimientos se cuentan sobre partidas, no sobre usos**, para que un Protección repetido cinco veces no tape al resto. El denominador son las partidas en las que esa especie llegó a mover.
- Que Rillaboom lleve Protección solo en el 3,5 % de sus partidas no es un fallo: lleva Sorpresa en su lugar. Protección es el movimiento más usado de toda la base (7.659 acciones) y aun así es minoritario en ese Pokémon concreto.

## Corrección de paso: las habilidades se mostraban en crudo

La columna `species.abilities` guarda slugs (`grassysurge`), y tanto el desplegable del Constructor como la ficha de la Pokédex los pintaban tal cual. Se resuelven ahora contra la tabla `abilities` y salen con su nombre traducido.

## Limitaciones que quedan

- Las revelaciones de una Mega se guardan bajo la forma Mega. Las consultas de conjunto agrupan la familia (especie + sus formas), pero cualquier otra consulta que vaya por especie exacta verá las dos separadas.
- `Frisk` revela el objeto del rival y se acepta; si en alguna regulación futura entra una habilidad parecida habrá que añadirla a la lista.
- El reparto de SP no conoce el equipo: no ajusta velocidad para adelantar a un rival concreto ni reparte pensando en un ataque que se quiera aguantar.
