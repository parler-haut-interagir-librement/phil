<?php

declare(strict_types=1);

namespace Phil\AudienceSegments\Condition;

use Phil\AudienceSegments\Contract\PeopleTags;
use Phil\Rules\Evaluation\Verdict;
use Phil\Rules\Fact\FactSource;
use Phil\Rules\Operand\Operand;
use Phil\Rules\Operand\Operands;
use Phil\Rules\Operand\OperandSchema;
use Phil\Rules\Type\ConditionType;

/**
 * `person.tagged`: the member carries at least one of the internal `tags`.
 */
final readonly class PersonTagged implements ConditionType
{
    public function __construct(private PeopleTags $tags)
    {
    }

    public function name(): string
    {
        return 'person.tagged';
    }

    public function operands(): OperandSchema
    {
        return new OperandSchema(Operand::identifierList('tags', maxItems: 50));
    }

    public function evaluate(Operands $operands, FactSource $facts): Verdict
    {
        $member = Member::of($facts);

        if ($member instanceof Verdict) {
            return $member;
        }

        $tags = $operands->strings('tags');

        return Member::verdict($this->tags->hasAnyTag($member, $tags), 'person.tagged', 'person.untagged', ['tags' => \count($tags)]);
    }
}
