<?php

declare(strict_types=1);

namespace Phil\OnboardingBundle\Application;

use Phil\OnboardingBundle\Domain\Flow\Choice;

/**
 * A user's answer to the question of a step: the choices they picked, in the question's order, and everything those
 * choices grant, each role and each subscription once.
 */
final readonly class Answer
{
    /** @var list<string> */
    public array $choices;

    /** @var list<string> */
    public array $roles;

    /** @var list<string> */
    public array $subscriptions;

    /**
     * @param list<Choice> $picked
     */
    public function __construct(
        public string $user,
        public string $flow,
        public string $step,
        array $picked,
    ) {
        $this->choices = array_map(static fn (Choice $choice): string => $choice->id, $picked);
        $this->roles = array_values(array_unique(array_merge(...array_map(static fn (Choice $choice): array => $choice->roles, $picked))));
        $this->subscriptions = array_values(array_unique(array_merge(...array_map(static fn (Choice $choice): array => $choice->subscriptions, $picked))));
    }
}
