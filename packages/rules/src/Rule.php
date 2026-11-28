<?php

declare(strict_types=1);

namespace Phil\Rules;

use Phil\Rules\Condition\All;
use Phil\Rules\Condition\Any;
use Phil\Rules\Condition\Condition;
use Phil\Rules\Condition\Leaf;
use Phil\Rules\Condition\Not;
use Phil\Rules\Evaluation\NodeKind;
use Phil\Rules\Evaluation\Outcome;
use Phil\Rules\Evaluation\Plan;
use Phil\Rules\Evaluation\Result;
use Phil\Rules\Evaluation\Step;
use Phil\Rules\Exception\InvalidRule;
use Phil\Rules\Exception\LimitExceeded;
use Phil\Rules\Fact\FactSource;
use Phil\Rules\Operand\Operands;
use Phil\Rules\Type\ConditionType;
use Phil\Rules\Type\ConditionTypes;

/**
 * A condition checked against a registry of types and bounded by limits: every leaf names a registered type and gives
 * operands its schema accepts, the tree is no deeper and no larger than allowed, and `All` and `Any` hold at least one
 * condition. A rule that exists can be evaluated; it is immutable and holds no state between evaluations.
 */
final readonly class Rule
{
    public const int VERSION = 1;

    public const string ROOT = '/condition';

    /** The condition, with its operands as the schemas keep them. */
    public Condition $condition;

    private Plan $plan;

    /**
     * @throws InvalidRule   when a node is malformed or names an unknown type, or an operand is refused
     * @throws LimitExceeded when the tree is deeper or larger than the limits
     */
    public function __construct(
        Condition $condition,
        public ConditionTypes $types,
        public Limits $limits = new Limits(),
    ) {
        $nodes = 0;
        [$this->condition, $this->plan] = $this->compile($condition, self::ROOT, 1, $nodes);
    }

    /**
     * Whether the rule holds for these facts, with the trace that explains it.
     *
     * @throws LimitExceeded when the evaluation needs more leaf evaluations than the limits allow
     */
    public function evaluate(FactSource $facts): Result
    {
        $evaluations = 0;
        $trace = $this->run($this->plan, self::ROOT, $facts, $evaluations);

        return new Result(Outcome::Passed === $trace->outcome, $trace, $evaluations);
    }

    /**
     * The names of the condition types the rule uses, each once, in order of first use.
     *
     * @return list<string>
     */
    public function typeNames(): array
    {
        $names = [];
        $this->collect($this->plan, $names);

        return array_values($names);
    }

    /**
     * @return array{0: Condition, 1: Plan}
     */
    private function compile(Condition $condition, string $path, int $depth, int &$nodes): array
    {
        if ($depth > $this->limits->maxDepth) {
            throw LimitExceeded::depth($path, $this->limits->maxDepth);
        }

        if (++$nodes > $this->limits->maxNodes) {
            throw LimitExceeded::nodes($this->limits->maxNodes);
        }

        if ($condition instanceof All || $condition instanceof Any) {
            $key = $condition instanceof All ? 'all' : 'any';

            if ([] === $condition->conditions) {
                throw InvalidRule::malformed($path, \sprintf('"%s" holds no condition', $key));
            }

            $children = [];
            $plans = [];

            foreach ($condition->conditions as $index => $child) {
                [$children[], $plans[]] = $this->compile($child, \sprintf('%s/%s/%d', $path, $key, $index), $depth + 1, $nodes);
            }

            return $condition instanceof All
                ? [new All(...$children), Plan::composite(NodeKind::All, $plans)]
                : [new Any(...$children), Plan::composite(NodeKind::Any, $plans)];
        }

        if ($condition instanceof Not) {
            [$child, $plan] = $this->compile($condition->condition, $path.'/not', $depth + 1, $nodes);

            return [new Not($child), Plan::composite(NodeKind::Not, [$plan])];
        }

        if ($condition instanceof Leaf) {
            $type = $this->types->get($condition->type);

            if (!$type instanceof ConditionType) {
                throw InvalidRule::unknownType($path, $condition->type);
            }

            $operands = $type->operands()->read($condition->operands, $condition->type, $path);

            return [new Leaf($condition->type, $operands->toArray()), Plan::leaf($type, $operands, $condition->type)];
        }

        throw InvalidRule::unsupportedNode($path, $condition::class);
    }

    private function run(Plan $plan, string $path, FactSource $facts, int &$evaluations): Step
    {
        if ($plan->type instanceof ConditionType && $plan->operands instanceof Operands) {
            if (++$evaluations > $this->limits->maxEvaluations) {
                throw LimitExceeded::evaluations($this->limits->maxEvaluations);
            }

            $verdict = $plan->type->evaluate($plan->operands, $facts);

            return new Step($path, NodeKind::Leaf, $verdict->passed ? Outcome::Passed : Outcome::Failed, $plan->typeName, $verdict->reason, $verdict->details);
        }

        if (NodeKind::Not === $plan->kind) {
            $child = $this->run($plan->children[0], $path.'/not', $facts, $evaluations);

            return new Step($path, NodeKind::Not, Outcome::Passed === $child->outcome ? Outcome::Failed : Outcome::Passed, children: [$child]);
        }

        // An All is decided by its first failure, an Any by its first success.
        $deciding = NodeKind::All === $plan->kind ? Outcome::Failed : Outcome::Passed;
        $outcome = NodeKind::All === $plan->kind ? Outcome::Passed : Outcome::Failed;
        $children = [];

        foreach ($plan->children as $index => $child) {
            $childPath = \sprintf('%s/%s/%d', $path, $plan->kind->value, $index);

            if ($outcome === $deciding) {
                $children[] = new Step($childPath, $child->kind, Outcome::Skipped, $child->typeName);

                continue;
            }

            $step = $this->run($child, $childPath, $facts, $evaluations);
            $children[] = $step;

            if ($step->outcome === $deciding) {
                $outcome = $deciding;
            }
        }

        return new Step($path, $plan->kind, $outcome, children: $children);
    }

    /**
     * @param array<string, string> $names
     */
    private function collect(Plan $plan, array &$names): void
    {
        if (null !== $plan->typeName) {
            $names[$plan->typeName] = $plan->typeName;
        }

        foreach ($plan->children as $child) {
            $this->collect($child, $names);
        }
    }
}
