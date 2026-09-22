<?php

namespace App\Console\Commands;

use App\Domain\Meta\SideQuery;
use App\Domain\Replays\ShowdownLogParser;
use App\Domain\Stats\Proportion;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class MetaMatchup extends Command
{
    protected $signature = 'meta:matchup
        {--vs= : Especie que el rival ensena en el Team Preview}
        {--format=gen9championsvgc2026regmc : Showdown id del formato}
        {--elo=0 : Corte minimo de ELO}
        {--min=30 : Muestra minima para publicar un porcentaje}
        {--top=25 : Cuantas filas mostrar}';

    protected $description = 'Como cambia el bring rate cuando el rival ensena una especie concreta';

    public function handle(): int
    {
        $formatId = DB::table('formats')->where('showdown_id', $this->option('format'))->value('id');

        if (! $formatId) {
            $this->error('Formato desconocido: '.$this->option('format'));

            return self::FAILURE;
        }

        $vs = trim((string) $this->option('vs'));

        if ($vs === '') {
            $this->error('Falta --vs con la especie que ensena el rival.');

            return self::FAILURE;
        }

        $rival = $this->findSpecies($vs);

        if (! $rival) {
            $this->error("Especie desconocida: {$vs}");

            return self::FAILURE;
        }

        $elo = (int) $this->option('elo');
        $min = (int) $this->option('min');
        $bindings = [$formatId, $elo, $rival->id];

        $universo = DB::selectOne(
            'with '.SideQuery::SIDES.', '.SideQuery::COMPLETE.', '.SideQuery::VERSUS.'
            select count(*) as completos, count(v.side) as enfrentados
            from completos c
            left join versus v on v.replay_id = c.replay_id and v.side = c.side',
            $bindings,
        );

        $rows = DB::select(
            'with '.SideQuery::SIDES.', '.SideQuery::COMPLETE.', '.SideQuery::VERSUS.'
            select s.name,
                   count(*) filter (where v.side is not null) as n,
                   count(*) filter (where v.side is not null and t.brought) as traido,
                   count(*) filter (where v.side is null) as n_sin,
                   count(*) filter (where v.side is null and t.brought) as traido_sin
            from completos c
            join replay_teams t on t.replay_id = c.replay_id and t.side = c.side
            join species s on s.id = t.species_id
            left join versus v on v.replay_id = c.replay_id and v.side = c.side
            group by s.name',
            $bindings,
        );

        $this->newLine();
        $this->line(sprintf(
            'Lados que se enfrentaron a %s: %d de %d equipos completos',
            $rival->name,
            $universo->enfrentados,
            $universo->completos,
        ));
        $this->line(sprintf('Corte de ELO: %d  ·  Muestra minima: %d', $elo, $min));
        $this->newLine();

        $publicables = array_values(array_filter(
            array_map(fn ($row) => $this->compare($row), $rows),
            fn ($fila) => $fila['n'] >= $min && $fila['n_sin'] >= $min,
        ));

        $ocultas = count($rows) - count($publicables);

        if ($publicables === []) {
            $this->warn(sprintf(
                'Ninguna especie llega a %d observaciones en los dos grupos. Hace falta recolectar mas replays o bajar --min.',
                $min,
            ));

            return self::SUCCESS;
        }

        usort($publicables, function (array $a, array $b) {
            return [$b['significativo'], abs($b['delta'])] <=> [$a['significativo'], abs($a['delta'])];
        });

        $this->table(
            ['Pokemon', 'N', 'Trae % (con '.$rival->name.')', 'Trae % (sin)', 'Delta'],
            array_map(fn ($fila) => [
                $fila['name'],
                $fila['n'],
                $this->pct($fila['pct']),
                $this->pct($fila['pct_sin']),
                sprintf('%+.1f%s', $fila['delta'] * 100, $fila['significativo'] ? ' *' : ''),
            ], array_slice($publicables, 0, (int) $this->option('top'))),
        );

        if ($ocultas > 0) {
            $this->line(sprintf('%d especies ocultas por no llegar a la muestra minima.', $ocultas));
        }

        $this->line('* el cambio no se explica por el tamano de la muestra (intervalo de Wilson al 95%).');
        $this->line('Es una descripcion, no una causa: quien lleva '.$rival->name.' suele llevar un equipo entero alrededor.');
        $this->line('Fuente: replays publicos de Pokemon Showdown, no el ladder de Pokemon Champions.');

        return self::SUCCESS;
    }

    private function compare(object $row): array
    {
        $n = (int) $row->n;
        $nSin = (int) $row->n_sin;
        $traido = (int) $row->traido;
        $traidoSin = (int) $row->traido_sin;

        $pct = $n > 0 ? $traido / $n : 0.0;
        $pctSin = $nSin > 0 ? $traidoSin / $nSin : 0.0;

        return [
            'name' => $row->name,
            'n' => $n,
            'n_sin' => $nSin,
            'pct' => $pct,
            'pct_sin' => $pctSin,
            'delta' => $pct - $pctSin,
            'significativo' => Proportion::differsFrom($traido, $n, $pctSin),
        ];
    }

    private function pct(float $value): string
    {
        return number_format($value * 100, 1).'%';
    }

    private function findSpecies(string $value): ?object
    {
        return DB::table('species')
            ->where('slug', ShowdownLogParser::toId($value))
            ->orWhereRaw('lower(name) = ?', [mb_strtolower($value)])
            ->first();
    }
}
