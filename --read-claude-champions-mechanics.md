# MECÁNICAS DE POKÉMON CHAMPIONS Y FUENTES DE DATOS

Documento de referencia para sesiones futuras. **Todo lo que hay aquí está verificado contra los datos reales de Showdown o contra la API en vivo**, no contra artículos. Fecha de verificación: 22-sep-2026.

No re-investigar nada de este documento. Si algo parece desactualizado, comprobarlo contra la fuente citada, no contra una web.

---

## 1. SISTEMA DE ESTADÍSTICAS

### 1.1 Reglas

- **Nivel siempre 50** en combate.
- **No hay IVs.** Todo Pokémon actúa como si tuviera 31 en las seis.
- **No hay EVs.** Se sustituyen por **Stat Points (SP)**.
- **66 SP totales**, con **tope duro de 32 SP por estadística**.
- **1 SP = +1 punto de estadística** a nivel 50.
- Las naturalezas se llaman **Stat Alignments**: ±10%, **25 en total, 5 de ellas neutras**. Verificado contra `Dex.natures.all()`, no son 21 como dicen algunas guías.
- Reentrenar SP cuesta **Victory Points (VP)**. No hay breeding.

### 1.2 Fórmulas

```
HP           = base + 75 + SP
Resto stats  = (base + 20 + SP) × alignment
```

Son idénticas a la fórmula clásica a nivel 50 con 31 IVs:
`floor((2·base + 31)/2) + 50 + 10 = base + 75` y `floor((2·base + 31)/2) + 5 = base + 20`.

⚠️ **Usar aritmética entera, nunca coma flotante.** El alignment se aplica como en los juegos:

```php
$plus  = intdiv($value * 11, 10);
$minus = intdiv($value * 9, 10);
```

Con floats, casos como `(85+20) * 1.1` dan `115.50000000000001` y otros pueden caer a `x.9999`, produciendo un punto de diferencia. Implementado así en `app/Domain/Stats/StatCalculator.php`.

### 1.3 Conversión con EVs (para importar spreads antiguos)

- **1 SP = 8 EVs**, salvo el primer SP de cada estadística, que cuesta 4.
- Por tanto **32 SP = 4 + 31×8 = 252 EVs**, exactamente el tope clásico por estadística.
- ⚠️ **El clásico 4/252/252 son 65 SP, no 66.** Al importar un paste de Showdown queda **1 SP libre**. Hay que avisar al usuario en la UI, no rellenarlo en silencio.
- 66 SP no equivalen a 508 EVs: Champions es ligeramente más generoso que el presupuesto clásico. No tratar los dos sistemas como equivalentes exactos.

### 1.4 Consecuencia de diseño

El tope de 32 hace **imposible** el clásico "252/252/4". El espacio de spreads es finito y enumerable (66 puntos, 6 estadísticas, tope 32), así que **un spread solver determinista es viable de verdad**, no una aproximación.

---

## 2. FUENTE DE DATOS: LOS MODS DE SHOWDOWN

### 2.1 Cómo se obtienen

El paquete npm `pokemon-showdown` **va por detrás de master** (tenía `championsregma` cuando master ya iba por `championsregmb`). Hay que instalar desde GitHub y compilar:

```bash
npm install github:smogon/pokemon-showdown     # ~50 s
cd node_modules/pokemon-showdown && node build # ~10 s, genera dist/
```

Sin `node build` no hay `dist/` y `require('pokemon-showdown')` falla con `MODULE_NOT_FOUND`.

Es CommonJS: desde ESM hay que hacer `import ps from 'pokemon-showdown'; const {Dex} = ps;`. El named import falla.

### 2.2 ⚠️ Cómo se organizan las regulaciones (lo más importante del documento)

> **El mod base `champions` ES SIEMPRE LA REGULACIÓN ACTIVA. Las regulaciones pasadas se congelan en su propio mod.**

Estado a 22-sep-2026:

| Mod | Qué es |
|---|---|
| `champions` | **Regulación M-C (activa)** |
| `championsregmb` | Regulación M-B (congelada) |

No existe `championsregmc` y **no debe existir mientras M-C esté activa**.

**Qué pasará cuando llegue M-D (previsible tras el 2-dic-2026):** aparecerá `championsregmc` con el contenido actual y el mod base `champions` pasará a ser M-D. **Hay que actualizar `ingest/config.mjs`**: `M-C` cambia su `mod` de `champions` a `championsregmc`, y se añade `M-D` con `mod: 'champions'`. Si no se hace, los datos de M-C se corromperán silenciosamente con el contenido de M-D. El workflow `ingest-data.yml` avisa de esto en el cuerpo del PR.

### 2.3 Formatos verificados

Todos existen en `Dex.formats`:

| showdownId | Tipo | Mod |
|---|---|---|
| `gen9championsvgc2026regmc` | doubles | champions |
| `gen9championsvgc2026regmcbo3` | doubles | champions |
| `gen9championsbssregmc` | singles | champions |
| `gen9championsou` | singles | champions |
| `gen9championsvgc2026regmb` | doubles | championsregmb |
| `gen9championsvgc2026regmbbo3` | doubles | championsregmb |

### 2.4 Criterios de legalidad

| Criterio | Significado | M-C |
|---|---|---|
| `isNonstandard === null` | Existe en la regulación (incluye formas de combate) | 392 |
| `... && tier !== 'Illegal'` | **Construible** en un equipo | **382** |

Las 10 de diferencia son **formas de transformación en combate**: Minior-Meteor, Eiscue-Noice, Meloetta-Pirouette, Cramorant-Gulping/Gorging, las Ogerpon-Tera y Terapagos-Terastal. Existen en batalla pero no se pueden poner en un equipo.

➡️ Por eso el modelo separa **catálogo global** (`species`, con flag `is_buildable`) de **legalidad por regulación** (tabla `legality`). El parser de replays necesitará el catálogo completo o no podrá resolver las formas que aparecen en `|detailschange|`.

### 2.5 Trampas reales encontradas (no volver a tropezar)

1. **Una forma legal puede tener una forma base ilegal.** `Floette-Eternal` y `Floette-Mega` son legales; **`Floette` a secas no lo es**. El catálogo debe incluir las formas base referenciadas aunque no sean legales, o se rompen las claves foráneas.

2. **`item.megaStone` es un MAPA, no un string.** En Champions vale `{"Abomasnow": "Abomasnow-Mega"}`. Es así porque existen Z-Megas y una piedra puede mapear varias especies base. Por eso la columna es `items.mega_evolutions` (jsonb), no un slug.

3. **Hay habilidades referenciadas que están marcadas `Future`.** `Lucario-Mega-Z` es legal pero su habilidad `Aura Guard` tiene `isNonstandard: 'Future'`. Es una inconsistencia de la propia fuente. La ingesta añade al catálogo cualquier habilidad u objeto referenciado por una especie disponible, independientemente de su `isNonstandard`.

4. **Las descripciones NO están en los datos del simulador.** Viven en `dist/data/text/{moves,abilities,items}.js`. `dex.moves.get(x).shortDesc` devuelve `undefined`.

5. **Los textos traen overrides específicos de Champions.** Cada entrada puede tener una clave `champions` que pisa la descripción base. Ejemplo real y mecánicamente relevante:
   - Base: Fake Out *"Fails unless it is the user's first turn on the field."*
   - Champions: *"This move **cannot be selected** unless it is the user's first turn on the field."*

   No es cosmético: en Champions Fake Out ni siquiera aparece como opción. **Siempre preferir el override `champions`.**

6. **Hay nombres traducidos al español** en `dist/data/text/es/`. Solo nombres, las descripciones vienen `null`. Cubre 511 movimientos, 313 habilidades, 88 objetos y 47 especies (el resto coinciden con el inglés). Se exportan a `data/names.es.json`.

### 2.6 Conteos actuales (línea base para detectar roturas)

| | Catálogo | M-C legal | M-B legal |
|---|---|---|---|
| Especies | 399 (382 construibles) | 382 | 347 |
| Movimientos | 515 | 515 | 515 |
| Habilidades | 317 | 317 | 316 |
| Objetos | 169 | 166 | 148 |
| Alignments | 25 | — | — |
| Pares de learnset | — | 23.361 | 21.277 |

M-C tiene **35 especies legales más que M-B**, consistente con los "34 Pokémon + 6 Megas" anunciados.

`ingest/schema.mjs` falla en CI si estos números caen por debajo de mínimos razonables.

---

## 3. FUENTE DE DATOS: REPLAYS DE SHOWDOWN

### 3.1 API

```
https://replay.pokemonshowdown.com/search.json?format=<formatId>
https://replay.pokemonshowdown.com/search.json?format=<formatId>&before=<uploadtime>
https://replay.pokemonshowdown.com/<replayId>.json
```

- ⚠️ **Requiere cabecera `User-Agent`. Sin ella devuelve 403.** `curl` funciona por defecto, `urllib` de Python no, `fetch` de Node sí si se la pones.
- **51 resultados por página.** Se pagina con `before` = `uploadtime` del último.
- `Access-Control-Allow-Origin: *`. Sin límites de tasa declarados: aun así, ir despacio (350 ms entre peticiones).
- El `.json` incluye `log` e `inputlog`.

### 3.2 Volumen medido (22-sep-2026)

| Formato | Replays/día |
|---|---|
| `gen9championsvgc2026regmc` | **~2.716** |
| `gen9championsvgc2026regmcbo3` | ~625 |
| `gen9championsou` | ~677 |

**Backfill: funciona.** 25 páginas seguidas hacia atrás sin corte ni error (0,47 días de profundidad). Recuperar una regulación entera es viable como trabajo puntual. Por tanto **no es cierto que cada día sin recolectar sea dato perdido irrecuperable**; sí conviene empezar pronto porque los replays pueden borrarse con el tiempo.

**Compresión: 13%.** Un día de un formato pasa de 141 KB a 18 KB con gzip -9.

### 3.3 Qué contiene el log

Protocolo de texto, una directiva por línea. Verificado sobre partidas reales:

```
|gametype|doubles
|player|p1|<nombre>|<avatar>|<rating>
|rule|Species Clause: Limit one of each Pokémon
|clearpoke
|poke|p1|Rillaboom, L50, F|        ← los 6 del Team Preview, de ambos
|teampreview|4
|teamsize|p1|4                      ← cuántos trajo
|switch|p1a: Sneasler|...|100/100   ← los leads exactos
|-ability|p1b: Salamence|Intimidate|boost
|turn|1
|t:|1790057229                      ← timestamp: tiempo de decisión
|detailschange|p1a: Floette|Floette-Mega, L50, F
|-mega|p1a: Floette|Floette|Floettite
|move|p1a: Floette|Calm Mind|p1a: Floette
|move|p1b: Rillaboom|Wood Hammer|p2a: Milotic
|-supereffective|p2a: Milotic|1
|-damage|p2a: Milotic|1/100
|-sidestart|p2: <jugador>|move: Tailwind
|-weather|RainDance|[upkeep]
|-heal|p1b: Rillaboom|74/100|[from] Grassy Terrain
|-enditem|p2b: Pelipper|Focus Sash
|faint|p2a: Milotic
|upkeep
|turn|2
|win|<jugador>
```

Directivas vistas en una partida de 12 turnos: `move`, `switch`, `-damage`, `-heal`, `-boost`, `-unboost`, `-ability`, `-activate`, `-start`, `-end`, `-fieldstart`, `-fieldend`, `-sidestart`, `-sideend`, `-weather`, `-singleturn`, `-enditem`, `-mega`, `detailschange`, `faint`, `turn`, `upkeep`, `t:`.

⚠️ **En `|poke|` aparece el nombre de la forma base** (`Floette`), y la forma real solo se revela con `|detailschange|`. El parser debe tenerlo en cuenta.

### 3.4 Dónde se guarda

`data/raw/replays/<formatId>/<YYYY-MM-DD>.jsonl` (el día en curso), comprimido a `.jsonl.gz` cuando el día termina. `seen.txt` por formato para deduplicar y `state.json` con el último `uploadtime` por formato.

---

## 4. LO QUE CHAMPIONS **NO** DA

- ❌ **No exporta logs de combate.** El "View Log" que añadió Reg M-C es solo del combate en curso: no se guarda, no se exporta, no hay replays. (La referencia a un "botón de exportar" que circula por ahí es de **Pokémon TCG Live**, no de Champions.)
- ❌ **No hay API oficial.**
- ❌ **Los Replica Team codes (10 caracteres) son opacos y se resuelven en servidor.** No se pueden decodificar ni generar localmente. El import/export tiene que ser por texto tipo Showdown paste.
- ✅ El juego **sí** muestra usage stats oficiales en `Battle > Battle Data`, no extraíbles. Motivo extra para que el producto no sean usage stats.

---

## 5. ENTORNO DE DESARROLLO

⚠️ **El `php` del PATH es 7.4 (WAMP, proyecto del trabajo). No tocarlo ni cambiar la configuración global de WAMP.**

```
PHP        C:/wamp64/bin/php/php8.3.6/php.exe -c php.ini
Composer   php.exe -c php.ini "$LOCALAPPDATA/ComposerSetup/bin/composer.phar"
Atajo      ./dev.ps1 artisan|composer|test|php|up|down|psql
PostgreSQL Docker, contenedor turnzero-db, puerto host 5434 (el 5433 lo usa scoreboard-db)
Node       v22 local, solo build-time
```

Laravel 13.32 · PHP 8.3.6 · PostgreSQL 16.15 · Pest 4.7.

**Pest 5 no se puede usar:** requiere PHP 8.4 y WAMP solo llega a 8.3.6.

Base de datos de test aparte: `turnzero_test`, configurada en `phpunit.xml`. Los tests Feature usan `DatabaseTransactions`.

```bash
node ingest/showdown-data.mjs        # regenera data/
node ingest/replay-collector.mjs     # recolecta replays
./dev.ps1 artisan data:sync          # data/ -> PostgreSQL
./dev.ps1 test
```

---

## 6. REFERENCIA RÁPIDA DE RUTAS

| Qué | Dónde |
|---|---|
| Configuración de regulaciones y formatos | `ingest/config.mjs` |
| Ingesta de catálogo y legalidad | `ingest/showdown-data.mjs` |
| Validación de esquema de la fuente | `ingest/schema.mjs` |
| Recolector de replays | `ingest/replay-collector.mjs` |
| Datos generados (commiteados) | `data/` |
| Replays crudos (fuera de git) | `data/raw/replays/` |
| Carga a PostgreSQL | `app/Console/Commands/DataSync.php` |
| Fórmulas de estadísticas | `app/Domain/Stats/StatCalculator.php` |
| Validación de SP | `app/Domain/Stats/SpSpread.php` |
| Esquema | `database/migrations/2026_09_22_*` |
| Tests | `tests/Unit/`, `tests/Feature/` |

---

## 7. REGLAS DE RIGOR (no negociables)

- **N mínimo = 30** para mostrar un porcentaje. Por debajo, no se muestra.
- **Mostrar siempre el N** junto al porcentaje.
- **Segmentar por ELO** con los cortes de Smogon: 0 / 1500 / 1630 / 1760.
- **Etiquetar siempre la fuente.** Los datos de comportamiento son de Showdown, no del ladder de Champions: población distinta y ELO medio bajo (~1.180 en Reg M-C).
- El copy nunca dice "deberías". Dice qué hace la gente y cuáles son las consecuencias de cada línea.
