<?php

declare(strict_types=1);

namespace Phil\OnboardingBundle\Domain\Flow;

use Phil\OnboardingBundle\Domain\Exception\InvalidAnswer;
use Phil\OnboardingBundle\Domain\Exception\InvalidFlow;

/**
 * The question of a step: two to twenty choices, each once, of which the person picks one, or as many as they like
 * when the question allows several. The step's title asks it.
 */
final readonly class Question
{
    public const int MAX_CHOICES = 20;

    /** @var array<string, Choice> by identifier, in their order */
    public array $choices;

    /**
     * @param list<Choice> $choices in their order
     *
     * @throws InvalidFlow when the declaration breaks a rule
     */
    public function __construct(
        array $choices,
        public bool $multiple = false,
    ) {
        if (\count($choices) < 2 || \count($choices) > self::MAX_CHOICES) {
            throw InvalidFlow::because(\sprintf('A question offers 2 to %d choices, not %d.', self::MAX_CHOICES, \count($choices)));
        }

        $byId = [];

        foreach ($choices as $choice) {
            if (isset($byId[$choice->id])) {
                throw InvalidFlow::because(\sprintf('A question offers the choice "%s" twice.', $choice->id));
            }

            $byId[$choice->id] = $choice;
        }

        $this->choices = $byId;
    }

    /**
     * Whether a choice asks the application for anything.
     */
    public function grantsAnything(): bool
    {
        return array_any($this->choices, static fn (Choice $choice): bool => $choice->grantsAnything());
    }

    /**
     * The choices a person picked, in the question's order.
     *
     * @param list<string> $picked the identifiers of the choices
     *
     * @return list<Choice>
     *
     * @throws InvalidAnswer when nothing is picked, a choice is unknown or picked twice, or several are picked where
     *                       one is asked
     */
    public function pick(array $picked): array
    {
        if ([] === $picked) {
            throw InvalidAnswer::because('An answer picks at least one choice.');
        }

        if (!$this->multiple && \count($picked) > 1) {
            throw InvalidAnswer::because('This question takes one choice.');
        }

        if (\count(array_unique($picked)) !== \count($picked)) {
            throw InvalidAnswer::because('An answer picks each choice once.');
        }

        foreach ($picked as $id) {
            if (!isset($this->choices[$id])) {
                throw InvalidAnswer::because('An answer picks among the choices of the question.');
            }
        }

        return array_values(array_filter($this->choices, static fn (Choice $choice): bool => \in_array($choice->id, $picked, true)));
    }
}
