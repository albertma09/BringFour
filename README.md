# BringFour

Laboratorio de competitivo para **Pokémon Champions**. Construye, analiza y entiende equipos — y aprende a decidir mejor.

> Ves 6 en el Team Preview y traes 4. Esa decisión, y el plan que hay detrás, es donde se gana la partida. BringFour trabaja ahí. **No juega tus turnos.**

`UNDERSTANDING > RECOMMENDATION` · `EXPLANATION > AUTOMATION` · `LEARNING > OPTIMAL PLAY`

**Estado:** Fase 1 (capa de datos) en curso. Sin interfaz todavía.

---

## Requisitos

- PHP 8.3 con `pdo_pgsql` (en Windows: el binario de WAMP + el `php.ini` de este repo)
- Composer 2
- Node 22
- Docker (para PostgreSQL 16)

## Arranque

```bash
./dev.ps1 up                              # PostgreSQL en el puerto 5434
./dev.ps1 composer install
cp .env.example .env && ./dev.ps1 artisan key:generate

cd ingest
npm install
node node_modules/pokemon-showdown/build  # genera dist/, obligatorio
node showdown-data.mjs                    # regenera data/
cd ..

./dev.ps1 artisan migrate
./dev.ps1 artisan data:sync               # data/ -> PostgreSQL
./dev.ps1 test
```

## Comandos

| Comando | Qué hace |
|---|---|
| `./dev.ps1 up` / `down` | Arranca o para PostgreSQL |
| `./dev.ps1 artisan <...>` | Artisan con PHP 8.3 y el `php.ini` del proyecto |
| `./dev.ps1 test` | Suite de Pest |
| `./dev.ps1 psql` | Consola de PostgreSQL |
| `node ingest/showdown-data.mjs` | Regenera `data/` desde los mods de Showdown |
| `node ingest/replay-collector.mjs` | Recolecta replays públicos |

## Arquitectura

```
GitHub Actions (Node)  →  JSON versionado en Git  →  PostgreSQL (Laravel)
Vue 3 + TS             →  @smogon/calc en cliente (cálculo de daño)
Laravel                →  REST API + RuleEngine determinista + LLM opcional
```

Node solo corre en tiempo de build. En producción hay una máquina: PHP-FPM, PostgreSQL y estáticos. El cálculo de daño no se reimplementa en PHP.

## Estructura

```
app/Domain/Stats/     Fórmulas de estadísticas y validación de SP
app/Console/Commands/ data:sync
ingest/               Scripts Node de ingesta y recolección
data/                 Catálogo y legalidad generados (commiteados)
data/raw/replays/     Archivo de replays (fuera de git)
docs/                 Investigación de mercado, fuentes y decisiones
```

## Documentación

| Fichero | Contenido |
|---|---|
| [`--read-claude-champions-mechanics.md`](--read-claude-champions-mechanics.md) | Mecánicas verificadas, fuentes, trampas encontradas |
| [`docs/DECISIONES.md`](docs/DECISIONES.md) | Decisiones cerradas |
| [`docs/INVESTIGACION-FASE-1.md`](docs/INVESTIGACION-FASE-1.md) | Competidores, fuentes, licencias, riesgo legal, roadmap |
| [`docs/INVESTIGACION-BATTLE-LOGS.md`](docs/INVESTIGACION-BATTLE-LOGS.md) | Replays como fuente de comportamiento |

## Datos y atribución

Datos derivados de [Pokémon Showdown](https://github.com/smogon/pokemon-showdown) (MIT) y de sus replays públicos. Las estadísticas que se publiquen serán siempre agregadas y anónimas.

**No afiliado a Nintendo, Game Freak ni The Pokémon Company.** Pokémon y los nombres de Pokémon son marcas de sus respectivos titulares. Proyecto sin ánimo comercial sobre propiedad intelectual ajena, hecho por y para la comunidad competitiva.
