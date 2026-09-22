<?php

namespace App\Domain\Replays;

class ShowdownLogParser
{
    public const VERSION = '1.0.0';

    private const SWITCH_DIRECTIVES = ['switch', 'drag', 'replace'];

    public function parse(string $log): ParsedReplay
    {
        $result = new ParsedReplay();
        $started = false;
        $leadsCaptured = ['p1' => 0, 'p2' => 0];

        foreach (explode("\n", $log) as $line) {
            if ($line === '' || $line[0] !== '|') {
                continue;
            }

            $parts = explode('|', substr($line, 1));
            $directive = $parts[0] ?? '';

            match (true) {
                $directive === 'gametype' => $result->gameType = $parts[1] ?? null,
                $directive === 'player' => $this->readPlayer($result, $parts),
                $directive === 'rule' => $result->rules[] = $parts[1] ?? '',
                $directive === 'poke' => $this->readPreview($result, $parts),
                $directive === 'teamsize' => $this->readTeamSize($result, $parts),
                $directive === 'start' => $started = true,
                $directive === 'turn' => $result->turnCount = max($result->turnCount, (int) ($parts[1] ?? 0)),
                $directive === 'win' => $result->winnerName = $parts[1] ?? null,
                $directive === 'tie' => $result->tie = true,
                in_array($directive, self::SWITCH_DIRECTIVES, true) => $this->readSwitch($result, $parts, $started, $leadsCaptured),
                default => null,
            };

            if ($directive === 'teampreview' && $result->previewSeen) {
                $result->multiGame = $result->multiGame || $started;
            }

            if ($directive === 'teampreview') {
                $result->previewSeen = true;
            }
        }

        $result->openTeamSheets = $this->hasOpenTeamSheets($result->rules);
        $result->resolveWinnerSide();

        return $result;
    }

    private function readPlayer(ParsedReplay $result, array $parts): void
    {
        $side = $parts[1] ?? '';

        if (! in_array($side, ['p1', 'p2'], true)) {
            return;
        }

        $name = $parts[2] ?? '';

        if ($name === '') {
            return;
        }

        $result->players[$side] = $name;
        $rating = $parts[4] ?? '';
        $result->ratings[$side] = is_numeric($rating) ? (int) $rating : null;
    }

    private function readPreview(ParsedReplay $result, array $parts): void
    {
        $side = $parts[1] ?? '';

        if (! in_array($side, ['p1', 'p2'], true)) {
            return;
        }

        $slug = $this->speciesFromDetails($parts[2] ?? '');

        if ($slug === null) {
            return;
        }

        $result->preview[$side][] = $slug;
    }

    private function readTeamSize(ParsedReplay $result, array $parts): void
    {
        $side = $parts[1] ?? '';

        if (in_array($side, ['p1', 'p2'], true)) {
            $result->teamSize[$side] = (int) ($parts[2] ?? 0);
        }
    }

    private function readSwitch(ParsedReplay $result, array $parts, bool $started, array &$leadsCaptured): void
    {
        $position = $parts[1] ?? '';
        $side = substr($position, 0, 2);

        if (! in_array($side, ['p1', 'p2'], true)) {
            return;
        }

        $slug = $this->speciesFromDetails($parts[2] ?? '');

        if ($slug === null) {
            return;
        }

        $result->brought[$side][$slug] = true;

        $slotsPerSide = $result->gameType === 'doubles' ? 2 : 1;

        if ($started && $leadsCaptured[$side] < $slotsPerSide && $result->turnCount === 0) {
            $result->leads[$side][$slug] = true;
            $leadsCaptured[$side]++;
        }
    }

    private function speciesFromDetails(string $details): ?string
    {
        $name = trim(explode(',', $details)[0] ?? '');

        if ($name === '') {
            return null;
        }

        return self::toId($name);
    }

    private function hasOpenTeamSheets(array $rules): bool
    {
        foreach ($rules as $rule) {
            if (stripos($rule, 'open team sheet') !== false) {
                return true;
            }
        }

        return false;
    }

    public static function toId(string $value): string
    {
        return strtolower(preg_replace('/[^A-Za-z0-9]+/', '', $value) ?? '');
    }
}
