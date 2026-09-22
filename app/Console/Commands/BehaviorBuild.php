<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class BehaviorBuild extends Command
{
    protected $signature = 'behavior:build
        {--format=gen9championsvgc2026regmc : Showdown id del formato}
        {--min=30 : Decisiones minimas para guardar un contexto}';

    protected $description = 'Agrega las decisiones observadas en priors de conducta por matchup';

    public const PRIOR_TYPE = 'action_given_matchup';

    private const CHUNK = 500;

    private array $slugById = [];

    public function handle(): int
    {
        $format = DB::table('formats')->where('showdown_id', $this->option('format'))->first();

        if (! $format) {
            $this->error('Formato desconocido: '.$this->option('format'));

            return self::FAILURE;
        }

        if (! $format->process_replays) {
            $this->error('Ese formato no esta marcado como procesable.');

            return self::FAILURE;
        }

        $this->slugById = DB::table('species')->pluck('slug', 'id')->all();
        $min = (int) $this->option('min');

        DB::table('behavior_priors')
            ->where('format_id', $format->id)
            ->where('prior_type', self::PRIOR_TYPE)
            ->delete();

        $grupo = [];
        $clave = null;
        $buffer = [];
        $totales = ['contextos' => 0, 'guardados' => 0, 'filas' => 0, 'descartados' => 0];

        foreach (DB::cursor($this->query(), [$format->id, $format->id]) as $row) {
            $actual = [$row->actor_species_id, $row->rival_species_id, $row->fase, $row->elo_bucket];

            if ($clave !== null && $actual !== $clave) {
                $this->emit($format, $clave, $grupo, $min, $buffer, $totales);
                $grupo = [];
            }

            $clave = $actual;
            $grupo[$row->action_key] = (int) $row->n;

            if (count($buffer) >= self::CHUNK) {
                $this->flush($buffer);
            }
        }

        if ($clave !== null) {
            $this->emit($format, $clave, $grupo, $min, $buffer, $totales);
        }

        $this->flush($buffer);

        $this->newLine();
        $this->table(
            ['contextos vistos', 'contextos guardados', 'filas', 'descartados por muestra'],
            [[$totales['contextos'], $totales['guardados'], $totales['filas'], $totales['descartados']]],
        );

        return self::SUCCESS;
    }

    private function query(): string
    {
        return '
            with activos as (
                select t.id as turn_id,
                       left(kv.key, 2) as side,
                       s.id as species_id
                from replay_turns t
                join replays r on r.id = t.replay_id
                cross join lateral jsonb_each(t.field_state->\'activos\') kv
                join species s on s.slug = kv.value->>\'especie\'
                where r.format_id = ?
            ),
            decisiones as (
                select a.replay_turn_id as turn_id,
                       a.side,
                       a.actor_species_id,
                       case when a.action_type = \'switch\' then \'switch\' else \'move:\' || m.slug end as action_key,
                       coalesce(r.elo_bucket, 0) as elo_bucket,
                       case when t.turn_no = 1 then \'apertura\' else \'medio\' end as fase
                from replay_actions a
                join replay_turns t on t.id = a.replay_turn_id
                join replays r on r.id = t.replay_id
                left join moves m on m.id = a.move_id
                where r.format_id = ?
                  and a.forced = false
                  and a.actor_species_id is not null
                  and (a.action_type = \'switch\' or (a.action_type = \'move\' and a.move_id is not null))
            )
            select d.actor_species_id,
                   v.species_id as rival_species_id,
                   d.fase,
                   d.elo_bucket,
                   d.action_key,
                   count(*) as n
            from decisiones d
            join activos v on v.turn_id = d.turn_id and v.side <> d.side
            group by 1, 2, 3, 4, 5
            order by 1, 2, 3, 4
        ';
    }

    private function emit(object $format, array $clave, array $grupo, int $min, array &$buffer, array &$totales): void
    {
        $totales['contextos']++;
        $total = array_sum($grupo);

        if ($total < $min) {
            $totales['descartados']++;

            return;
        }

        [$actorId, $rivalId, $fase, $eloBucket] = $clave;

        $context = [
            'actor' => $this->slugById[$actorId] ?? null,
            'rival' => $this->slugById[$rivalId] ?? null,
            'fase' => $fase,
        ];

        if ($context['actor'] === null || $context['rival'] === null) {
            $totales['descartados']++;

            return;
        }

        $json = json_encode($context, JSON_UNESCAPED_UNICODE);
        $hash = hash('sha256', $json);
        $now = now();

        foreach ($grupo as $actionKey => $n) {
            $buffer[] = [
                'regulation_id' => $format->regulation_id,
                'format_id' => $format->id,
                'elo_bucket' => (int) $eloBucket,
                'prior_type' => self::PRIOR_TYPE,
                'context_hash' => $hash,
                'context' => $json,
                'action_key' => $actionKey,
                'action' => json_encode($this->action($actionKey)),
                'n' => $n,
                'pct' => round(100 * $n / $total, 2),
                'computed_at' => $now,
            ];
            $totales['filas']++;
        }

        $totales['guardados']++;
    }

    private function action(string $actionKey): array
    {
        if ($actionKey === 'switch') {
            return ['tipo' => 'switch'];
        }

        return ['tipo' => 'move', 'movimiento' => substr($actionKey, 5)];
    }

    private function flush(array &$buffer): void
    {
        if ($buffer === []) {
            return;
        }

        foreach (array_chunk($buffer, self::CHUNK) as $chunk) {
            DB::table('behavior_priors')->insert($chunk);
        }

        $buffer = [];
    }
}
