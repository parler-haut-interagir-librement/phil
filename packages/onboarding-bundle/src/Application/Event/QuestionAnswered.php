<?php

declare(strict_types=1);

namespace Phil\OnboardingBundle\Application\Event;

/**
 * A user answered the question of a step; the event carries identifiers only: the choices picked, not what they grant.
 */
final readonly class QuestionAnswered
{
    /**
     * @param list<string> $choices
     */
    public function __construct(
        public string $user,
        public string $flow,
        public string $step,
        public array $choices,
    ) {
    }
}
