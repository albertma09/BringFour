<?php

namespace App\Domain\Replays;

class ParsedReveal
{
    public function __construct(
        public string $side,
        public string $speciesSlug,
        public string $kind,
        public string $valueSlug,
        public ?int $turnNo = null,
    ) {}

    public function key(): string
    {
        return $this->side.'|'.$this->speciesSlug.'|'.$this->kind;
    }
}
