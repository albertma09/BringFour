<?php

namespace App\Domain\Replays;

class ParsedTurn
{
    public array $actions = [];

    public array $fieldState = [];

    public ?int $decisionSeconds = null;

    public function __construct(public int $number) {}

    public function add(ParsedAction $action): void
    {
        $this->actions[] = $action;
    }

    public function hasActionFor(string $slot): bool
    {
        foreach ($this->actions as $action) {
            if ($action->slot === $slot) {
                return true;
            }
        }

        return false;
    }

    public function lastFor(string $slot): ?ParsedAction
    {
        for ($i = count($this->actions) - 1; $i >= 0; $i--) {
            if ($this->actions[$i]->slot === $slot) {
                return $this->actions[$i];
            }
        }

        return null;
    }

    public function activesOf(string $side): array
    {
        $out = [];

        foreach ($this->fieldState['activos'] ?? [] as $slot => $data) {
            if (str_starts_with((string) $slot, $side) && isset($data['especie'])) {
                $out[] = $data['especie'];
            }
        }

        sort($out);

        return array_values(array_unique($out));
    }
}
