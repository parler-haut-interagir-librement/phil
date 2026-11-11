<?php

declare(strict_types=1);

namespace Phil\FeatureFlagsBundle\Domain\Flag;

use Phil\FeatureFlagsBundle\Domain\Flag\Rule\Rule;

/**
 * A flag as a provider knows it: active or not, and the rules that target it.
 *
 * **Inactive wins.** Deactivating a flag turns its feature off for everyone without touching its rules, which
 * is what makes deactivation a rollback: reactivating it restores the exact rollout it had.
 *
 * Active with no rule is on for every context; active with rules is on when any rule matches. **An experiment**
 * then splits the contexts the flag is on for between its variants; a context it cannot place is off, with the
 * reason `DEFAULT`, and a placed one is on with its variant and the reason `SPLIT`.
 *
 * The metadata (description, owner, kind, expiry) changes nothing in the evaluation.
 */
final readonly class FlagDefinition
{
    public FlagMetadata $metadata;

    /**
     * @param list<Rule> $rules
     */
    public function __construct(
        public FlagName $name,
        public bool $active,
        public array $rules = [],
        public ?Experiment $experiment = null,
        ?FlagMetadata $metadata = null,
    ) {
        $this->metadata = $metadata ?? FlagMetadata::none();
    }

    /**
     * The same flag with the same rules, experiment and metadata, in another active state: what a store of active
     * states applies to the definition a provider holds.
     */
    public function withActive(bool $active): self
    {
        return new self($this->name, $active, $this->rules, $this->experiment, $this->metadata);
    }

    /**
     * Whether a rule or the experiment of the flag reads one of these attributes of the context: a flag that
     * reads none of them evaluates the same whatever they are.
     */
    public function readsAnyOf(TargetingAttribute ...$attributes): bool
    {
        foreach ($this->rules as $rule) {
            if (\in_array($rule->reads(), $attributes, true)) {
                return true;
            }
        }

        return $this->experiment instanceof Experiment && \in_array($this->experiment->attribute, $attributes, true);
    }

    public function evaluate(EvaluationContext $context): Evaluation
    {
        if (!$this->active) {
            return new Evaluation($this->name, false, EvaluationReason::Disabled);
        }

        $reason = $this->matchReason($context);

        if (!$reason instanceof EvaluationReason) {
            return new Evaluation($this->name, false, EvaluationReason::Default);
        }

        if (!$this->experiment instanceof Experiment) {
            return new Evaluation($this->name, true, $reason);
        }

        $variant = $this->experiment->assign($this->name, $context);

        return $variant instanceof VariantName
            ? new Evaluation($this->name, true, EvaluationReason::Split, variant: $variant)
            : new Evaluation($this->name, false, EvaluationReason::Default);
    }

    /**
     * The reason the flag is on for the context, before any experiment, or `null` when no rule matches.
     */
    private function matchReason(EvaluationContext $context): ?EvaluationReason
    {
        if ([] === $this->rules) {
            return EvaluationReason::Static;
        }

        foreach ($this->rules as $rule) {
            if ($rule->matches($this->name, $context)) {
                return $rule->matchReason();
            }
        }

        return null;
    }
}
