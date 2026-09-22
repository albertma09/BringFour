<?php

namespace App\Console\Commands;

use App\Domain\Meta\SideQuery;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class MetaBringRates extends Command
{
    protected $signature = 'meta:bring-rates
        {--format=gen9championsvgc2026regmc : Showdown id del formato}
        {--elo=0 : Corte minimo de ELO}
        {--min=30 : Muestra minima para publicar un porcentaje}
        {--top=25 : Cuantas filas mostrar}';

    protected $description = 'Porcentaje de veces que una especie del Team Preview acaba entrando al combate';

    public function handle(): int
    {
        $formatId = DB::table('formats')->where('showdown_id', $this->option('format'))->value('id');

        if (! $formatId) {
            $this->error('Formato desconocido: '.$this->option('format'));

            return self::FAILURE;
        }

        $min = (int) $this->option('min');
        $elo = (int) $this->option('elo');

        $rows = DB::select(
            'with '.SideQuery::SIDES.', '.SideQuery::COMPLETE.'
            select s.name,
                   count(*) as n,
                   count(*) filter (where t.brought) as traido,
                   round(100.0 * count(*) filter (where t.brought) / count(*), 1) as bring_pct,
                   round(100.0 * count(*) filter (where t.lead) / count(*), 1) as lead_pct
            from completos c
            join replay_teams t on t.replay_id = c.replay_id and t.side = c.side
            join species s on s.id = t.species_id
            group by s.name
            having count(*) >= ?
            order by count(*) desc
            limit ?',
            [$formatId, $elo, $min, (int) $this->option('top')],
        );

        $universo = DB::selectOne(
            'with '.SideQuery::SIDES.'
            select count(*) as total,
                   count(*) filter (where declarado is not null and visto = declarado) as completos
            from lados',
            [$formatId, $elo],
        );

        $descartados = $universo->total - $universo->completos;

        $this->newLine();
        $this->line(sprintf(
            'Equipos analizados: %d de %d (%d descartados porque la partida acabo antes de que se viera a todo el equipo)',
            $universo->completos,
            $universo->total,
            $descartados,
        ));
        $this->line(sprintf('Corte de ELO: %d  ·  Muestra minima: %d', $elo, $min));
        $this->newLine();

        if ($rows === []) {
            $this->warn('Ninguna especie llega a la muestra minima todavia. Hace falta recolectar mas replays.');

            return self::SUCCESS;
        }

        $this->table(
            ['Pokemon', 'N', 'Traido', 'Bring %', 'Lead %'],
            array_map(fn ($r) => [$r->name, $r->n, $r->traido, $r->bring_pct.'%', $r->lead_pct.'%'], $rows),
        );

        $this->line('Fuente: replays publicos de Pokemon Showdown, no el ladder de Pokemon Champions.');

        return self::SUCCESS;
    }
}
