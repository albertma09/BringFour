<?php

namespace App\Domain\Replays;

class BattleState
{
    private array $species = [];

    private array $hp = [];

    private ?string $weather = null;

    private ?string $terrain = null;

    private array $field = [];

    private array $sides = ['p1' => [], 'p2' => []];

    public function enter(string $slot, string $slug, ?int $hp): void
    {
        $this->species[$slot] = $slug;
        $this->hp[$slot] = $hp;
    }

    public function changeForme(string $slot, string $slug): void
    {
        if (isset($this->species[$slot])) {
            $this->species[$slot] = $slug;
        }
    }

    public function setHp(string $slot, ?int $hp): void
    {
        if (array_key_exists($slot, $this->species)) {
            $this->hp[$slot] = $hp;
        }
    }

    public function faint(string $slot): void
    {
        $this->hp[$slot] = 0;
    }

    public function speciesAt(string $slot): ?string
    {
        return $this->species[$slot] ?? null;
    }

    public function hpAt(string $slot): ?int
    {
        return $this->hp[$slot] ?? null;
    }

    public function setWeather(?string $name): void
    {
        $this->weather = ($name === null || $name === 'none') ? null : $name;
    }

    public function startField(string $name): void
    {
        if (str_ends_with($name, 'terrain')) {
            $this->terrain = $name;

            return;
        }

        $this->field[$name] = true;
    }

    public function endField(string $name): void
    {
        if ($this->terrain === $name) {
            $this->terrain = null;
        }

        unset($this->field[$name]);
    }

    public function startSide(string $side, string $name): void
    {
        if (isset($this->sides[$side])) {
            $this->sides[$side][$name] = true;
        }
    }

    public function endSide(string $side, string $name): void
    {
        unset($this->sides[$side][$name]);
    }

    public function actives(): array
    {
        $out = [];

        foreach ($this->species as $slot => $slug) {
            if ($this->hp[$slot] !== null && $this->hp[$slot] <= 0) {
                continue;
            }

            $out[$slot] = ['especie' => $slug, 'hp' => $this->hp[$slot]];
        }

        ksort($out);

        return $out;
    }

    public function activesOf(string $side): array
    {
        $out = [];

        foreach ($this->actives() as $slot => $data) {
            if (str_starts_with($slot, $side)) {
                $out[] = $data['especie'];
            }
        }

        sort($out);

        return array_values(array_unique($out));
    }

    public function snapshot(): array
    {
        return [
            'activos' => $this->actives(),
            'clima' => $this->weather,
            'terreno' => $this->terrain,
            'campo' => array_keys($this->field),
            'lados' => [
                'p1' => array_keys($this->sides['p1']),
                'p2' => array_keys($this->sides['p2']),
            ],
        ];
    }
}
