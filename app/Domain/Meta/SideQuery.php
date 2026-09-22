<?php

namespace App\Domain\Meta;

final class SideQuery
{
    public const SIDES = "lados as (
        select r.id as replay_id,
               t.side,
               case t.side when 'p1' then r.p1_team_size else r.p2_team_size end as declarado,
               count(*) filter (where t.brought) as visto
        from replays r
        join replay_teams t on t.replay_id = r.id
        where r.format_id = ?
          and coalesce(r.elo_bucket, 0) >= ?
        group by r.id, t.side, r.p1_team_size, r.p2_team_size
    )";

    public const COMPLETE = 'completos as (
        select replay_id, side
        from lados
        where declarado is not null and visto = declarado
    )';

    public const VERSUS = 'versus as (
        select c.replay_id, c.side
        from completos c
        join replay_teams t on t.replay_id = c.replay_id and t.side <> c.side and t.species_id = ?
        group by c.replay_id, c.side
    )';
}
