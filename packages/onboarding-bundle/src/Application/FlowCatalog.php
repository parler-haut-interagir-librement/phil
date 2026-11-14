<?php

declare(strict_types=1);

namespace Phil\OnboardingBundle\Application;

use Phil\OnboardingBundle\Application\Exception\UnknownFlow;
use Phil\OnboardingBundle\Domain\Exception\InvalidFlow;
use Phil\OnboardingBundle\Domain\Flow\Choice;
use Phil\OnboardingBundle\Domain\Flow\Completion;
use Phil\OnboardingBundle\Domain\Flow\FlowDefinition;
use Phil\OnboardingBundle\Domain\Flow\Question;
use Phil\OnboardingBundle\Domain\Flow\StepDefinition;

/**
 * Every flow the application and its packages declare, with their steps in order. It is built once from the
 * declarations, which the container checks when it is compiled.
 *
 * @phpstan-type QuestionDeclaration array{multiple?: bool, choices: array<array-key, array{label: string, roles?: list<string>, subscriptions?: list<string>}>}
 */
final readonly class FlowCatalog
{
    /**
     * @param array<string, FlowDefinition>                          $flows
     * @param array<string, list<array{flow: string, step: string}>> $onEvent the steps each event completes
     */
    private function __construct(
        private array $flows,
        private array $onEvent,
    ) {
    }

    /**
     * @param array<string, array{title: string, description: ?string, translation_domain: string}>                                                                                                                                                                                                                       $flows by identifier, in the order of declaration
     * @param list<array{flow: string, id: string, title: string, completion: string, description: ?string, route: ?string, route_parameters: array<string, scalar>, target: ?string, event: ?string, checker: ?string, skippable: bool, before: list<string>, after: list<string>, question?: QuestionDeclaration|null}> $steps in the order of declaration
     *
     * @throws InvalidFlow when a declaration breaks a rule, or a step belongs to no declared flow
     */
    public static function fromDeclarations(array $flows, array $steps): self
    {
        /** @var array<string, list<StepDefinition>> $stepsOf */
        $stepsOf = array_fill_keys(array_keys($flows), []);

        foreach ($steps as $step) {
            if (!isset($stepsOf[$step['flow']])) {
                throw InvalidFlow::because(\sprintf('The step "%s" belongs to the flow "%s", which is not declared.', $step['id'], $step['flow']));
            }

            $stepsOf[$step['flow']][] = new StepDefinition(
                $step['id'],
                $step['title'],
                Completion::tryFrom($step['completion']) ?? throw InvalidFlow::because(\sprintf('The step "%s" is completed "%s", not "manual", "event", "checker" or "question".', $step['id'], $step['completion'])),
                $step['description'],
                $step['route'],
                $step['route_parameters'],
                $step['target'],
                $step['event'],
                $step['checker'],
                $step['skippable'],
                $step['before'],
                $step['after'],
                self::question($step['id'], $step['question'] ?? null),
            );
        }

        $definitions = [];
        $onEvent = [];

        foreach ($flows as $id => $flow) {
            $definition = new FlowDefinition($id, $flow['title'], $stepsOf[$id], $flow['description'], $flow['translation_domain']);
            $definitions[$id] = $definition;

            foreach ($definition->steps as $step) {
                if (null !== $step->event) {
                    $onEvent[$step->event][] = ['flow' => $id, 'step' => $step->id];
                }
            }
        }

        return new self($definitions, $onEvent);
    }

    /**
     * Whether a choice of a question asks the application for a role or a subscription.
     */
    public function grantsAnything(): bool
    {
        foreach ($this->flows as $flow) {
            foreach ($flow->steps as $step) {
                if ($step->question?->grantsAnything() ?? false) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @throws UnknownFlow when no flow has the identifier
     */
    public function get(string $flow): FlowDefinition
    {
        return $this->flows[$flow] ?? throw UnknownFlow::named($flow);
    }

    public function has(string $flow): bool
    {
        return isset($this->flows[$flow]);
    }

    /**
     * @return list<FlowDefinition> the flows, in the order of declaration
     */
    public function all(): array
    {
        return array_values($this->flows);
    }

    /**
     * @param QuestionDeclaration|null $declaration
     *
     * @throws InvalidFlow when the declaration breaks a rule
     */
    private static function question(string $step, ?array $declaration): ?Question
    {
        if (null === $declaration) {
            return null;
        }

        $choices = [];

        foreach ($declaration['choices'] as $id => $choice) {
            $choices[] = new Choice((string) $id, $choice['label'], $choice['roles'] ?? [], $choice['subscriptions'] ?? []);
        }

        try {
            return new Question($choices, $declaration['multiple'] ?? false);
        } catch (InvalidFlow $invalidFlow) {
            throw InvalidFlow::because(\sprintf('The step "%s": %s', $step, $invalidFlow->getMessage()));
        }
    }

    /**
     * The steps an event completes.
     *
     * @return list<array{flow: string, step: string}>
     */
    public function onEvent(string $event): array
    {
        return $this->onEvent[$event] ?? [];
    }
}
