<?php

declare(strict_types=1);

namespace Phil\OnboardingBundle\Domain\Flow;

use Phil\OnboardingBundle\Domain\Exception\InvalidFlow;

/**
 * An onboarding flow: its identifier, the translation keys of its title and description in their domain, and one to a
 * hundred steps, each once, in their order.
 */
final readonly class FlowDefinition
{
    public const int MAX_STEPS = 100;

    /** @var list<StepDefinition> the steps, in their order */
    public array $steps;

    /** @var array<string, StepDefinition> */
    private array $byId;

    /**
     * @param list<StepDefinition> $steps in the order of declaration; `before` and `after` reorder them
     *
     * @throws InvalidFlow when the declaration breaks a rule
     */
    public function __construct(
        public string $id,
        public string $title,
        array $steps,
        public ?string $description = null,
        public string $translationDomain = 'messages',
    ) {
        Identifier::check($id, 'flow');
        Identifier::text($title, \sprintf('title of the flow "%s"', $id), StepDefinition::MAX_TITLE);

        if (null !== $description) {
            Identifier::text($description, \sprintf('description of the flow "%s"', $id), StepDefinition::MAX_DESCRIPTION);
        }

        if (1 !== preg_match('/^[A-Za-z0-9_.+-]{1,64}$/D', $translationDomain)) {
            throw InvalidFlow::because(\sprintf('The translation domain "%s" of the flow "%s" is malformed.', $translationDomain, $id));
        }

        if ([] === $steps || \count($steps) > self::MAX_STEPS) {
            throw InvalidFlow::because(\sprintf('The flow "%s" has %d steps, not one to %d.', $id, \count($steps), self::MAX_STEPS));
        }

        $byId = [];

        foreach ($steps as $step) {
            if (isset($byId[$step->id])) {
                throw InvalidFlow::because(\sprintf('The flow "%s" declares the step "%s" twice.', $id, $step->id));
            }

            $byId[$step->id] = $step;
        }

        $this->byId = $byId;
        $this->steps = Ordering::sort($steps);
    }

    public function step(string $id): ?StepDefinition
    {
        return $this->byId[$id] ?? null;
    }
}
