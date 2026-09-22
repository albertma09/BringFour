<?php

namespace App\Domain\Replays;

class ParsedReplay
{
    public ?string $gameType = null;

    public array $players = ['p1' => null, 'p2' => null];

    public array $ratings = ['p1' => null, 'p2' => null];

    public array $rules = [];

    public array $preview = ['p1' => [], 'p2' => []];

    public array $teamSize = ['p1' => null, 'p2' => null];

    public array $brought = ['p1' => [], 'p2' => []];

    public array $leads = ['p1' => [], 'p2' => []];

    public array $turns = [];

    public int $turnCount = 0;

    public ?string $winnerName = null;

    public ?string $winnerSide = null;

    public bool $tie = false;

    public bool $openTeamSheets = false;

    public bool $previewSeen = false;

    public bool $multiGame = false;

    public function resolveWinnerSide(): void
    {
        if ($this->tie) {
            $this->winnerSide = 'tie';

            return;
        }

        if ($this->winnerName === null) {
            return;
        }

        foreach (['p1', 'p2'] as $side) {
            if ($this->players[$side] !== null && $this->players[$side] === $this->winnerName) {
                $this->winnerSide = $side;

                return;
            }
        }
    }

    public function broughtSlugs(string $side): array
    {
        return array_keys($this->brought[$side] ?? []);
    }

    public function leadSlugs(string $side): array
    {
        return array_keys($this->leads[$side] ?? []);
    }

    public function isUsable(): bool
    {
        return ! $this->multiGame
            && count($this->preview['p1']) > 0
            && count($this->preview['p2']) > 0;
    }
}
