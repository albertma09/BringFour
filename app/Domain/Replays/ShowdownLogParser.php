<?php

namespace App\Domain\Replays;

class ShowdownLogParser
{
    public const VERSION = '2.0.0';

    private const SWITCH_DIRECTIVES = ['switch', 'drag', 'replace'];

    private const MAX_DECISION_SECONDS = 1800;

    private ParsedReplay $result;

    private BattleState $state;

    private ?ParsedTurn $turn = null;

    private array $leadsCaptured = ['p1' => 0, 'p2' => 0];

    private array $turnStartHp = [];

    private bool $started = false;

    private bool $turnHadMove = false;

    private bool $turnHadFaint = false;

    private ?int $lastTimestamp = null;

    public function parse(string $log): ParsedReplay
    {
        $this->result = new ParsedReplay();
        $this->state = new BattleState();
        $this->turn = null;
        $this->leadsCaptured = ['p1' => 0, 'p2' => 0];
        $this->turnStartHp = [];
        $this->started = false;
        $this->turnHadMove = false;
        $this->turnHadFaint = false;
        $this->lastTimestamp = null;

        foreach (explode("\n", $log) as $line) {
            if ($line === '' || $line[0] !== '|') {
                continue;
            }

            $this->dispatch(explode('|', substr($line, 1)));
        }

        $this->closeTurn();
        $this->result->openTeamSheets = $this->hasOpenTeamSheets($this->result->rules);
        $this->result->resolveWinnerSide();

        return $this->result;
    }

    private function dispatch(array $parts): void
    {
        $directive = $parts[0] ?? '';

        match (true) {
            $directive === 'gametype' => $this->result->gameType = $parts[1] ?? null,
            $directive === 'player' => $this->readPlayer($parts),
            $directive === 'rule' => $this->result->rules[] = $parts[1] ?? '',
            $directive === 'poke' => $this->readPreview($parts),
            $directive === 'teamsize' => $this->readTeamSize($parts),
            $directive === 'teampreview' => $this->readTeamPreview(),
            $directive === 'start' => $this->started = true,
            $directive === 'turn' => $this->openTurn((int) ($parts[1] ?? 0)),
            $directive === 't:' => $this->readTimestamp($parts),
            $directive === 'win' => $this->result->winnerName = $parts[1] ?? null,
            $directive === 'tie' => $this->result->tie = true,
            $directive === 'move' => $this->readMove($parts),
            $directive === 'cant' => $this->readCant($parts),
            $directive === 'faint' => $this->readFaint($parts),
            $directive === 'detailschange' => $this->readFormeChange($parts),
            $directive === '-damage' || $directive === '-heal' || $directive === '-sethp' => $this->readHp($parts),
            $directive === '-weather' => $this->state->setWeather($this->effectName($parts[1] ?? '')),
            $directive === '-fieldstart' => $this->state->startField($this->effectName($parts[1] ?? '')),
            $directive === '-fieldend' => $this->state->endField($this->effectName($parts[1] ?? '')),
            $directive === '-sidestart' => $this->readSide($parts, true),
            $directive === '-sideend' => $this->readSide($parts, false),
            $directive === '-ability' => $this->readReveal($parts, 'ability'),
            $directive === '-item' || $directive === '-enditem' => $this->readReveal($parts, 'item'),
            in_array($directive, self::SWITCH_DIRECTIVES, true) => $this->readSwitch($directive, $parts),
            default => null,
        };
    }

    private function readPlayer(array $parts): void
    {
        $side = $parts[1] ?? '';

        if (! in_array($side, ['p1', 'p2'], true)) {
            return;
        }

        $name = $parts[2] ?? '';

        if ($name === '') {
            return;
        }

        $this->result->players[$side] = $name;
        $rating = $parts[4] ?? '';
        $this->result->ratings[$side] = is_numeric($rating) ? (int) $rating : null;
    }

    private function readPreview(array $parts): void
    {
        $side = $parts[1] ?? '';

        if (! in_array($side, ['p1', 'p2'], true)) {
            return;
        }

        $slug = $this->speciesFromDetails($parts[2] ?? '');

        if ($slug !== null) {
            $this->result->preview[$side][] = $slug;
        }
    }

    private function readTeamSize(array $parts): void
    {
        $side = $parts[1] ?? '';

        if (in_array($side, ['p1', 'p2'], true)) {
            $this->result->teamSize[$side] = (int) ($parts[2] ?? 0);
        }
    }

    private function readTeamPreview(): void
    {
        if ($this->result->previewSeen && $this->started) {
            $this->result->multiGame = true;
        }

        $this->result->previewSeen = true;
    }

    private function openTurn(int $number): void
    {
        $this->closeTurn();

        $this->result->turnCount = max($this->result->turnCount, $number);
        $this->turn = new ParsedTurn($number);
        $this->turn->fieldState = $this->state->snapshot();
        $this->turnStartHp = [];

        foreach ($this->turn->fieldState['activos'] as $slot => $data) {
            $this->turnStartHp[$slot] = $data['hp'];
        }

        $this->turnHadMove = false;
        $this->turnHadFaint = false;
    }

    private function closeTurn(): void
    {
        if ($this->turn !== null && $this->turn->actions !== []) {
            $this->result->turns[] = $this->turn;
        }

        $this->turn = null;
    }

    private function readTimestamp(array $parts): void
    {
        $stamp = (int) ($parts[1] ?? 0);

        if ($stamp <= 0) {
            return;
        }

        if ($this->turn !== null && $this->turn->decisionSeconds === null && $this->lastTimestamp !== null) {
            $elapsed = $stamp - $this->lastTimestamp;

            if ($elapsed >= 0 && $elapsed <= self::MAX_DECISION_SECONDS) {
                $this->turn->decisionSeconds = $elapsed;
            }
        }

        $this->lastTimestamp = $stamp;
    }

    private function readMove(array $parts): void
    {
        $slot = $this->slotOf($parts[1] ?? '');

        if ($slot === null) {
            return;
        }

        $this->turnHadMove = true;

        if ($this->turn === null) {
            return;
        }

        $action = new ParsedAction(
            side: substr($slot, 0, 2),
            slot: $slot,
            type: 'move',
            actorSlug: $this->state->speciesAt($slot),
            actorHpPct: $this->turnStartHp[$slot] ?? null,
            moveSlug: self::toId($parts[2] ?? ''),
            forced: $this->hasTag($parts, '[from]'),
        );

        [$action->targetSide, $action->targetSlot] = $this->targetOf($parts[3] ?? '');

        $this->turn->add($action);
    }

    private function readSwitch(string $directive, array $parts): void
    {
        $slot = $this->slotOf($parts[1] ?? '');

        if ($slot === null) {
            return;
        }

        $side = substr($slot, 0, 2);
        $slug = $this->speciesFromDetails($parts[2] ?? '');

        if ($slug === null) {
            return;
        }

        $outgoing = $this->state->speciesAt($slot);
        $hp = $this->hpFrom($parts[3] ?? '');

        if ($this->turn !== null) {
            $this->turn->add(new ParsedAction(
                side: $side,
                slot: $slot,
                type: 'switch',
                actorSlug: $outgoing,
                actorHpPct: $this->turnStartHp[$slot] ?? null,
                switchInSlug: $slug,
                forced: $directive === 'drag' || $this->turnHadMove || $this->turnHadFaint || $this->hasTag($parts, '[from]'),
            ));
        }

        $this->state->enter($slot, $slug, $hp);
        $this->result->brought[$side][$slug] = true;

        $slotsPerSide = $this->result->gameType === 'doubles' ? 2 : 1;

        if ($this->started && $this->leadsCaptured[$side] < $slotsPerSide && $this->result->turnCount === 0) {
            $this->result->leads[$side][$slug] = true;
            $this->leadsCaptured[$side]++;
        }
    }

    private function readCant(array $parts): void
    {
        $slot = $this->slotOf($parts[1] ?? '');

        if ($slot === null || $this->turn === null || $this->turn->hasActionFor($slot)) {
            return;
        }

        $this->turn->add(new ParsedAction(
            side: substr($slot, 0, 2),
            slot: $slot,
            type: 'cant',
            actorSlug: $this->state->speciesAt($slot),
            actorHpPct: $this->turnStartHp[$slot] ?? null,
            reason: $this->effectName($parts[2] ?? '') ?: null,
        ));
    }

    private function readFaint(array $parts): void
    {
        $slot = $this->slotOf($parts[1] ?? '');

        if ($slot === null) {
            return;
        }

        $this->turnHadFaint = true;
        $this->state->faint($slot);
    }

    private function readFormeChange(array $parts): void
    {
        $slot = $this->slotOf($parts[1] ?? '');
        $slug = $this->speciesFromDetails($parts[2] ?? '');

        if ($slot === null || $slug === null) {
            return;
        }

        $this->state->changeForme($slot, $slug);

        if ($this->turn !== null && isset($this->turn->fieldState['activos'][$slot])) {
            $this->turn->fieldState['activos'][$slot]['especie'] = $slug;
        }
    }

    private function readHp(array $parts): void
    {
        $slot = $this->slotOf($parts[1] ?? '');

        if ($slot !== null) {
            $this->state->setHp($slot, $this->hpFrom($parts[2] ?? ''));
        }
    }

    private function readSide(array $parts, bool $start): void
    {
        $side = substr($parts[1] ?? '', 0, 2);

        if (! in_array($side, ['p1', 'p2'], true)) {
            return;
        }

        $name = $this->effectName($parts[2] ?? '');

        if ($name === '') {
            return;
        }

        $start ? $this->state->startSide($side, $name) : $this->state->endSide($side, $name);
    }

    private function readReveal(array $parts, string $kind): void
    {
        $slot = $this->slotOf($parts[1] ?? '');

        if ($slot === null || $this->turn === null) {
            return;
        }

        $action = $this->turn->lastFor($slot);

        if ($action === null) {
            return;
        }

        $slug = self::toId($parts[2] ?? '');

        if ($slug === '') {
            return;
        }

        if ($kind === 'ability') {
            $action->revealedAbilitySlug ??= $slug;

            return;
        }

        $action->revealedItemSlug ??= $slug;
    }

    private function slotOf(string $reference): ?string
    {
        $slot = substr(trim($reference), 0, 3);

        return preg_match('/^p[12][a-c]$/', $slot) === 1 ? $slot : null;
    }

    private function targetOf(string $reference): array
    {
        $reference = trim($reference);
        $side = substr($reference, 0, 2);

        if (! in_array($side, ['p1', 'p2'], true)) {
            return [null, null];
        }

        return [$side, $this->slotOf($reference)];
    }

    private function hpFrom(string $value): ?int
    {
        $value = trim(explode(' ', trim($value))[0] ?? '');

        if ($value === '0') {
            return 0;
        }

        if ($value === '' || ! str_contains($value, '/')) {
            return null;
        }

        [$current, $max] = array_map('intval', explode('/', $value, 2));

        return $max > 0 ? (int) round(100 * $current / $max) : 0;
    }

    private function effectName(string $value): string
    {
        $value = trim($value);

        if (str_contains($value, ':')) {
            $value = trim(explode(':', $value, 2)[1]);
        }

        return self::toId($value);
    }

    private function hasTag(array $parts, string $tag): bool
    {
        foreach (array_slice($parts, 3) as $part) {
            if (str_contains($part, $tag)) {
                return true;
            }
        }

        return false;
    }

    private function speciesFromDetails(string $details): ?string
    {
        $name = trim(explode(',', $details)[0] ?? '');

        return $name === '' ? null : self::toId($name);
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
