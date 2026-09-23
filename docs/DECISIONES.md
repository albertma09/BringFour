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

## Corrección de usabilidad: las recomendaciones no se veían

Tres cosas las escondían, y las tres eran de diseño y no de datos.

**El corte de ELO vaciaba los paneles sin decir por qué.** El selector es global y se guarda, así que bastaba haberlo tocado en otra sección para que el Constructor arrastrase un filtro que deja la muestra en nada:

```
elo_bucket 0     → 3.112 replays
elo_bucket 1500  →    32 replays
elo_bucket 1630  →     0
elo_bucket 1760  →     0
```

El rating más alto de toda la base es **1663**: por encima de 1630 no hay nada y nunca lo habrá mientras la fuente sea el ladder de Showdown. Ahora `/api/formats` devuelve el número de partidas de cada corte, el selector lo enseña entre paréntesis y deshabilita los tramos vacíos, y el aviso de muestra insuficiente explica que la causa es el filtro y ofrece quitarlo de un clic. Un filtro que te deja a ciegas en silencio es peor que no tenerlo.

**El panel de compañeros estaba debajo de las seis tarjetas**, o sea fuera de pantalla. Pasa a tira horizontal compacta entre el equipo y los huecos.

**Las sugerencias de conjunto y SP estaban plegadas.** Ahora se abren solas cuando al hueco le falta algo (habilidad, objeto, los cuatro movimientos o SP sin repartir) y se cierran solas al completarlo, salvo que el usuario haya tocado el desplegable, en cuyo caso manda su decisión hasta que cambie de Pokémon.

---

# Fase 5 — Taller para Pokémon sin datos (22-sep-2026)

## El hueco que ninguna herramienta cubre

Todas las herramientas del sector son *usage-driven*: dicen qué se usa. Fuera del meta se quedan mudas, y ahí es justo donde un jugador necesita ayuda. Si eliges Clawitzer no hay registros, y no hay nadie que te diga qué ataques ponerle.

**La asimetría que lo hace viable:** el Pokémon raro no tiene datos, pero **lo que se va a encontrar enfrente sí los tiene**. Lo deducido y lo medido se combinan, y en pantalla se distinguen siempre.

## Lo que sale de la mecánica, no del uso

Ordenar los ataques de Clawitzer por potencia bruta da Giga Impacto e Hiperrayo, que son basura. La respuesta correcta sale de cruzar el flag `pulse` de los movimientos con su habilidad **Megadisparador**, que multiplica por 1,5 los movimientos pulso:

| Movimiento | Base | Con la habilidad |
|---|---|---|
| Pulso Dragón | 85 | 127 |
| Esfera Aural | 80 | 120 (y no falla nunca) |
| Pulso Umbrío | 80 | 120 |
| Hidropulso | 60 | 90 → 135 con STAB |

Los multiplicadores de habilidad **salen de los flags, no de una lista de casos especiales**: `pulse`→Megadisparador, `punch`→Puño Férreo, `bite`→Mandíbula Fuerte, `slicing`→Cortante, `sound`→Punk Rock, potencia ≤60→Experto. Si Showdown añade un movimiento pulso nuevo, entra solo.

## Cuatro reglas que salieron de probar y ver fallos

Cada una se añadió porque la anterior daba un resultado malo con un Pokémon real:

1. **Los ataques de área que pegan al compañero no entran en el conjunto propuesto.** Con Surf a 202,5 de potencia efectiva, Clawitzer lo elegía por encima de Hidropulso — ignorando que Surf también revienta a tu propio compañero. Quedan como alternativa, avisando.
2. **Umbral de viabilidad.** El algoritmo metía Disparo Lodo (52 de potencia efectiva) porque sumaba cobertura, aunque no hiciera nada. Solo se consideran ataques que lleguen al 60 % del mejor.
3. **El umbral se mide por objetivo, no por daño total.** Con Gholdengo, Tesoro Ambicioso (área, 256,5) hacía que ningún ataque normal pareciera viable y el conjunto se llenaba de pantallas. Comparando por objetivo entra Bola Sombra, que es lo correcto.
4. **Fuera los ataques de prioridad ≤ −3.** Metía Puño Certero a Rillaboom y a Incineroar: técnicamente está en su repertorio, pero falla si te pegan antes.

## La honestidad de las amenazas

La primera versión decía *"a Clawitzer le jode Garchomp con Colmillo Rayo"*. Está en el repertorio de Garchomp, pero **nadie lo lleva**. Eso es inventarse una amenaza.

Ahora se cruza con `replay_actions`, que sí es dato observado, y se separa en dos listas:

- **Confirmadas** — se ha visto usar el golpe: Rillaboom con Hierba Parásita (792 veces), Archaludon con Disparo Eléctrico (715). Eso es una amenaza de verdad.
- **Posibles** — lo aprenden pero no se ha visto a nadie llevarlo. Se muestran aparte y en gris.

## La velocidad deja de ser una opinión

El `SpreadAdvisor` de la fase anterior decidía rápido/lento con unos umbrales 80/55 inventados. Ahora se mide el **percentil de velocidad contra lo que se trae de verdad**, pesando cada especie por cuánto se juega. Clawitzer con 59 de base está en el percentil 8,9: le gana el 91 % de lo que se va a encontrar. Eso ya no es criterio, es cuenta. El alineamiento que elige el usuario sigue mandando sobre todo lo demás.

## Compañeros por encaje

Sin coapariciones no hay costumbre que medir, así que se propone por estructura, con tres criterios y cada uno explicado: quién **tapa** sus debilidades, quién le **da el turno** (Viento Afín, Espacio Raro, Viento Hielo…) si su velocidad lo pide, y quién **llega** a lo que él no alcanza. Los candidatos salen siempre de lo que sí se juega, para que la sugerencia sea montable.

## Limitaciones conocidas

- **No se calcula daño real.** Se ordena por potencia efectiva, que es otra cosa. El cálculo exacto sigue siendo `@smogon/calc` en el navegador.
- **No se modelan terrenos ni climas.** Por eso a Rillaboom no se le propone Hierba Parásita: su prioridad +1 depende del Campo de Hierba que él mismo pone, y eso el modelo no lo sabe.
- Las habilidades condicionales (Espesura, Mar Llamas y compañía, que solo actúan a poca vida) no se cuentan.
- El conjunto propuesto no conoce el resto del equipo: no ajusta la cobertura a lo que ya llevan los otros cinco.

## El selector de movimientos no enseñaba lo elegido

La lista de movimientos del Constructor cortaba en **40** y venía ordenada por el nombre **en inglés**, aunque en pantalla se muestre el nombre traducido. Con Rillaboom, que tiene 67 movimientos legales, eso significaba:

```
Mazazo          posicion 65  -> no se ve nunca
Ida y Vuelta    posicion 63  -> no se ve nunca
```

El flujo era: buscas el movimiento, lo marcas, borras la búsqueda — y desaparece de la vista. **Se guardaba bien, pero no había forma de verlo**, así que parecía que no. El único indicio era el contador `x/4`.

Arreglado en tres partes:

- Los movimientos elegidos salen en **su propia lista arriba, siempre visible**, cada uno con su botón de quitar.
- Se acabó el corte de 40: la lista es desplazable y muestra todo el repertorio legal, **ordenado por el nombre del idioma activo**.
- Al llegar a cuatro, la búsqueda se sustituye por un aviso de que hay que quitar uno para cambiarlo. Antes el clic simplemente no hacía nada, sin explicación.

De paso, al cambiar de formato ahora se **podan** el movimiento, el objeto y la habilidad que dejen de ser legales en la regulación nueva. Antes se quedaban guardados en el equipo pero no se pintaban, así que el contador decía cuatro y se veían tres.

---

# Fase 6 — Compañeros por papel y tasa de cierre (22-sep-2026)

## El problema: todo eran apoyos

Los tres criterios estructurales de la fase anterior (tapa las debilidades, da el turno, redirige) premiaban **solo a los Pokémon de apoyo**. Para Clawitzer salían cuatro con Espacio Raro y poco más, cuando lo que hace falta es poder elegir también otro atacante, o ver cuál de los candidatos es el que cierra partidas.

Ahora se devuelve **agrupado por papel**: para rematar · para el control de velocidad · para sostener · otros atacantes físicos · otros atacantes especiales.

**Decisión clave: los cubos de ataque no exigen razón estructural.** Un atacante puro puede no tapar ninguna debilidad ni aportar control de velocidad, y si se exigiera razón se quedaría fuera justo lo que se estaba pidiendo. La razón estructural sigue existiendo, pero como criterio de orden y no de entrada.

Los cubos se rellenan en orden **remate → velocidad → apoyo → físico → especial**, y quien ya ha salido en un cubo baja al final de los siguientes. Sin eso, Volcarona y Sinistcha aparecían idénticos en tres secciones seguidas, que es lo contrario de dar variedad.

## La tasa de cierre: una *win condition* medida

De las partidas **ganadas** llevando a un Pokémon, en cuántas estaba **en el campo al acabar**. El denominador son solo partidas ganadas, para que no sea una tasa de victoria disfrazada.

| Especie | Ganadas | Cierra | Tasa |
|---|---|---|---|
| Archaludon | 283 | 210 | 74,2 % |
| Golisopod | 264 | 193 | 73,1 % |
| Kingambit | 278 | 155 | 55,8 % |
| Sneasler | 562 | 271 | 48,2 % |
| Rillaboom | 652 | 299 | 45,9 % |
| Indeedee-F | 571 | 182 | 31,9 % |

Rillaboom se trae más que nadie y casi nunca es el que cierra: hace su trabajo pronto. Archaludon es lo contrario.

**Esto describe correlación, no causa, y el texto de la interfaz lo dice.** Un Pokémon duro sobrevive hasta el final sin ser quien remata, y uno que entra pronto y cumple sale bajo sin ser peor. La cifra dice *cuándo* aporta, no cuánto vale.

## Un fallo que habría hecho parecer inútil a toda mega

La primera versión daba **Salamence 4,3 %** y **Metagross 7,5 %**. La causa: `replay_teams` guarda la forma base, pero el estado del campo al final guarda la forma **mega**, así que el cruce no encontraba nada.

Al arreglarlo mapeando formas a su base, se rompió lo contrario: **Indeedee-F pasó a 0 %**, porque Indeedee-F tiene `base_form_id` apuntando a Indeedee pero **no es una mega**: es una especie distinta que aparece como tal en el Team Preview. La condición correcta es mapear solo cuando `is_mega`.

| | antes | tras el primer arreglo | correcto |
|---|---|---|---|
| Salamence | 4,3 % | 52,7 % | 52,7 % |
| Metagross | 7,5 % | 50,9 % | 50,9 % |
| Indeedee-F | 31,9 % | **0 %** | 31,9 % |

## Los papeles salen de la mecánica

El eje ofensivo, de las estadísticas base. Las etiquetas de función, del repertorio y de la habilidad: control de velocidad, redirección, pantallas, clima, terreno, y **remate** solo si sube *y además* pega. Los repertorios se cargan en bloque, una consulta para las ~100 especies del meta.

Se mantiene la separación de siempre: **el papel se deduce, la tasa de cierre se mide**, y en pantalla se distinguen.

## Limitaciones que quedan

- El ancla de la recomendación es **un solo Pokémon** (el último tocado), no el equipo entero. Los ya elegidos se excluyen, pero no se suman sus debilidades para buscar quien las tape todas.
- La tasa de cierre no distingue ganar con seis en pie de ganar con uno: mira quién está en el campo, no cuántos quedaban.
- Sigue pendiente que el conjunto propuesto conozca al resto del equipo.

---

# Fase 7 — Análisis del equipo completo (23-sep-2026)

## De un ancla a los seis

Las recomendaciones colgaban del **último Pokémon tocado**, que es una elección arbitraria: montas un equipo, no un Pokémon. Ahora `GET /api/build/team?equipo=a,b,c` analiza el conjunto y los compañeros se piden con `GET /api/build/team/partners`.

## Las debilidades se pesan por lo que se lanza de verdad

Tratar los 18 tipos por igual es falso. Contando los **40.691 ataques** registrados en los replays:

```
Normal 14,5%  ·  Fuego 9,4%  ·  Lucha 7,3%  ·  Siniestro 7,1%  ·  Psíquico 6,7%
...
Veneno 2,8%   ·  Volador 2,4%
```

Una debilidad a Fuego pesa casi cuatro veces más que una a Volador. La gravedad de una debilidad compartida es `miembros afectados × cuota real del tipo`, no una cuenta abstracta de la tabla de tipos. El reparto se cachea una hora por formato y corte de ELO.

Las dos listas dicen cosas distintas y se presentan por separado: **debilidades compartidas** es recibir 2× (peligro real) y **sin resistir** es no llegar nunca a ½ (leve). Por eso tapar una compartida vale el triple que tapar un hueco al ordenar candidatos.

## La propuesta de cambio

Es lo más delicado que hace la herramienta: es la primera vez que dice *"yo quitaría a este"*. La forma de no cruzar la línea de `UNDERSTANDING > RECOMMENDATION` es que el criterio sea **medible y explicado**, no una nota de calidad:

1. **Aportación única** de cada miembro: resistencias que solo él da (ponderadas por uso) y papeles que solo él cubre.
2. **Parejas redundantes**: mismo eje ofensivo **y** debilidades en común.
3. **Hueco serio**: una debilidad compartida que no resiste nadie, o una resistencia que falta y pesa ≥ 4 % de los ataques, o un papel que no tiene nadie.
4. Solo propone si hay **las dos cosas**: redundancia y hueco. Si el equipo está equilibrado, lo dice y calla.
5. El sustituto tiene que **resistir** el tipo del hueco; si no, ni se ofrece.

Con cuatro Aguas (Clawitzer, Milotic, Pelipper, Basculegion):

```
sale: Clawitzer  ·  hace lo mismo que Milotic  ·  aporte único: 0
arregla: eléctrico, débiles 4 de 4, no lo resiste nadie, 5,5 % de los ataques
entran: Sinistcha, Sinistcha-Masterpiece, Scovillain, Rillaboom
```

Con un equipo variado (Rillaboom, Incineroar, Archaludon, Indeedee-F, Salamence, Gholdengo) hay **3 parejas parecidas y ninguna propuesta**, porque toda debilidad compartida tiene quien la resista. Eso es el comportamiento correcto.

El texto dice *"estos dos hacen lo mismo"*, nunca *"este es peor"*, y cierra con **"si el que sale te gusta, no lo saques"**.

## Amenazas al equipo

Rivales a los que **se ha visto usar de verdad** un golpe súper eficaz contra dos o más miembros, ordenados por cuántos tocan y por cuánto se traen. Se reutiliza la separación confirmado/posible de la fase 5: no se inventan amenazas de learnset.

## Limitaciones conocidas

- **Solo cuenta tipos y papeles, no daño real.** Que Milotic resista Fuego no significa que aguante a Charizard: eso depende del reparto de SP y del objeto. El aviso está en pantalla.
- El reparto físico/especial se mide por estadísticas base, no por los movimientos que el usuario haya elegido.
- No se mide la cobertura ofensiva del equipo (a quién no llegáis entre todos), porque sin saber los movimientos elegidos la estimación sería demasiado floja para publicarla.

---

# Códigos de equipo aportados por jugadores — investigado y aparcado (23-sep-2026)

**Decisión del usuario: no se implementa de momento.** Se retomará si la herramienta se publica y coge tracción, porque es una función que sin usuarios queda vacía y resta.

## Generar un código es imposible, no difícil

Se volvió a mirar porque PikaChampions anuncia *"copy a 10-character Replica Team Code"* como si lo generase desde el navegador. No puede ser, y la cuenta lo cierra:

```
10 caracteres → 36^10 = 3,6·10^15 → unos 52 bits
```

Lo que habría que codificar de **un solo** Pokémon: especie de ~400 (8,6 bits) + habilidad (1,6) + objeto de ~170 (7,4) + alineamiento de 25 (4,6) + 4 movimientos de ~100 (21,9) + reparto de 66 SP (~20) ≈ **64 bits**. Seis Pokémon son ~384 bits contra 52 disponibles. Solo las seis especies (6 × 8,6 = 52) ya llenan el código entero.

Es un identificador que apunta a algo guardado en los servidores de Nintendo. Serebii lo describe como **subir** el equipo y recibir un ID. Confirma lo que ya estaba en `CLAUDE.md`.

## Nadie verifica: lo que varía es de dónde sale la confianza

| Sitio | Señal de confianza |
|---|---|
| **op.gg** | Cuenta con perfil, votos positivos y fecha. Sin insignia de verificado, sin voto negativo, sin reportar |
| **Victory Road / PokeReplicas** | Procedencia: "es el equipo con el que Fulano ganó tal torneo". Su "verified" es trazabilidad, no comprobación |
| **Game8** | Tablón; el código es un campo opcional que pega el jugador |

Ninguno comprueba que el código cargue, porque no hay API de Champions.

## El fallo de diseño que se evitó

La primera propuesta llevaba votos de **"funciona / no funciona"**. Es mala idea por la mecánica del propio juego: el código solo carga **si ya tienes todos los Pokémon y objetos del equipo**. Un "no funciona" sería indistinguible de "me faltaba el Archaludon", así que el contador mediría el inventario del votante y no el código, y el auto-ocultado enterraría códigos buenos.

Si se retoma, el diseño correcto es: **solo confirmación positiva** (*"a mí me ha cargado"*) con fecha de la última, antigüedad visible como señal de que puede estar muerto, y reportar reservado a basura. Más una línea explicando por qué puede no cargarte aunque el código sea bueno, que es información que ninguna de las otras herramientas da.

## El desajuste de fondo

Los equipos que enseña BringFour salen de **replays de Showdown**, y esos jugadores no juegan a Champions: sus equipos no tienen código ni lo tendrán. Cualquier código que apareciese sería de alguien que haya montado esas seis especies por su cuenta.

Además, el identificador de equipo son **las seis especies ordenadas**, no una configuración: un código sería "de *un* equipo con estos seis", con otros objetos y otro reparto de SP. Eso habría que decirlo en pantalla.

---

# Fase 8 — Climas y terrenos (23-sep-2026)

## No es un detalle de nicho

Contando los `field_state` de los turnos ya guardados:

| | Partidas | |
|---|---|---|
| Con terreno | 2.125 / 3.134 | **68 %** |
| Con clima | 1.752 / 3.134 | **56 %** |

Campo de Hierba (50,9 % de las partidas) y Campo Psíquico (54,8 %) dominan; Campo Eléctrico es residual (6,5 %) y el de Niebla anecdótico (0,7 %). En clima: lluvia 33,6 %, sol 23,1 %, arena 20,9 %, nevada 13,5 %. Estas frecuencias son **dato medido** y salen etiquetadas como tal.

## El origen del fallo

**Hierba Parásita está guardada con prioridad 0.** Su +1 es lógica condicional dentro del código de Showdown, no un campo del JSON. Igual pasa con Voltio Cruel (dobla en Campo Eléctrico), Vasta Fuerza (sube en Campo Psíquico), Bola Clima y Pulso de Campo (doblan y cambian de tipo).

Por eso hay una **tabla escrita a mano** de ~15 movimientos condicionales. Es una diferencia importante respecto a los multiplicadores de habilidad, que salían de los flags y se mantienen solos: **esta hay que actualizarla** si Showdown añade movimientos de campo.

## El campo se autoactiva

La decisión que hace esto útil: **la habilidad del propio Pokémon pone su campo**, así que se analiza con él puesto. Rillaboom se calcula con Campo de Hierba, Pelipper con lluvia, Torkoal con sol.

Resultados, todos coincidiendo con lo que se juega de verdad:

```
Rillaboom  Protección · Mazazo (234) · Hierba Parásita (+1 prio) · Fuerza Bruta
Pelipper   Protección · Agua Lodosa (258) · Vendaval (165, no falla con lluvia)
Torkoal    Protección · Erupción (506) · Sofoco (263)
Ninetales-A  Ventisca (247, no falla con nevada)
```

## Tres reglas que hubo que añadir

1. **Hueco para la prioridad.** Hierba Parásita tiene 107 de potencia efectiva contra los 234 de Mazazo, así que por potencia nunca entraba. Pero pegar antes vale un hueco en dobles, así que un ataque prioritario con al menos el 40 % de la potencia del mejor se lleva sitio propio. El número de potencia **no se infla**: la prioridad se valora aparte y se muestra aparte.

2. **El umbral de viabilidad se mide sin el campo.** El ×1,5 del sol sobre Erupción disparaba el techo y dejaba fuera todo lo demás, llenando el conjunto de movimientos de estado. Se compara con la potencia sin campo y se ordena con ella.

3. **Se permite repetir tipo si cambia el alcance.** Un ataque de área y uno de objetivo único del mismo tipo hacen cosas distintas en dobles. Sin esto, Pelipper no podía llevar Agua Lodosa e Hidrobomba, que es su conjunto real.

## Un fallo que destapó el sol

Torkoal salía como atacante **físico**: tiene 85 de Ataque y 85 de Especial, y el desempate elegía físico a ciegas. Ahora, cuando la diferencia es de 10 o menos, **se decide mirando qué movimientos tiene de verdad en cada lado**. Torkoal pasa a especial y recibe Erupción, que es lo correcto.

## A nivel de equipo

- Quién pone clima y quién pone terreno, con su frecuencia real al lado.
- **Quién lo aprovecha**: Excadrill con Ímpetu Arena dobla velocidad si llevas Tyranitar.
- **Huérfanos**: alguien con Clorofila y nadie que ponga sol es un aviso.
- **Choques**: dos que ponen climas distintos se pisan.
- **Campo Psíquico anula la prioridad**, también la tuya, y eso se dice.
- Razón nueva para compañeros: **aprovecha el campo que ya pones**.

El reparto de SP se entera por la cadena: un Nado Rápido con lluvia deja de ser «lento» y no gasta puntos en correr.

## Limitaciones conocidas

- **El campo del rival no se modela.** Si el rival pone Campo Psíquico, tu Hierba Parásita pierde la prioridad. Eso es dinámica de combate, no de construcción.
- Los climas duran 5 turnos (8 con roca); aquí se tratan como permanentes, que es lo razonable cuando los pone una habilidad.
- Sigue sin calcularse daño real: un ×1,3 sobre potencia efectiva no es un cálculo de daño.

---

# Fase 9 — Cobertura ofensiva del equipo (23-sep-2026)

## El agujero que quedaba

El análisis de equipo decía **por dónde te entra a ti** (debilidades compartidas, tipos que nadie resiste, rivales que tocan a varios) y no decía nada de **a quién no llegas tú**. Un equipo podía tener seis atacantes sin un solo golpe que pegara súper eficaz a Garchomp y la herramienta no lo mencionaba.

Además el conjunto propuesto en el taller se calculaba para cada Pokémon **aislado**: optimizaba su cobertura personal sin saber qué llevaban los otros cinco.

## La decisión que sostiene todo: de dónde salen los tipos de ataque

Hay dos fuentes posibles y **no se mezclan sin decirlo**:

| Origen | Qué es | Cuándo se usa |
|---|---|---|
| `visto` | Tipos que se le han visto lanzar de verdad en `replay_actions`, con su `n` | Cuando el Pokémon supera el mínimo de muestra |
| `deducido` | Tipos del conjunto que propone el taller | Cuando no hay muestra (el caso Clawitzer) |

**No se usa el learnset.** Es la misma decisión que se tomó al separar amenazas `confirmadas` de `posibles`: que un Pokémon *pueda* aprender Colmillo Rayo no significa que nadie se lo ponga. El panel enseña el origen de cada miembro para que se vea cuál es dato y cuál es propuesta.

Dentro de lo visto se descarta el golpe anecdótico: un tipo cuenta si llega al 5 % de los ataques de esa especie, con suelo de 3 usos. Sin ese filtro, un Garra Ígnea suelto convertía a cualquiera en «cubre Acero».

## Qué se mide

- **Cobertura**: porcentaje de lo que se trae, ponderado por veces traído, al que alguien del equipo llega con ≥2×.
- **Intocables**: los que nadie toca súper eficaz, ordenados por peso, marcando además si son inmunes a alguno de tus golpes.
- **Qué tipo te falta**: se simulan los 18 tipos y se devuelven los 3 que más subirían la cobertura, con la ganancia en puntos.
- **Golpes repetidos**: tipos que llevan 3 o más miembros.

Verificado con un equipo real del meta (Rillaboom + Sneasler + Indeedee-F + Incineroar): **96,3 %**, y los tres intocables son Garchomp, Sableye y Gallade. Coincide: ese equipo no lleva Hielo, Dragón ni Hada. Y `mejor_anadido` dice hada +3,7 / dragón +3,1 / hielo +3,1.

## Dos ajustes que hubo que hacer

1. **Normal repetido no es una redundancia.** Salía `normal x4` porque casi todos llevan Sorpresa. La regla no es una excepción para Normal: un tipo solo entra en repetidos si es súper eficaz contra alguien del meta. Normal no lo es contra nadie, así que cae solo.

2. **El aviso de cobertura pobre solo aparece con 4 o más.** Con dos o tres Pokémon la cobertura baja es normal y el aviso sería ruido mientras montas.

## El conjunto ya conoce al resto del equipo

`SetBuilder::build()` acepta los huecos del equipo y el hueco de cobertura suma una bonificación por cuántos tapa. Es **desempate, no mandato**: el tope es de 5 puntos frente a una ganancia de cobertura que va de 0 a 100.

Barriendo los 40 Pokémon más traídos con los huecos de ese equipo, **cambian 2**:

```
Politoed    solo:   Agua Lodosa / Tierra Viva / Rayo Hielo
            equipo: Agua Lodosa / Rayo Hielo / Tierra Viva     (mismo conjunto, otro orden)

Grimmsnarl  solo:   Juego Sucio / Golpe Bajo / Fuerza Bruta
            equipo: Juego Sucio / Golpe Bajo / Carantoña       (Hada pega a Garchomp y Gallade)
```

2 de 40 es exactamente el comportamiento buscado: no reescribe conjuntos, corrige el caso en que dos opciones estaban empatadas.

## Compañeros que llegan donde tú no

Razón nueva `alcanza`: el candidato golpea ≥2× a alguno de tus intocables, usando sus tipos **vistos**. Vale 3 por cada uno resuelto con tope de 9, para que no aplaste a las razones defensivas. Con el equipo de prueba, los que resuelven los tres huecos son todos Hada — Mawile, Klefki, Tinkaton, Whimsicott, Primarina, Floette-Eternal.

De paso: las razones de compañero se mostraban como una lista pelada de nombres sin explicar por qué. Ahora se traduce la clave, así que se lee «le pega a Garchomp, Sableye, Gallade» en vez de «Garchomp, Sableye, Gallade».

## Limitaciones conocidas

- **≥2× es alcance de tipo, no daño.** Un súper eficaz contra un muro con 250 de defensa sigue sin matar. El cálculo de daño real sigue en `@smogon/calc`, en el navegador.
- **Repetir tipo no es un fallo** y así se dice: puede ser el plan. Solo importa si además no llegas a nadie.
- El conjunto deducido de un miembro sin muestra entra en la cobertura del equipo: es una propuesta, y va etiquetada como tal.
