<?php

declare(strict_types=1);

namespace Phil\Automations\Catalogue;

use Phil\Automations\Contract\Action;
use Phil\Automations\Contract\ConditionEvaluator;
use Phil\Automations\Contract\Trigger;
use Phil\Automations\Exception\InvalidDefinition;
use Phil\Automations\Exception\UnknownComponent;

/**
 * Every trigger, action and condition language the application registers, by type: what the engine runs and what a
 * no-code builder offers. It is built once and never changes, so that a worker shares it between messages.
 */
final readonly class Catalogue
{
    /** @var array<string, Trigger> */
    private array $triggers;

    /** @var array<string, Action> */
    private array $actions;

    /** @var array<string, ConditionEvaluator> */
    private array $evaluators;

    /**
     * @param iterable<Trigger>            $triggers
     * @param iterable<Action>             $actions
     * @param iterable<ConditionEvaluator> $evaluators
     *
     * @throws InvalidDefinition when two components share a type, or two evaluators a language
     */
    public function __construct(iterable $triggers = [], iterable $actions = [], iterable $evaluators = [])
    {
        $byType = [];

        foreach ($triggers as $trigger) {
            $byType[$this->unique($byType, $trigger->describe()->type, 'trigger')] = $trigger;
        }

        $this->triggers = $byType;
        $byType = [];

        foreach ($actions as $action) {
            $byType[$this->unique($byType, $action->describe()->type, 'action')] = $action;
        }

        $this->actions = $byType;
        $byLanguage = [];

        foreach ($evaluators as $evaluator) {
            $byLanguage[$this->unique($byLanguage, $evaluator->language(), 'condition language')] = $evaluator;
        }

        $this->evaluators = $byLanguage;
    }

    /**
     * @throws UnknownComponent
     */
    public function trigger(string $type): Trigger
    {
        return $this->triggers[$type] ?? throw UnknownComponent::trigger($type);
    }

    /**
     * @throws UnknownComponent
     */
    public function action(string $type): Action
    {
        return $this->actions[$type] ?? throw UnknownComponent::action($type);
    }

    /**
     * @throws UnknownComponent
     */
    public function evaluator(string $language): ConditionEvaluator
    {
        return $this->evaluators[$language] ?? throw UnknownComponent::language($language);
    }

    /**
     * The triggers that read an event of this class or of a parent of it.
     *
     * @return list<Trigger>
     */
    public function triggersFor(object $event): array
    {
        return array_values(array_filter($this->triggers, static fn (Trigger $trigger): bool => array_any($trigger->events(), static fn (string $class): bool => $event instanceof $class)));
    }

    /**
     * What the triggers say of themselves, by type, for a builder.
     *
     * @return array<string, ComponentDescription>
     */
    public function describeTriggers(): array
    {
        return array_map(static fn (Trigger $trigger): ComponentDescription => $trigger->describe(), $this->triggers);
    }

    /**
     * What the actions say of themselves, by type, for a builder.
     *
     * @return array<string, ComponentDescription>
     */
    public function describeActions(): array
    {
        return array_map(static fn (Action $action): ComponentDescription => $action->describe(), $this->actions);
    }

    /**
     * The languages conditions may be written in.
     *
     * @return list<string>
     */
    public function languages(): array
    {
        return array_keys($this->evaluators);
    }

    /**
     * @param array<string, mixed> $known
     */
    private function unique(array $known, string $key, string $what): string
    {
        if (isset($known[$key])) {
            throw InvalidDefinition::because(\sprintf('Two of the registered components are the %s "%s".', $what, $key));
        }

        return $key;
    }
}
