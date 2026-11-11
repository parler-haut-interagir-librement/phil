<?php

declare(strict_types=1);

namespace Phil\FeatureFlagsBundle\Domain\Flag\Rule;

use Override;
use Phil\DddBundle\Domain\Exception\InvalidValue;
use Phil\FeatureFlagsBundle\Domain\Flag\EvaluationContext;
use Phil\FeatureFlagsBundle\Domain\Flag\EvaluationReason;
use Phil\FeatureFlagsBundle\Domain\Flag\FlagName;
use Phil\FeatureFlagsBundle\Domain\Flag\TargetingAttribute;

/**
 * Matches when an attribute of the context belongs to an explicit set: these tenants, these projects.
 */
final readonly class MemberOf implements Rule
{
    /**
     * @param array<string, int> $members the members as keys, so that a lookup is a hash lookup
     */
    private function __construct(
        public TargetingAttribute $attribute,
        private array $members,
    ) {
    }

    /**
     * @param list<string> $members
     */
    public static function of(TargetingAttribute $attribute, array $members): self
    {
        if ([] === $members) {
            throw InvalidValue::because('A membership rule needs at least one member; an empty set is an inactive flag.');
        }

        if (\in_array('', $members, true)) {
            throw InvalidValue::because('A member of a membership rule cannot be empty.');
        }

        return new self($attribute, array_flip($members));
    }

    /**
     * @return list<string>
     */
    public function members(): array
    {
        return array_map(strval(...), array_keys($this->members));
    }

    #[Override]
    public function matches(FlagName $flag, EvaluationContext $context): bool
    {
        $value = $context->get($this->attribute);

        return null !== $value && isset($this->members[$value]);
    }

    #[Override]
    public function matchReason(): EvaluationReason
    {
        return EvaluationReason::TargetingMatch;
    }

    #[Override]
    public function reads(): TargetingAttribute
    {
        return $this->attribute;
    }
}
