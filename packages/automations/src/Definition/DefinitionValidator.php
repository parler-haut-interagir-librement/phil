<?php

declare(strict_types=1);

namespace Phil\Automations\Definition;

use Phil\Automations\Catalogue\Catalogue;
use Phil\Automations\Exception\InvalidDefinition;
use Phil\Automations\Exception\UnknownComponent;

/**
 * Checks a definition against the catalogue before it is published: its trigger and its actions registered, with the
 * options they take, its conditions in a known language and readable.
 */
final readonly class DefinitionValidator
{
    public function __construct(
        private Catalogue $catalogue,
    ) {
    }

    /**
     * Every problem of the definition; none when it may be published.
     *
     * @return list<string>
     */
    public function problems(AutomationDefinition $definition): array
    {
        try {
            $problems = $this->catalogue->trigger($definition->trigger->type)->describe()->problems($definition->trigger->options);
        } catch (UnknownComponent $unknownComponent) {
            $problems = [$unknownComponent->getMessage()];
        }

        foreach ($definition->allSteps() as $step) {
            foreach ($this->stepProblems($step) as $problem) {
                $problems[] = \sprintf('Step "%s": %s', $step->id, $problem);
            }
        }

        return $problems;
    }

    /**
     * @throws InvalidDefinition with every problem, when there is one
     */
    public function validate(AutomationDefinition $definition): void
    {
        $problems = $this->problems($definition);

        if ([] !== $problems) {
            throw InvalidDefinition::because(implode(' ', $problems));
        }
    }

    /**
     * @return list<string>
     */
    private function stepProblems(Step $step): array
    {
        try {
            if (StepKind::Action === $step->kind) {
                return $this->catalogue->action((string) $step->action)->describe()->problems($step->options);
            }

            if ($step->condition instanceof Condition) {
                $this->catalogue->evaluator($step->condition->language)->check($step->condition->source);
            }
        } catch (UnknownComponent|InvalidDefinition $problem) {
            return [$problem->getMessage()];
        }

        return [];
    }
}
