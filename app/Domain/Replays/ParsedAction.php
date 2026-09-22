<?php

namespace App\Domain\Replays;

class ParsedAction
{
    public function __construct(
        public string $side,
        public string $slot,
        public string $type,
        public ?string $actorSlug = null,
        public ?int $actorHpPct = null,
        public ?string $moveSlug = null,
        public ?string $targetSide = null,
        public ?string $targetSlot = null,
        public ?string $switchInSlug = null,
        public bool $forced = false,
        public ?string $reason = null,
        public ?string $revealedItemSlug = null,
        public ?string $revealedAbilitySlug = null,
    ) {}

    public function isDecision(): bool
    {
        if ($this->type === 'cant') {
            return false;
        }

        return ! $this->forced && $this->actorSlug !== null;
    }
}
