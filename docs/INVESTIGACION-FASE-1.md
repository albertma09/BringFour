# FASE 1 — INVESTIGACIÓN TÉCNICA Y DE MERCADO

**Proyecto:** herramienta educativa de competitivo para Pokémon Champions
**Fecha:** 21 de septiembre de 2026
**Estado:** investigación cerrada. **No se ha escrito código.** Pendiente de aprobación.

---

## 0. RESUMEN EJECUTIVO — LO QUE IMPORTA

Cinco conclusiones que condicionan todo lo demás:

1. **El juego ya existe y el reglamento es conocido.** Champions salió el 8-abr-2026 (Switch) y 17-jun-2026 (móvil), es F2P y desde mayo es la plataforma oficial de Play! Pokémon. La regulación activa hoy es **M-C (9-sep-2026 → 2-dic-2026)**. No hay que especular sobre mecánicas: están documentadas.

2. **El nicho de "team builder + dex + usage stats" está SATURADO.** He encontrado 15+ herramientas activas y gratuitas (ChampDex, Pikalytics, ChampTeams, MetaVGC, Pokémon Zone, Champions Lab, PokéBase, Game8, ChampsDex…). Construir otro builder no tiene valor diferencial. **Lo que nadie está haciendo bien es la capa explicativa**: Team Doctor, Learning Mode y Battle Review. Ahí está el producto.

3. **Hay una fuente de datos canónica, gratuita y con licencia limpia: los mods de Pokémon Showdown.** Existen los directorios `data/mods/champions` y `data/mods/championsregmb` en `smogon/pokemon-showdown` (MIT). Todo lo demás (datasets de GitHub, Pikalytics, PokeAPI) es o derivado, o desactualizado, o legalmente ambiguo.

4. **Hay un choque entre el stack que quieres y el ecosistema Pokémon.** Todo el tooling serio (datos, simulador, calculadora de daño) es TypeScript. Laravel no tiene nada equivalente. Propongo una solución que respeta tu stack sin reimplementar la calculadora de daño en PHP (sección 5). Es la decisión arquitectónica más importante del proyecto.

5. **La monetización es el riesgo real, no la técnica.** Los términos de The Pokémon Company prohíben explícitamente el uso comercial de su contenido. En la práctica toleran herramientas gratuitas (Showdown y Pikalytics llevan años), pero el historial de enforcement es agresivo. Una suscripción de pago con sprites oficiales y "Pokémon" en el dominio es el perfil de más riesgo posible. Hay mitigaciones concretas (sección 4), ninguna llega a riesgo cero.

---

## 1. POKÉMON CHAMPIONS: ESTADO ACTUAL (21-sep-2026)

### 1.1 El juego

| Dato | Valor |
|---|---|
| Lanzamiento Switch | 8 de abril de 2026 |
| Lanzamiento iOS/Android | 17 de junio de 2026 |
| Modelo | Free-to-play con moneda virtual (Victory Points, VP) |
| Cross-play | Sí, Switch ↔ móvil |
| Conectividad | Pokémon HOME (importa desde Scarlet/Violet) |
| Plataforma oficial VGC | Sí, desde mayo de 2026 |
| Especies al lanzamiento | 186 (según Wikipedia) |
| Mega Evolución | Sí, disponible |
| Terastalización | Anunciada como futura adición, no disponible al lanzamiento |

**Cómo se consiguen los Pokémon:** se reclutan gastando **Victory Points (VP)**, que se ganan jugando. Los VP también se gastan en reentrenar SP. Esto es relevante para el producto: **el coste en VP de un equipo es una restricción real de teambuilding** que ninguna herramienta de generaciones anteriores tenía que modelar. ChampDex ya muestra "VP costs" en su builder; es una feature que el usuario espera.

### 1.2 El sistema de Stat Points (SP) — el cambio de fondo

Este es el cambio mecánico más importante respecto a Scarlet/Violet, y afecta directamente al modelo de datos:

- **No hay IVs.** Todo Pokémon se comporta como si tuviera 31 en las seis estadísticas.
- **No hay EVs.** Se sustituyen por **Stat Points (SP)**: **66 puntos totales**, repartidos libremente, con **tope duro de 32 SP por estadística**.
- **1 SP = +1 punto de estadística a nivel 50.**
- **Nivel siempre 50** en combate.
- **Las naturalezas se sustituyen por "Stat Alignments"**: funcionan igual (+10% a una stat, −10% a otra).
- **Reentrenar es libre**, pagando VP. No hay breeding.

**Fórmulas (verificadas contra la fórmula clásica a nivel 50 con 31 IVs):**

```
HP           = Base + 75 + SP
Resto stats  = (Base + 20 + SP) × multiplicador_alignment
```

He comprobado que esto es matemáticamente idéntico a la fórmula de siempre:
`floor((2·Base + 31)/2) + 50 + 10 = Base + 75` para HP, y
`floor((2·Base + 31)/2) + 5 = Base + 20` para el resto. **Las fórmulas son correctas.**

**Conversión con el sistema antiguo:** `1 SP = 8 EVs`, salvo el primer SP de cada stat que cuesta 4 EVs (regla de transferencia de HOME). Esto importa porque muchos spreads publicados y herramientas antiguas siguen hablando en EVs.

**Consecuencia de diseño:** el tope de 32 SP por stat hace imposible el clásico "252/252/4". El espacio de spreads es mucho más pequeño y **mucho más analizable**: 66 puntos con tope 32 es un espacio finito y enumerable. Un "spread solver" determinista es viable de verdad, no una aproximación. Esto es una oportunidad de producto.

> ⚠️ **Dato a verificar antes de implementar:** las fuentes discrepan sobre el número de Stat Alignments (una dice 21, el dataset de GitHub tiene 25 entradas en `natures/`). Hay que resolverlo contra el mod de Showdown.

### 1.3 Regulación M-C (activa)

| Regla | Valor |
|---|---|
| Vigencia | 9-sep-2026 → 2-dic-2026 |
| Dobles | equipo de 4 a 6, se llevan 4 al combate |
| Singles | equipo de 3 a 6 |
| Nivel | todos a 50 |
| Tiempo | 7 min totales, 45 s por turno, 90 s de Team Preview |
| Novedades | +34 Pokémon y +6 Mega Evoluciones respecto a M-B |
| Otros cambios | nuevos objetos (semillas de terreno, objetos defensivos), nuevas habilidades y movimientos, bans de movimientos de la v1.2.0 |

Nombres relevantes añadidos en M-C: Rillaboom, Cinderace, Inteleon, Mega Golisopod, entre otros.

**Meta actual (orientativo, de agregadores de terceros):** Rillaboom y Mega Salamence encabezan el uso; Incineroar, Indeedee-F, Milotic y Farigiraf son habituales. Farigiraf es el mejor setter de Trick Room del formato gracias a Armor Tail (bloquea prioridad). El formato se describe como equilibrado, sin un S-tier claro.

### 1.4 Ciclo de regulaciones — implicación arquitectónica crítica

Las regulaciones duran **~3 meses** y cambian roster, objetos, movimientos y habilidades. Esto significa:

> **La regulación NO puede ser un campo de texto. Tiene que ser una entidad de primera clase en la base de datos, y todo dato competitivo (legalidad, usage, sets, análisis guardados) tiene que estar versionado por regulación.**

Si esto no se diseña desde el día 1, en diciembre de 2026 (cuando entre M-D) el proyecto se rompe o requiere una migración dolorosa. Es el error de diseño más probable y más caro.

### 1.5 Import / Export — limitación importante

El juego comparte equipos mediante **Replica Teams**: un **código alfanumérico de 10 caracteres** generado en `Train > Replica Teams > Share your Battle Teams`. Copia los 6 Pokémon con objeto, habilidad, naturaleza y movimientos.

**Ese código es opaco y se resuelve en los servidores de Nintendo. No es decodificable localmente y no existe API pública para resolverlo.**

Consecuencia práctica: **no podemos leer ni generar códigos de juego.** El import/export tiene que basarse en:
- Formato de texto estilo Showdown paste (estándar de facto, lo soportan ChampDex y casi todos).
- Un formato JSON propio con URL compartible.
- Como mucho, permitir al usuario **pegar** el código de 10 caracteres como metadato para que otros lo copien manualmente a mano.

Esto hay que asumirlo: no habrá sincronización automática con la cuenta del juego.

---

## 2. COMPETIDORES

### 2.1 Panorama

El espacio está mucho más lleno de lo que probablemente esperabas. En los 5 meses desde el lanzamiento han aparecido decenas de herramientas.

| Herramienta | Qué hace | Modelo | Amenaza |
|---|---|---|---|
| **ChampDex** | Dex (342 Pokémon), team builder, calc de daño, usage en vivo, resultados de torneo, speed tiers, **spread solver**, replica teams | Gratis, solo dev, sin API | 🔴 **Alta** — competidor directo más completo |
| **Pikalytics** | Usage stats de referencia del VGC, team builder, sets sugeridos, torneos, app Android | Gratis + app sin ads + Ko-fi | 🔴 **Alta** — marca establecida, autoridad en datos |
| **ChampTeams.gg** | Meta, tier list, mejores equipos, cores, guías (incl. speed control) | Gratis | 🟠 Media |
| **MetaVGC** | Reglamento, equipos destacados, guías conceptuales | Gratis | 🟠 Media |
| **Pokémon Zone** | Regulaciones, stats, tier lists | Gratis | 🟠 Media |
| **Champions Lab** | Season tracking, team builder, **battle simulator**, data | Gratis | 🟠 Media |
| **ChampsDex** | Blog/guías SEO (EVs, spreads, speed tiers, Trick Room) | Gratis, SEO-first | 🟡 Baja, pero ocupa el SEO |
| **Game8 / GameWith.ai** | Wikis masivas con builder básico | Gratis, ads | 🟡 Baja en calidad, 🔴 alta en SEO |
| **Victory Road** | Cobertura VGC ES/EN desde 2015, recursos | Gratis | 🟡 Baja (no es herramienta) |
| **Limitless VGC** | Base de datos de torneos, standings, usage de torneo | Gratis | 🟡 Complementaria, no competidora |
| **RotomLabs / ChampCalc** | Conversores SP↔EV, calculadoras sueltas | Gratis, single-purpose | 🟢 Nula |
| **Showdown Tier / Pokékipe** | Tier lists y dashboards sobre datos de Smogon | Gratis | 🟢 Nula |
| **BattleWise AI** | Guías con enfoque IA | Gratis | 🟠 Media — el único que apunta a lo mismo que tú |

### 2.2 Lectura honesta

**Lo que ya está resuelto y no deberías intentar ganar:**

- Team builder visual → ChampDex y Pikalytics lo tienen, gratis y bien.
- Usage stats → Pikalytics es la autoridad, con años de marca.
- Dex y datos crudos → resuelto por varios.
- Calculadora de daño → resuelta y open source.
- Wikis y guías SEO genéricas → Game8 y ChampsDex ya inundan ese espacio.

**Lo que NADIE está haciendo (tu ventana real):**

1. **Diagnóstico estructural explicado.** Todos te dicen "tu equipo es débil a Fuego". Ninguno te dice *"tienes tres Pokémon cuya única respuesta a Trick Room es ser más rápido, así que si el rival lo sube, los tres dejan de funcionar a la vez"*. El Team Doctor con explicación causal no existe en el mercado.

2. **Learning Mode / entrenamiento de decisión.** No he encontrado **ninguna** herramienta que plantee escenarios y evalúe el razonamiento del jugador. Hay guías que explican Trick Room; no hay nada que te *pregunte* qué intenta el rival y te corrija. Es la feature más diferencial de todo el brief.

3. **Post-Battle Analysis con comparación de líneas bajo incertidumbre.** El marco SAFE / RISKY / ALL-IN y el "¿qué pasa si me equivoco?" es exactamente lo que enseñan los buenos coaches de VGC, y nadie lo ha convertido en producto.

**Conclusión estratégica:** el Team Builder es tabla de entrada obligatoria, no es el producto. **El producto es el motor de explicación.** Si el builder se lleva el 70% del esfuerzo del MVP, el proyecto habrá fallado.

---

## 3. FUENTES DE DATOS

### 3.1 Tabla de evaluación

| Fuente | Contenido | API | Licencia | Actualización | Uso comercial | Veredicto |
|---|---|---|---|---|---|---|
| **smogon/pokemon-showdown** — mods `champions`, `championsregmb` | Pokédex, movimientos, habilidades, objetos, learnsets, legalidad por regulación, lógica de efectos | No (repo Git) | **MIT** | Continua, por PRs de la comunidad | ✅ Sí | 🟢 **FUENTE PRIMARIA** |
| **Smogon usage stats** (`smogon.com/stats`) | Usage %, movimientos, objetos, habilidades, spreads, compañeros, checks/counters. Formatos confirmados: `gen9championsvgc2026regmb`, `gen9championsou`, `gen9championsuu`, `gen9championsbssregmb` y variantes Bo3, con cortes de ELO 0/1500/1630/1760 | Ficheros `.txt.gz` + **chaos JSON** | Sin licencia explícita; público y usado por toda la comunidad desde hace 15 años | **Mensual** | ⚠️ Zona gris tolerada | 🟢 **FUENTE PRIMARIA de meta** |
| **otterlyclueless/pokemon-champions-data** | 258 Pokémon, 900 movimientos, 191 habilidades, 583 objetos, 258 learnsets, type chart, docs de mecánicas | No | **CC BY 4.0** (comercial permitido con atribución) | ⚠️ **3 commits, último abril 2026** | ✅ Sí | 🟠 **Semilla útil, NO fuente viva** — le faltan los 34 Pokémon de M-C |
| **jamesrcode/project-red-data** | Usage diario del top 10% de replays públicos de Showdown | `stats.json` vía GitHub Actions | ⚠️ **Sin fichero LICENSE** | **Diaria** | ❌ No determinable | 🔴 **No depender comercialmente.** Buena idea, replicable por nosotros |
| **PokeAPI** | Datos generales de Pokémon, >50B llamadas/mes | REST pública, sin key | BSD-3 (código) | Lenta para gens nuevas | ✅ Sí | 🟡 Útil para metadatos genéricos; **no cubre Champions** de forma fiable |
| **PokeAPI/sprites** | Sprites | Repo / CDN | **CC0** declarado por el repo | — | ⚠️ Ver sección 4 | 🟠 Ojo: el CC0 lo declara el repo, **no el titular del copyright** |
| **Pikalytics** | Usage, sets, torneos | ❌ Sin API pública | Propietaria | Frecuente | ❌ No | 🔴 **Scraping frágil y hostil. Descartar** |
| **Limitless VGC + RK9** | Resultados de torneo, standings, listas oficiales | Wrapper Python no oficial | Sin términos públicos claros | Por torneo | ⚠️ Preguntar | 🟠 Valioso (datos de torneo ≠ ladder). **Pedir permiso antes de usar** |
| **smogon/damage-calc** (`@smogon/calc`) | Motor de cálculo de daño | npm | **MIT** | Activa | ✅ Sí | 🟢 **Reutilizar, no reescribir** |
| **NCP-VGC-Damage-Calculator** | Calc de VGC 2026: Champions, mantenida por nerd_of_now | Web | **MIT** | Activa | ✅ Sí | 🟢 Referencia para las adaptaciones de Champions |
| **API oficial de Champions** | — | ❌ **No existe** | — | — | — | 🔴 No hay |

### 3.2 Discrepancias detectadas (y por qué importan)

Las fuentes **no coinciden** en cifras básicas:

| Dato | Wikipedia | dataset GitHub (abr-2026) | ChampDex (hoy) |
|---|---|---|---|
| Nº de Pokémon | 186 (lanzamiento) | 258 | 342 |
| Nº de naturalezas / alignments | — | 25 | 21 (según otra guía) |

Seguramente miden cosas distintas (especies vs. formas vs. formas + megas, y el roster ha crecido con M-A/M-B/M-C). Pero que **no se pueda contrastar sin ambigüedad** confirma la decisión:

> **Una sola fuente de verdad (el mod de Showdown), ingesta automatizada, y todo lo demás como enriquecimiento opcional.** Nunca mezclar fuentes para el mismo campo.

### 3.3 Estrategia de datos recomendada

```
smogon/pokemon-showdown (mods champions*)  ─┐
                                            ├─► script de ingesta (Node, GitHub Actions)
Smogon stats (chaos JSON, mensual)         ─┤                │
                                            │                ▼
Replays públicos de Showdown (diario)      ─┘   JSON normalizado versionado en Git
                                                             │
                                                             ▼
                                              seeders de Laravel → PostgreSQL
```

**Puntos clave:**

- **Cero scraping de webs de terceros.** Solo repos Git públicos y ficheros estáticos de Smogon.
- **La ingesta corre en CI, no en producción.** Si una fuente cae, la web sigue funcionando con el último JSON commiteado. Sin dependencias en caliente.
- **Los JSON quedan versionados en Git.** Es la diferencia entre "el meta cambió" y "no sé qué pasó": un `git diff` entre regulaciones te regala la feature premium de "evolución del meta" sin trabajo extra.
- **La idea de project-red-data la replicamos nosotros**, con licencia propia, en vez de depender de un repo sin licencia.

---

## 4. RIESGO LEGAL E IP — LA SECCIÓN INCÓMODA

No soy abogado y esto no es asesoramiento legal. Es lo que dicen los documentos públicos y lo que muestra el historial.

### 4.1 Lo que dicen los términos

Los Terms of Use de Pokémon.com son explícitos:

- Prohíben usar el servicio o su contenido **con fines comerciales**, incluido vender acceso o colocar publicidad, patrocinios o promociones sobre ese contenido.
- Todo el contenido (artwork, capturas, gráficos, logos) es propiedad de Pokémon.
- Su política declarada es **denegar** las solicitudes de uso de sus marcas y copyrights, porque reciben miles.

En términos estrictos: **no existe una vía formal y limpia para monetizar una herramienta que muestre nombres, sprites y datos de Pokémon.**

### 4.2 Lo que dice el historial de enforcement

El patrón es bastante consistente:

| Tipo de proyecto | Trato histórico |
|---|---|
| **Fan games y ROM hacks** | 🔴 Perseguidos con dureza. Pokémon Uranium, Prism, Relic Castle (2024), 379 juegos retirados de Game Jolt por DMCA masiva |
| **Apps que imitan una experiencia de producto** | 🔴 SmashTogether cerrado en 24 h (mayo 2025) |
| **Herramientas competitivas, dex y stats** | 🟢 Toleradas durante años. Pokémon Showdown, Smogon, Serebii, Pikalytics, Bulbapedia siguen operando. Pikalytics incluso tiene app y Ko-fi |

La lectura: **TPCi persigue lo que compite con su producto o confunde sobre su origen; tolera lo que sirve a su escena competitiva.** Una herramienta de análisis educativo cae claramente en el segundo grupo. Pero "tolerado" no es "permitido", y la tolerancia no es un derecho.

### 4.3 Dónde sube el riesgo

El riesgo no es binario, es un gradiente:

| Configuración | Riesgo |
|---|---|
| Gratis + donaciones (Ko-fi/Patreon) + disclaimer | 🟢 Bajo (es el modelo de Pikalytics) |
| Gratis + ads discretos | 🟡 Bajo-medio |
| Suscripción de pago sobre análisis propio | 🟠 Medio |
| Suscripción + sprites oficiales como activo central | 🔴 Alto |
| "Pokémon" en el nombre de marca o dominio | 🔴 Alto (problema de marca, distinto del copyright) |

Sobre los sprites: el repo **PokeAPI/sprites está bajo CC0**, pero eso es una declaración de los mantenedores del repo, **no del titular del copyright**. Nadie puede liberar bajo CC0 una obra que no le pertenece. Legalmente ese CC0 no te protege. En la práctica todo el mundo los usa; en un producto de pago es una exposición real.

### 4.4 Mitigaciones concretas

1. **No metas "Pokémon" en la marca ni en el dominio.** Es lo más barato y lo que más reduce el riesgo de marca. Nombres tipo `<algo>lab.gg`, no `pokemonchampionslab.com`.
2. **Disclaimer visible en el footer**, igual que ChampDex: *"Not affiliated with Nintendo / The Pokémon Company"*.
3. **Cobra por el análisis, no por los datos.** El premium debe ser el motor de explicación, los informes y el histórico — cosas de creación propia. Los datos de Pokémon quedan siempre en la capa gratuita.
4. **Evita sprites oficiales detrás del paywall.** Opciones: iconografía propia, siluetas, tipografía y color por tipo. Un diseño con identidad propia es además mejor producto y te distingue de las 15 herramientas que usan los mismos sprites.
5. **Ten plan de contingencia desde el día 1:** export completo de datos del usuario, y arquitectura donde los nombres de especie sean claves referenciadas, no texto incrustado. Si algún día hay que despersonalizar, que sea un cambio de capa de presentación, no una reescritura.
6. **No uses la palabra "oficial" ni imites la identidad visual del juego.** La confusión de origen es lo que dispara las acciones.

### 4.5 Fuentes de terceros: qué hacer

- **Smogon / Showdown (MIT):** usar con atribución. Sin problema.
- **otterlyclueless (CC BY 4.0):** usar con atribución explícita. La licencia permite uso comercial.
- **project-red-data (sin licencia):** "sin licencia" significa *todos los derechos reservados* por defecto. **No usar.** Replicar la metodología es legítimo; copiar el output no lo es sin permiso.
- **Limitless / RK9:** escribirles y pedir permiso antes de tocar nada. Son comunidad, suelen colaborar.
- **Pikalytics:** no tocar.
---

## 5. ARQUITECTURA PROPUESTA

### 5.1 El problema que hay que resolver primero

Pides **Laravel + PHP**. Perfecto para API, auth, equipos, reglas y facturación.

El problema: **todo el ecosistema de datos y simulación de Pokémon es TypeScript.** `@smogon/calc`, `@pkmn/dex`, `@pkmn/data`, los mods de Showdown… no existe equivalente en PHP. Y la calculadora de daño no es "una fórmula": son cientos de casos especiales (habilidades, objetos, terrenos, clima, spread moves, Intimidate, multi-hit, redirección…). Reimplementarla en PHP son semanas de trabajo y una fuente permanente de bugs sutiles y de desincronización cada vez que cambie una regulación.

Tres opciones:

| Opción | Descripción | Veredicto |
|---|---|---|
| **A. Laravel puro** | Reimplementar cálculo de daño y datos en PHP | ❌ Semanas de trabajo, bugs sutiles, deriva permanente. No lo hagas |
| **B. Laravel + microservicio Node** | Sidecar Node para calc y datos | 🟠 Funciona, pero añade un servicio que desplegar y mantener. Contradice tu punto 30 |
| **C. Node solo en build-time** | Ingesta en CI (Node) → JSON → Postgres. Cálculo de daño **en el navegador** con `@smogon/calc` | ✅ **Recomendada** |

### 5.2 Por qué la opción C

- **Laravel nunca necesita calcular daño.** El cálculo es interactivo (el usuario mueve sliders) y pertenece al cliente: es más rápido, no consume servidor y no cuesta nada.
- **Node solo aparece en GitHub Actions**, nunca en producción. No hay un segundo servicio que desplegar, monitorizar ni pagar.
- **El rule engine sí va en PHP**, en Laravel, que es donde quieres mantener la lógica de negocio. Y encaja: detectar roles y sinergias es lógica de reglas, no simulación numérica.
- **Una sola máquina en producción**: PHP-FPM + Postgres + assets estáticos. Mantenible por una persona, que es tu requisito 30.

### 5.3 Diagrama

```
┌─────────────── BUILD TIME (GitHub Actions, gratis) ───────────────┐
│  smogon/pokemon-showdown (mods champions*)                        │
│  smogon.com/stats (chaos JSON mensual)                            │
│  replays públicos de Showdown (diario)                            │
│                    │                                              │
│                    ▼  script Node de ingesta                      │
│         /data/{regulation}/*.json  ──► commit al repo             │
└────────────────────┬──────────────────────────────────────────────┘
                     │  php artisan data:sync
                     ▼
┌─────────────── RUNTIME ───────────────────────────────────────────┐
│                                                                   │
│   PostgreSQL ◄──► Laravel (REST API)                              │
│                     │                                             │
│                     ├── RuleEngine (PHP, determinista) ◄── núcleo │
│                     ├── Auth / Teams / Billing                    │
│                     └── LLM Gateway (opcional, premium)           │
│                     │                                             │
│                     ▼  JSON                                       │
│   Vue 3 + TS + Vite + Tailwind                                    │
│                     └── @smogon/calc (cálculo de daño en cliente) │
└───────────────────────────────────────────────────────────────────┘
```

### 5.4 El Rule Engine — el corazón del producto

Tu sección 6 del brief es correcta y es lo que hay que construir bien. Concreto:

**Pipeline determinista, tres niveles:**

```
Nivel 1 — FACTS       Hechos atómicos extraídos del equipo
                      "incineroar tiene Fake Out"
                      "5 de 6 Pokémon tienen velocidad base < 70"
                      "el equipo tiene 0 fuentes de speed control"

Nivel 2 — SIGNALS     Interpretación de hechos en conceptos competitivos
                      Roles: FakeOutUser, Intimidate, Pivot, TrickRoomSetter…
                      Cobertura: presión física/especial, spread vs single
                      Estructura: winCondition, redirección, recovery

Nivel 3 — FINDINGS    Diagnóstico con severidad + evidencia + explicación
                      { tipo, severidad, pokemon_implicados[],
                        evidencia[], plantilla_explicacion, params }
```

**Reglas como datos, no como código.** Cada regla es un fichero declarativo:

```yaml
id: no-trick-room-answer
tipo: weakness
severidad: alta
cuando:
  - equipo.speed_control_sources.trick_room_counter == 0
  - equipo.pokemon.filter(base_speed > 80).count >= 4
evidencia:
  - lista de Pokémon rápidos afectados
  - fuentes de speed control detectadas
explicacion: weakness.trick-room.no-answer
```

Añadir una regla nueva = añadir un fichero. Nunca tocar el motor. Esto cumple tu requisito de extensibilidad y hace los tests triviales.

**Las explicaciones son plantillas i18n con parámetros, no texto generado.** Ejemplo:

> `weakness.trick-room.no-answer` = *"{n} de tus Pokémon dependen de ser más rápidos para funcionar ({lista}). Si el rival sube Trick Room, los {n} pasan a moverse los últimos a la vez. No detecto en tu equipo ni Taunt, ni Imprison, ni un Pokémon lento que aproveche el Trick Room rival."*

Esto da explicaciones de calidad **con 0 € de IA y 0 alucinaciones**, y traducibles a ES/EN de entrada. La IA llega después, como capa de reescritura y conversación, solo en premium.

**Regla dura de producto:** el LLM **nunca** decide si un equipo tiene Fake Out. El LLM solo recibe FINDINGS ya calculados y los redacta o los conversa. Si un dato no está en los FINDINGS, la respuesta es "Data unavailable".

### 5.5 Esquema de base de datos propuesto

Mínimo viable, sin tablas de adorno. **Todo lo competitivo va versionado por `regulation_id`.**

**Datos de referencia (read-only, poblados por ingesta):**

```
regulations        id, code (M-C), name, starts_at, ends_at, format (singles|doubles), source_version
species            id, slug, name, types[], base_stats(jsonb), abilities[], is_mega, base_form_id
moves              id, slug, name, type, category, power, accuracy, pp, priority, target, flags(jsonb)
abilities          id, slug, name, effect(jsonb)
items              id, slug, name, effect(jsonb)
alignments         id, slug, name, plus_stat, minus_stat
learnsets          species_id, move_id, regulation_id
legality           regulation_id, entity_type, entity_id, is_legal, restriction  -- legalidad por regulación
```

**Equipos del usuario:**

```
teams              id, user_id (nullable en v1), name, regulation_id, format, notes, visibility, share_slug
team_slots         id, team_id, position, species_id, ability_id, item_id, alignment_id,
                   sp(jsonb: {hp,atk,def,spa,spd,spe}), moves(int[4]), nickname
```

Nota: `sp` como jsonb con constraint `suma = 66` y `cada valor <= 32`. Lo valida la BD además del backend.

**Análisis:**

```
analyses           id, team_id, regulation_id, engine_version, findings(jsonb), created_at
rules              id, slug, type (role|synergy|weakness), severity, definition(jsonb), enabled, version
```

`engine_version` es importante: permite reproducir por qué un análisis antiguo dijo lo que dijo.

**Meta:**

```
usage_snapshots    id, regulation_id, source (smogon|replays|tournament), period, elo_cutoff, captured_at
usage_entries      snapshot_id, species_id, usage_pct, moves(jsonb), items(jsonb),
                   abilities(jsonb), spreads(jsonb), teammates(jsonb)
archetypes         id, slug, name, regulation_id, definition(jsonb), description_key
```

**Aprendizaje y partidas:**

```
scenarios          id, slug, regulation_id, setup(jsonb), question_key, options(jsonb),
                   correct_option, explanation_key, difficulty
scenario_attempts  id, user_id, scenario_id, chosen_option, created_at
battle_reviews     id, user_id, team_id, opponent_team(jsonb), result, turns(jsonb), analysis(jsonb)
```

**Cuentas y pago (creadas tarde, previstas ya):**

```
users              id, email, password_hash, created_at
subscriptions      id, user_id, plan, status, provider, provider_ref, current_period_end
```

**Lo que NO creo ahora:** `cores` (derivable de `usage_entries.teammates`), `team_moves` (redundante con `team_slots.moves`), `competitive_data` (demasiado vago), tablas de logs de IA hasta que haya IA.

**Índices críticos desde el principio:** `(regulation_id, species_id)` en learnsets/legality/usage_entries; GIN en los jsonb que se consulten; `share_slug` único.

---

## 6. STACK Y COSTES

### 6.1 Stack

| Capa | Elección | Nota |
|---|---|---|
| Frontend | Vue 3 + TypeScript + Vite + Tailwind | Como pediste |
| Cálculo de daño | `@smogon/calc` (MIT) en el cliente | No reescribir |
| Backend | Laravel 12 + PHP 8.3, REST | Como pediste |
| BD | PostgreSQL 16 | jsonb + GIN es clave para findings y usage |
| Caché | Caché de fichero o Redis si hace falta | No lo metas hasta necesitarlo |
| Ingesta | Node + GitHub Actions | Solo build-time |
| Tests | Pest (PHP) + Vitest (TS) | El grueso: tests del rule engine |
| Hosting | 1 VPS | Ver abajo |
| CI/CD | GitHub Actions | Gratis en repos públicos |

### 6.2 Costes reales

Tu objetivo son €0. La verdad matizada:

| Opción | Coste/mes | Pega |
|---|---|---|
| **Render free tier** | **€0** | La app se duerme a los 15 min; despertar tarda 30-50 s. **Inaceptable para SEO y para un usuario nuevo** |
| **Hetzner CX23** | **€5,49** | Nada. Es la recomendación |
| Hetzner CPX22 | €9,51 | Si quieres margen para varios proyectos |
| Railway | €10-15 | Más caro, sin ventaja aquí |
| Fly.io | €8-25 | Ya no tiene free tier para nuevos usuarios |
| Render Starter | €7 (~$7) | Postgres se factura aparte |

**Nota de 2026: el free tier real está prácticamente muerto.** Railway y Fly.io han pasado a modelos de trial/uso. Render mantiene free tier sin tarjeta pero con cold starts.

**Presupuesto realista v1:**

| Concepto | Coste |
|---|---|
| VPS Hetzner CX23 (PHP + Postgres + Nginx) | €5,49/mes |
| Dominio `.gg` o `.app` | ~€12-35/año |
| Cloudflare (CDN, DNS, caché) | €0 |
| GitHub + Actions (repo público) | €0 |
| Sprites / assets | €0 |
| OpenAI en v1 | **€0** — no hay IA en el MVP |
| **Total v1** | **≈ €6-9/mes** |

**Coste de IA cuando llegue (Fase 9):** con prompts estructurados (solo FINDINGS, nunca la BD), un análisis ronda 1.500-3.000 tokens de entrada. Es céntimos por análisis. La regla: **la IA solo se invoca bajo suscripción o con rate limit duro por usuario.** Nunca en el flujo gratuito, nunca automáticamente al guardar un equipo.

---

## 7. ROADMAP

| Fase | Objetivo | Dificultad | Depende de | Coste | Riesgo principal |
|---|---|---|---|---|---|
| **0 — Research** | Este documento | ✅ Hecha | — | €0 | — |
| **1 — Data layer** | Ingesta Showdown→JSON→Postgres, versionado por regulación, migraciones, seeders, tests de integridad | 🟠 Media | Fase 0 | €0 | **Que los mods de Showdown cambien de estructura.** Mitigación: tests de esquema que fallan en CI |
| **2 — Team Builder** | UI de construcción, tarjetas, selección, validación SP (66/32), stats calculadas, guardado local, import/export texto | 🟠 Media | F1 | €6/mes (VPS) | Perder tiempo puliendo UI. **Timeboxear** |
| **3 — Team Analyzer** | Rule engine + roles + sinergias + debilidades. Plantillas de explicación i18n. Suite de tests de reglas | 🔴 **Alta** | F1, F2 | €0 | **El riesgo nº1 del proyecto.** Reglas mal calibradas = consejos malos = pérdida de credibilidad |
| **4 — Team Doctor** | Capa de presentación de F3: identidad de equipo, fortalezas, áreas de atención, expandibles | 🟡 Baja | F3 | €0 | Bajo. Si F3 está bien, esto es UI |
| **5 — Meta Explorer** | Usage de Smogon, filtros por regulación/ELO/fuente, etiquetado claro de dato real vs inferencia | 🟠 Media | F1 | €0 | Que los cortes de ELO confundan. Etiquetar la procedencia siempre |
| **6 — Learning Mode** | Escenarios, preguntas, feedback explicado | 🔴 Alta (contenido) | F3 | €0 | **No es un problema técnico, es de contenido.** Cada escenario hay que escribirlo a mano y con criterio. 20 buenos > 200 malos |
| **7 — Battle Review** | Entrada de partida, comparación de líneas, marco SAFE/RISKY/ALL-IN | 🔴 Alta | F3, F6 | €0 | Difícil de hacer útil sin caer en obviedades |
| **8 — Cuentas** | Email/password, equipos en nube, historial | 🟡 Baja | F2 | €0 | Bajo. Laravel lo da casi hecho |
| **9 — Premium + IA** | Stripe, gating, AI Coach sobre FINDINGS, informes | 🟠 Media | F8 | Stripe 1,5% + €0,25 · OpenAI por uso | **Riesgo legal (sección 4)** + coste variable |

**Nota sobre el orden:** he movido Meta Explorer a Fase 5 (tú lo tenías más tarde). Motivo: depende solo de la capa de datos, es barato, y es el mejor imán de SEO. Da tráfico mientras construyes las fases difíciles.

**Nota sobre la Fase 6:** es la feature más diferencial y la que más trabajo manual exige. No es código, es criterio competitivo. Presupuesta tiempo de *escribir*, no de programar.

---

## 8. FUNCIONES MVP vs PREMIUM

### MVP (gratis, y tiene que ser realmente útil por sí solo)

- Team Builder completo con validación de SP y legalidad por regulación
- Stats calculadas en vivo
- Cálculo de daño en cliente
- Team Analyzer: roles, sinergias, debilidades, con explicación
- Team Doctor: identidad del equipo, fortalezas, áreas de atención
- Meta Explorer básico: usage por regulación
- Import/export texto + URL compartible
- Guardado local (sin cuenta)
- ES + EN
- Dark mode, responsive, accesible

### Premium (más adelante)

| Feature | Por qué alguien pagaría |
|---|---|
| **AI Coach** | Conversar sobre *tu* equipo, con los FINDINGS como contexto |
| **Histórico del meta** | Evolución del uso entre regulaciones. Te sale casi gratis del versionado en Git |
| **Team Vault** | Equipos ilimitados en la nube, privados |
| **Matchup Lab** | Análisis profundo contra un equipo concreto o un arquetipo |
| **Informes descargables** | PDF para preparar un regional |
| **Battle Review avanzado** | Múltiples líneas, probabilidades, patrones a lo largo de varias partidas |

**Principio de reparto:** gratis = *entender tu equipo*. Premium = *profundidad, histórico y persistencia*. Nunca poner detrás del muro algo que un jugador necesita para construir un equipo legal.

---

## 9. MONETIZACIÓN

| Modelo | Pros | Contras | Veredicto |
|---|---|---|---|
| **Donaciones (Ko-fi/Patreon)** | Riesgo legal mínimo, es lo que hace Pikalytics, sin fricción | Ingresos muy bajos e impredecibles | 🟢 **Empezar aquí** |
| **Freemium suscripción** | Ingreso recurrente, alinea valor y precio | Riesgo legal medio; el nicho es pequeño y acostumbrado a lo gratis | 🟠 **Fase 9, con cuidado** |
| **Ads** | Pasivo, no requiere cuentas | Arruina la UX de una herramienta de análisis; CPM bajo; choca con los ToU de TPCi | 🔴 Evitar |
| **Pago único** | Sin gestión de churn | No cubre coste recurrente de mantener datos por regulación | 🟠 Mal encaje |
| **Sponsorship** | Encaja con la escena (torneos, creadores) | Requiere audiencia previa | 🟡 Cuando haya tráfico |
| **Affiliate** | — | Nada natural que afiliar aquí | 🔴 Descartar |

**Recomendación:**

1. **Fases 1-8: gratis total + botón de donación.** Construye audiencia y credibilidad. El coste (€6/mes) es asumible sin ingresos.
2. **Fase 9: freemium suave.** Precio de referencia en este nicho: **€3-5/mes**. Por encima de eso no compites con herramientas gratuitas buenas.
3. **Nunca degrades lo gratuito** para empujar a premium. En una comunidad pequeña y muy conectada eso se detecta en días y quema la reputación.

**Realismo sobre el tamaño del mercado:** el VGC competitivo serio son decenas de miles de personas a nivel mundial, no millones, y están bien servidas de herramientas gratuitas. Esto no va a ser un negocio. Puede cubrir costes y darte reputación en la escena. Plantéalo así y las decisiones serán mejores.

---

## 10. RIESGOS TÉCNICOS

| # | Riesgo | Impacto | Prob. | Mitigación |
|---|---|---|---|---|
| 1 | **Las reglas dan análisis malos o triviales** | 🔴 Crítico — destruye la propuesta de valor | Alta | Validar cada regla contra equipos reales de top cut. Suite de tests con equipos conocidos y findings esperados. Empezar con 15 reglas buenas, no 60 mediocres |
| 2 | **Cambio de regulación rompe todo** | 🔴 Crítico | **Certeza** (M-D llega el 2-dic-2026) | Versionar por `regulation_id` desde el día 1. Es la decisión más importante del esquema |
| 3 | **La estructura del mod de Showdown cambia** | 🟠 Alto | Media | Ingesta con validación de esquema que falla en CI. Snapshot JSON commiteado: producción nunca se rompe |
| 4 | **Acción legal de TPCi** | 🔴 Crítico si llega | Baja (mientras sea gratis) / Media (con paywall y sprites) | Sección 4. Export de datos siempre disponible |
| 5 | **Reimplementar la calc de daño en PHP** | 🟠 Alto | Alta si no se decide ahora | Opción C de la sección 5. `@smogon/calc` en cliente |
| 6 | **Los sprites no están disponibles para Champions** | 🟡 Medio | Media | Diseño con identidad propia (siluetas, color por tipo). Reduce riesgo legal y te diferencia |
| 7 | **Alucinaciones del LLM** | 🟠 Alto para la credibilidad | Media | El LLM solo redacta FINDINGS. Prompt que prohíbe inventar. "Data unavailable" obligatorio. Validar que los nombres de la respuesta existen en el equipo |
| 8 | **Scope creep** | 🟠 Alto | **Muy alta** — el brief tiene 34 secciones | Timeboxing por fase. Fase 3 es innegociable; las demás se recortan |
| 9 | **Coste de IA descontrolado** | 🟡 Medio | Baja | Rate limit duro, solo bajo suscripción, caché de respuestas por `(team_hash, engine_version)` |
| 10 | **Contenido de Learning Mode que no llega** | 🟠 Alto | Media | Empezar con 10 escenarios excelentes. Si no puedes escribir 10 buenos, la feature no está lista |

---

## 11. DECISIONES QUE NECESITO DE TI

Antes de empezar mañana:

1. **¿Apruebas la opción C de arquitectura** (Node solo en build-time, `@smogon/calc` en cliente, Laravel para API + rule engine)? Es la decisión que más condiciona todo.

2. **Formato prioritario: ¿dobles (VGC) o singles?** El brief habla de Fake Out, Intimidate, redirección, Wide Guard, spread damage — todo eso es **dobles**. Asumo **dobles VGC como formato principal** y singles como posible extensión. Confírmalo, porque cambia la mitad de las reglas.

3. **Idiomas: ¿ES + EN desde el principio, o solo EN?** Las explicaciones son el producto, y traducirlas después es caro. El mercado competitivo es mayoritariamente anglófono; tú eres español. Mi recomendación: **estructura i18n desde el día 1, contenido en EN primero, ES en paralelo.**

4. **Nombre y dominio.** Recomiendo evitar "Pokémon" y también "ChampionsLab" (existe `championslab.xyz`) y "ChampDex"/"ChampsDex"/"ChampTeams" (todos ocupados). Hay que buscar algo libre.

5. **¿Repo público o privado?** Público = GitHub Actions gratis ilimitado y credibilidad en la comunidad. Privado = Actions limitadas. Recomiendo **público**, con los datos derivados commiteados.

6. **¿Escribo antes de la Fase 1 un `--read-claude-*.md` con las mecánicas de Champions**, fórmulas y rutas de datos, para que las sesiones futuras no tengan que redescubrirlo? Es barato y evita errores.

---

## 12. FUENTES CONSULTADAS

**Juego y reglamento**
- [Pokémon Champions — Wikipedia](https://en.wikipedia.org/wiki/Pok%C3%A9mon_Champions)
- [Play! Pokémon Competitions Transition to Pokémon Champions — Pokemon.com](https://www.pokemon.com/us/news/play-pokemon-competitions-transition-to-pokemon-champions-on-april-and-may-2026)
- [Ranked Battle Regulation M-C — Serebii](https://www.serebii.net/pokemonchampions/rankedbattle/regulationm-c.shtml)
- [Regulation M-C — Pokémon Zone](https://www.pokemon-zone.com/champions/regulations/m-c/)
- [Regulation M-C — Victory Road](https://victoryroad.pro/champions-regulations/)
- [All 34 Pokémon Added in Regulation MC — Vice](https://www.vice.com/en/article/pokemon-champions-regulation-mc-all-new-pokemon-list/)
- [Regulation M-B Double Battles Overview — Pokemon.com](https://www.pokemon.com/us/features/pokemon-champions-regulation-m-b-double-battles-overview)

**Sistema SP**
- [Stat Points & EVs Explained — ChampDex](https://champdex.com/guides/stat-points)
- [SP System Explained — Switchblade Gaming](https://www.switchbladegaming.com/pokemon-champions/sp-system-explained/)
- [Pokémon Champions Has No IVs — genpkm](https://genpkm.com/blog/pokemon-champions-no-ivs-stat-points-competitive-guide-2026)
- [EV to Stat Point Converter — ChampDex](https://champdex.com/tools/ev-converter)
- [ChampCalc](https://champ-calc.vercel.app/)

**Fuentes de datos**
- [smogon/pokemon-showdown — data/mods](https://github.com/smogon/pokemon-showdown/tree/master/data/mods)
- [Smogon usage stats 2026-08](https://www.smogon.com/stats/2026-08/)
- [otterlyclueless/pokemon-champions-data](https://github.com/otterlyclueless/pokemon-champions-data)
- [jamesrcode/project-red-data](https://github.com/jamesrcode/project-red-data)
- [hamtaro626/pokefilter](https://github.com/hamtaro626/pokefilter)
- [smogon/damage-calc](https://github.com/smogon/damage-calc)
- [NCP-VGC-Damage-Calculator](https://github.com/nerd-of-now/NCP-VGC-Damage-Calculator)
- [pkmn/ps](https://github.com/pkmn/ps)
- [PokéAPI](https://pokeapi.co/) · [PokeAPI/sprites LICENCE](https://github.com/PokeAPI/sprites/blob/master/LICENCE.txt)

**Competidores**
- [ChampDex](https://champdex.com/) · [Pikalytics](https://www.pikalytics.com/) · [ChampTeams.gg](https://champteams.gg/browse) · [MetaVGC](https://metavgc.com/) · [Champions Lab](https://championslab.xyz/team-builder) · [Pokémon Zone](https://www.pokemon-zone.com/champions/) · [ChampsDex](https://champsdex.com/) · [Limitless VGC](https://limitlessvgc.com/) · [Victory Road](https://victoryroad.pro/)

**Legal**
- [Terms of Use — Pokemon.com](https://www.pokemon.com/us/legal/terms-of-use)
- [Media Usage Guidelines — TPCi Press Site](https://pokemon.gamespress.com/Media-Usage-Guidelines)
- [Copyright Infringement Claims — Pokemon.com](https://www.pokemon.com/us/legal/copyright)
- [Nintendo takes action against IP infringements (2025) — EU IP Helpdesk](https://intellectual-property-helpdesk.ec.europa.eu/news-events/news/nintendo-takes-action-against-ip-infringements-amid-switch-2-launch-disney-and-universal-sue-2025-06-13_en)

**Hosting**
- [The Free Tier Is Almost Dead — bex.co (sep 2026)](https://bex.co/blog/2026/09/12/side-project-hosting-cost-render-railway-fly-hetzner)
- [Platforms with a real free tier in 2026 — Render](https://render.com/articles/platforms-with-a-real-free-tier-for-developers-in-2026)
- [Railway vs Render vs Fly.io for Solo Developers 2026](https://devtoolpicks.com/blog/railway-vs-render-vs-fly-io-solo-developers-2026)

---

*Documento de investigación. No se ha escrito código. Pendiente de aprobación para pasar a Fase 1.*
