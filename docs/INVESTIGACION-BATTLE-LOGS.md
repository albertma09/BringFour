# INVESTIGACIÓN — LOGS DE COMBATE Y DATOS DE COMPORTAMIENTO

**Fecha:** 22-sep-2026
**Origen:** idea del usuario — *"sacar los logs de combate para analizar qué suele hacer la gente ante según qué situaciones; si sacan X Pokémon, el 68% de las veces hacen tal movimiento"*
**Veredicto:** ✅ **La idea es viable y es probablemente la mejor del proyecto.** Pero no por la vía que pensábamos.

---

## 1. RESUMEN

| Pregunta | Respuesta |
|---|---|
| ¿Champions exporta logs de combate? | ❌ **No.** El "View Log" de Reg M-C es solo durante el combate, no se guarda ni se exporta |
| ¿Hay sistema de replays en Champions? | ❌ No. Es la petición más votada de la comunidad desde el lanzamiento |
| ¿Hay alguna forma legítima de sacar datos del juego? | ❌ No sin OCR de vídeo o interceptar tráfico. Ambas descartadas |
| ¿Existe la fuente que necesitamos? | ✅ **Sí: los replays públicos de Pokémon Showdown**, con API documentada |
| ¿Hay volumen suficiente? | ✅ **~2.200 replays/día solo en Reg M-C**, medido hoy |
| ¿Contienen lo que necesitamos? | ✅ **Sí, y más de lo que pedías.** Verificado sobre replays reales |
| ¿Alguien más lo está explotando así? | ❌ **No.** Nadie publica datos de comportamiento condicional |

---

## 2. CORRECCIÓN: CHAMPIONS NO EXPORTA LOGS

Primero di por buena una referencia a un botón de exportar. Era de un hilo sobre **Pokémon TCG Live**, no sobre Champions. Verificado y corregido:

**Lo que sí añadió la actualización de Reg M-C:** una función **View Log** que muestra el historial de acciones — cada movimiento, cambio y decisión — **del combate en curso**.

**Sus límites:**
- Solo el combate **activo**. Al salir, se pierde.
- **No guarda** una copia de la partida.
- **No hay botón de exportar ni de copiar.**
- **No hay sistema de replays.** Sigue siendo la petición más pedida desde abril.

**Dato lateral interesante:** Champions **sí** tiene estadísticas oficiales in-game en `Battle > Battle Data > Ranked Battles / Online Competitions`: movimientos más usados por Pokémon, objetos, compañeros frecuentes y ranking de uso, con toggle singles/dobles.

> ⚠️ **Esto tiene una implicación estratégica incómoda:** el propio juego regala estadísticas de uso, gratis, sin salir de la app. **Las usage stats están comoditizadas.** Refuerza la conclusión de la Fase 0: un Meta Explorer de uso puro no es un producto. Lo que nadie tiene es el *comportamiento*.

### 2.1 Vías descartadas para sacar datos del juego

| Vía | Por qué se descarta |
|---|---|
| **OCR de vídeo / captura de pantalla** | Es lo que hace [pokemon_champions_battle_logger](https://github.com/fufufukakaka/pokemon_champions_battle_logger): lee la OBS Virtual Camera o un vídeo grabado. Funciona, pero su licencia es **"All Rights Reserved", solo uso personal no comercial**. Además exige que el usuario grabe con OBS: fricción enorme para una web. **No usar ni su código ni su enfoque como pilar** |
| **Interceptar tráfico del cliente** | Violación directa de los ToS, riesgo de baneo para nuestros usuarios y exposición legal para nosotros. **Descartado sin discusión** |
| **Pedir al usuario que transcriba** | Inviable |

---

## 3. LA VÍA BUENA: REPLAYS DE POKÉMON SHOWDOWN

### 3.1 API verificada hoy

Endpoints (documentados en `WEB-API.md` del cliente oficial, todos con `Access-Control-Allow-Origin: *`):

```
https://replay.pokemonshowdown.com/search.json?format=gen9championsvgc2026regmc
https://replay.pokemonshowdown.com/search.json?format=...&before=<uploadtime>   (paginación)
https://replay.pokemonshowdown.com/<replay-id>.json                             (replay completo)
```

- Máximo **51 resultados por página**, se pagina con `before=<uploadtime del último>`.
- El `.json` incluye **`log` e `inputlog`**; los endpoints `.log` y `.inputlog` son solo comodidad.
- ⚠️ **Requiere cabecera `User-Agent`.** Sin ella devuelve **403 Forbidden** (comprobado: `curl` funciona, `urllib` por defecto no).

### 3.2 Volumen real (medido el 22-sep-2026)

| Formato | Replays/día estimados | Con ELO | ELO medio |
|---|---|---|---|
| `gen9championsvgc2026regmc` | **~2.209** | 51/51 | 1.184 |
| `gen9championsvgc2026regmcbo3` | ~625 | 16/51 | 1.091 |
| `gen9championsou` (singles) | ~677 | 51/51 | 1.154 |

**~2.800 replays/día en formatos VGC ≈ 85.000/mes.** Muestra más que suficiente para estadística condicional… con matices importantes (sección 5).

### 3.3 Qué contiene un replay (verificado sobre partidas reales)

Extracto real de `gen9championsvgc2026regmc-2685621433`:

```
|gametype|doubles
|player|p1|yoskrildropithard|alder|1134
|rule|Species Clause: Limit one of each Pokémon
|rule|Item Clause: Limit 1 of each item
|clearpoke
|poke|p1|Rillaboom, L50, F|          ← los 6 del Team Preview
|poke|p1|Kingambit, L50, F|
  ...
|teampreview|4
|teamsize|p1|4                        ← cuántos trajo
|switch|p1a: Sneasler|...|100/100     ← los leads exactos
|-ability|p1b: Salamence|Intimidate|boost
|turn|1
|t:|1790057229                        ← timestamp de la decisión
|detailschange|p1a: Floette|Floette-Mega, L50, F
|-mega|p1a: Floette|Floette|Floettite
|move|p1a: Floette|Calm Mind|p1a: Floette
|move|p1b: Rillaboom|Wood Hammer|p2a: Milotic
|-supereffective|p2a: Milotic|1
|-damage|p2a: Milotic|1/100
|move|p2b: Pelipper|Tailwind|p2b: Pelipper
|-sidestart|p2: squiddy-biddy|move: Tailwind
|-weather|RainDance|[upkeep]
|-heal|p1b: Rillaboom|74/100|[from] Grassy Terrain
|upkeep
|turn|2
...
|-enditem|p2b: Pelipper|Focus Sash
|faint|p2a: Milotic
|win|squiddy-biddy
```

Tipos de mensaje encontrados en una partida de 12 turnos: `move`, `switch`, `-damage`, `-heal`, `-boost`, `-unboost`, `-ability`, `-activate`, `-start`, `-end`, `-fieldstart`, `-fieldend`, `-sidestart`, `-sideend`, `-weather`, `-singleturn`, `-enditem`, `-mega`, `detailschange`, `faint`, `turn`, `upkeep`, `t:`.

**Traducción a lo que importa:**

| Necesitamos | Lo da el replay |
|---|---|
| Equipos completos de ambos (6+6) | ✅ `\|clearpoke\|` + `\|poke\|` — **incluso en partidas abandonadas en el turno 1** |
| Qué 4 trajo cada uno | ✅ `\|teamsize\|` + `\|switch\|` inicial |
| Leads exactos | ✅ `\|switch\|` al `\|start\|` |
| Acción de cada jugador en cada turno, con objetivo | ✅ `\|move\|origen\|movimiento\|objetivo\|` |
| Estado del campo (TR, Tailwind, clima, terreno) | ✅ `\|-fieldstart\|`, `\|-sidestart\|`, `\|-weather\|` |
| HP en % en cada momento | ✅ `\|-damage\|` / `\|-heal\|` |
| Objetos y habilidades revelados | ✅ `\|-enditem\|`, `\|-ability\|`, `\|-activate\|` |
| Mega Evolución y cuándo se usó | ✅ `\|-mega\|` + `\|detailschange\|` |
| ELO de ambos jugadores | ✅ `\|player\|` y campo `rating` |
| Resultado | ✅ `\|win\|` |
| **Tiempo que tardó en decidir** | ✅ `\|t:\|` — **nadie usa esto** |

---

## 4. QUÉ PODEMOS CONSTRUIR CON ESTO

Tu intuición era "porcentaje de movimiento dado un Pokémon". Se puede ir bastante más lejos. Ordenado por valor / esfuerzo:

### 4.1 Bring rates y leads condicionados al matchup — 🥇 el más valioso

> *"Contra un equipo con Pelipper + Archaludon, el 74% de los jugadores traen Rillaboom, y el 61% lo sacan de lead."*

Sale de `|poke|` + `|teamsize|` + `|switch|` inicial. **Está en el 100% de los replays, incluso en los que acaban en el turno 1.** Es la muestra más grande y más barata de obtener.

Es además lo que más estudian los jugadores buenos: el Team Preview de 90 segundos es donde se gana o se pierde la partida. Ninguna herramienta lo publica.

### 4.2 Distribución de acción condicionada al estado

Tu idea original, formalizada:

```
P(acción | estado)
  estado = { mons activos, mons en banquillo, campo (TR/Tailwind/clima/terreno),
             turno, bucket de HP, ELO }
```

Ejemplos de output:
- *"Con Tailwind activo y tu Pokémon por debajo del 40% de HP, el 71% de los rivales usan Protect."*
- *"En el turno 1, con Incineroar en campo, el 83% usa Fake Out. De esos, el 64% lo dirige al Pokémon con más Ataque."*
- *"Cuando Farigiraf entra al campo, el 56% de los rivales cambia inmediatamente."*

### 4.3 Momento de la Mega Evolución

> *"El 78% mega-evoluciona en el turno 1. El 22% que espera gana un 9% más."*

Mecánica nueva del formato, sin literatura todavía. Nicho libre.

### 4.4 Tiempo de decisión como señal de dificultad

`|t:|` da los segundos entre turnos. Los turnos donde la gente **tarda mucho** son, por definición, los turnos difíciles.

> *"Este es uno de los turnos más difíciles del formato: la media de decisión aquí es de 21 segundos, frente a 7 en un turno normal."*

Es una forma de **descubrir automáticamente buenos escenarios para el Learning Mode** en vez de escribirlos a mano. Ataca directamente el riesgo nº10 de la Fase 0.

### 4.5 Alimentar Post-Battle Analysis con datos, no con opinión

Esto es lo que más cambia el producto. Tu brief pedía:

> *"No podemos saber con certeza qué habría hecho el rival. Pero podemos comparar las dos líneas."*

Con datos de comportamiento eso deja de ser retórica:

> *"En esa posición, el 68% de los jugadores de tu rango atacan y el 24% protegen. Tu lectura no era irrazonable; era la línea minoritaria. Esto es lo que pasaba en cada caso…"*

Sigue **sin decirle qué pulsar**. Le dice **qué información existía**. Es exactamente el principio fundamental del producto (§33), pero con evidencia detrás.

---

## 5. LÍMITES Y HONESTIDAD ESTADÍSTICA

Esto hay que implementarlo con disciplina o se convierte en desinformación con aspecto de ciencia.

| Límite | Realidad | Mitigación |
|---|---|---|
| **Showdown ≠ ladder de Champions** | Población distinta: anónima, sin coste de VP, más experimental, sin miedo a perder rango real | **Etiquetar SIEMPRE la fuente.** El brief ya lo exige (§13). Nunca presentarlo como "lo que hace la gente en Champions" |
| **Sesgo de subida** | Solo se analiza lo que alguien decidió subir. Se suben más victorias y partidas llamativas | Declararlo en la UI. Comparar distribución de ELO de replays vs ladder |
| **ELO medio bajo (~1.180)** | El comportamiento del ladder medio no es el de un top cut | Segmentar por corte de ELO, igual que hace Smogon. Avisar cuando el corte alto tenga poca muestra |
| **Fragmentación de la muestra** | 85.000 replays/mes parecen muchos, pero "dado lead A+B contra C+D en el turno 3 con Tailwind activo" puede tener N=4 | 🔴 **Regla dura: N mínimo (p. ej. 30) o no se muestra el porcentaje.** Mostrar siempre el N junto al % |
| **Confundir correlación con acierto** | "El 68% hace X" **no** significa que X sea correcto | El copy nunca dice "deberías". Dice "esto es lo que hace la gente, y estas son las consecuencias de cada línea" |
| **Profundidad del backfill** | Desconocido cuánto se puede paginar hacia atrás con `before=` | **Probarlo en la Fase 1.** Si el histórico es limitado, cada día sin recolectar es dato perdido |

> 🔴 **La regla de N mínimo no es opcional.** Un porcentaje sobre 4 partidas mostrado con dos decimales destruye la credibilidad del producto entero, que es lo único que tenemos.

---

## 6. QUÉ CAMBIA EN EL PROYECTO

### 6.1 Cambia el foso competitivo

| Antes | Ahora |
|---|---|
| Diferenciación = explicaciones mejor escritas | Diferenciación = **datos que nadie más publica** |
| Learning Mode = mis opiniones redactadas | Learning Mode = escenarios reales, con distribución real de respuestas |
| Battle Review = comparación cualitativa de líneas | Battle Review = comparación con base empírica |
| Meta Explorer = otra tabla de uso más (y el juego ya trae una) | Meta Explorer = **bring rates y comportamiento**, que el juego no trae |

Las explicaciones se copian en una tarde. **Un año de replays procesados no.** Esto es lo primero del proyecto que es realmente defendible.

### 6.2 Cambia el roadmap: hay que empezar a recolectar YA

**El recolector de replays debe entrar en la Fase 1, aunque no haya ninguna feature que lo consuma todavía.**

Motivo: el archivo histórico solo se puede construir hacia delante. Un cron diario en GitHub Actions que baje los replays nuevos y los guarde cuesta ~0 € y unas horas de trabajo. Cada semana que no exista es una semana de datos que no se recupera — y es exactamente el activo que no se puede copiar.

Encaja sin fricción en la arquitectura aprobada (opción C): es otro job de ingesta en GitHub Actions, en Node, igual que el de los mods de Showdown.

Propuesta de reordenación:

| Fase | Cambio |
|---|---|
| **1 — Data layer** | ➕ **Añadir el recolector de replays desde el día 1.** Guardar crudo, parsear después |
| **1.5 — Replay parser** (nueva) | Protocolo Showdown → máquina de estados → tabla de eventos por turno |
| **5 — Meta Explorer** | Reenfocado: bring rates y comportamiento, no solo uso |
| **6 — Learning Mode** | Escenarios **derivados de replays reales** (usando `\|t:\|` para encontrar los turnos difíciles). Baja mucho el riesgo de contenido |
| **7 — Battle Review** | Comparación de líneas **con distribución empírica** |

### 6.3 Esquema de BD — tablas nuevas

```
replays          id, format_id, regulation_id, showdown_id, uploaded_at,
                 p1_rating, p2_rating, winner, turn_count, raw_log(text), parsed_at

replay_teams     replay_id, side (p1|p2), species_id, previewed (bool), brought (bool),
                 lead (bool), position

replay_turns     id, replay_id, turn_no, decision_seconds,
                 field_state(jsonb)   -- {trick_room, tailwind[], weather, terrain}

replay_actions   id, replay_turn_id, side, slot (a|b), action_type (move|switch|mega|protect),
                 move_id, target_slot, actor_species_id, actor_hp_pct, revealed_item_id,
                 revealed_ability_id

behavior_priors  id, regulation_id, elo_bucket, context_hash, context(jsonb),
                 action(jsonb), n, pct, computed_at
```

`behavior_priors` es la tabla precalculada que sirve la web: consultar millones de `replay_actions` en caliente sería lento. Se recalcula en batch y **solo se materializan filas con `n >= umbral`.**

`raw_log` en bruto: si mejoramos el parser, se reprocesa sin volver a descargar nada.

### 6.4 Coste

**€0 adicionales.** GitHub Actions gratis en repo público; el almacenamiento son logs de texto (el replay de ejemplo pesa 9,5 KB → ~85.000/mes ≈ **800 MB/mes en bruto**, mucho menos comprimido y bastante menos tras parsear). Cabe de sobra en el VPS de 5,49 €. Conviene comprimir el crudo y plantearse retención por regulación.

---

## 7. LEGAL

| Aspecto | Situación |
|---|---|
| Replays de Showdown | **Públicos**, con `Access-Control-Allow-Origin: *`, API documentada oficialmente. Sin límites de tasa declarados |
| Términos de uso | No hay ToU explícitos para la API de replays. Misma zona gris tolerada que las stats de Smogon, usada por toda la comunidad desde hace años |
| Propiedad de los datos | Los replays son de sus jugadores / Pokémon Showdown. **Publicar agregados estadísticos es estándar** (es lo que hace Smogon). **No republicar replays íntegros ni identificar a jugadores concretos** |
| `pokemon_champions_battle_logger` | "All Rights Reserved", no comercial. **No usar su código** |
| Buenas prácticas | `User-Agent` identificativo con contacto, ritmo de descarga moderado, caché agresiva, no re-descargar lo ya guardado |

**Recomendación:** agregar y anonimizar. Guardar el nombre del jugador solo como hash para deduplicar, nunca mostrarlo. Elimina el ángulo de privacidad y no cuesta nada.

---

## 8. PREGUNTAS ABIERTAS PARA MAÑANA

1. **¿Confirmas meter el recolector de replays en la Fase 1?** Es la decisión con más valor por euro de todo el proyecto, y la única que se degrada si se pospone.
2. **¿Umbral de N mínimo?** Propongo **30** para mostrar un porcentaje, y enseñar siempre el N al lado.
3. **¿Segmentamos por ELO desde el principio?** Propongo los mismos cortes que Smogon (0/1500/1630/1760) para poder comparar.
4. **¿Guardamos el log crudo indefinidamente** o lo purgamos por regulación al cabo de N meses? Recomiendo guardarlo comprimido: es el activo.
5. **¿Incluimos singles (`gen9championsou`)** en la recolección aunque el MVP sea dobles? Cuesta prácticamente nada recolectarlo ahora y no se puede recuperar después. Recomiendo **sí, recolectar; no, procesar**.

---

## 9. FUENTES

- [WEB-API.md — pokemon-showdown-client](https://github.com/smogon/pokemon-showdown-client/blob/master/WEB-API.md)
- [Replays — Pokémon Showdown](https://replay.pokemonshowdown.com/)
- [Pokémon Champions Finally Added a Highly Requested Feature, but It Still Misses the Mark — ComicBook](https://comicbook.com/gaming/feature/pokemon-champions-finally-added-a-highly-requested-feature-but-it-still-misses-the-mark/)
- [Pokémon Champions: How to Check Battle Data — Operation Sports](https://www.operationsports.com/pokemon-champions-how-to-check-battle-data/)
- [Battle Replay/History features — Pokémon Forums](https://community.pokemon.com/en-us/discussion/24518/battle-replay-history-features-to-review-matches-within-pokemon-champions)
- [fufufukakaka/pokemon_champions_battle_logger](https://github.com/fufufukakaka/pokemon_champions_battle_logger)
- [StatsugiriLabs/PsReplayDownloader](https://github.com/StatsugiriLabs/PsReplayDownloader)
- [jamesrcode/project-red-data](https://github.com/jamesrcode/project-red-data) — precedente de parseo de Team Preview desde replays

*Mediciones de volumen y estructura del protocolo realizadas directamente contra la API el 22-sep-2026.*
