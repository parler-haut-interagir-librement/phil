<?php

declare(strict_types=1);

namespace Phil\PrivacyBundle\Domain\Erasure;

use DateTimeInterface;
use Phil\PrivacyBundle\Domain\Retention\ErasureEffect;

/**
 * What a person or an operator sees of an erasure request: its state and each module's, with what each module did once
 * it completed. It never shows who the request is about, nor why an attempt failed beyond the closed reason.
 */
final readonly class ErasureProgress
{
    /**
     * @param list<ErasureStep> $steps sorted by module
     */
    public function __construct(
        public ErasureRequest $request,
        public array $steps,
    ) {
    }

    /**
     * How many modules completed, out of all.
     *
     * @return array{completed: int, total: int}
     */
    public function counts(): array
    {
        return [
            'completed' => \count(array_filter($this->steps, static fn (ErasureStep $step): bool => StepState::Completed === $step->state)),
            'total' => \count($this->steps),
        ];
    }

    /**
     * The progress as the JSON response gives it.
     *
     * @return array{id: string, state: string, inventory_version: string, requested_at: string, closed_at: string|null, completed: int, total: int, modules: list<array{module: string, state: string, reason: string|null, completed_at: string|null, effects: array<string, array{effect: string, personal_data_left: bool}>}>}
     */
    public function toArray(): array
    {
        return [
            'id' => $this->request->id,
            'state' => $this->request->state->value,
            'inventory_version' => $this->request->inventoryVersion,
            'requested_at' => $this->request->requestedAt->format(DateTimeInterface::ATOM),
            'closed_at' => $this->request->closedAt?->format(DateTimeInterface::ATOM),
            ...$this->counts(),
            'modules' => array_map(static fn (ErasureStep $step): array => [
                'module' => $step->module,
                'state' => $step->state->value,
                'reason' => $step->reason?->value,
                'completed_at' => $step->completedAt?->format(DateTimeInterface::ATOM),
                'effects' => array_map(static fn (ErasureEffect $effect): array => ['effect' => $effect->value, 'personal_data_left' => $effect->leavesPersonalData()], $step->effects),
            ], $this->steps),
        ];
    }
}
