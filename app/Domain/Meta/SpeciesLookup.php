<?php

namespace App\Domain\Meta;

use App\Domain\Replays\ShowdownLogParser;
use Illuminate\Support\Facades\DB;

final class SpeciesLookup
{
    public function find(string $value): ?object
    {
        $value = trim($value);

        if ($value === '') {
            return null;
        }

        return DB::table('species')
            ->where('slug', ShowdownLogParser::toId($value))
            ->orWhereRaw('lower(name) = ?', [mb_strtolower($value)])
            ->orWhereRaw('lower(name_es) = ?', [mb_strtolower($value)])
            ->first();
    }

    public function formatId(string $showdownId): ?int
    {
        return DB::table('formats')->where('showdown_id', $showdownId)->value('id');
    }
}
