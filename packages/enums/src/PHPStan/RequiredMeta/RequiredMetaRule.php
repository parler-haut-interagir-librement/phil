<?php

declare(strict_types=1);

namespace Phil\Enums\PHPStan\RequiredMeta;

use Phil\Enums\Attribute\RequiredMeta;
use PhpParser\Node;
use PHPStan\Analyser\Scope;
use PHPStan\BetterReflection\Reflection\Adapter\ReflectionEnum;
use PHPStan\Node\InClassNode;
use PHPStan\Rules\IdentifierRuleError;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * Reports each case of an enum that does not carry a meta property its `#[RequiredMeta]` attributes ask for: the
 * case added without a label is found by the analysis rather than by a form that shows its name.
 *
 * @implements Rule<InClassNode>
 */
final class RequiredMetaRule implements Rule
{
    public function getNodeType(): string
    {
        return InClassNode::class;
    }

    /**
     * @return list<IdentifierRuleError>
     */
    public function processNode(Node $node, Scope $scope): array
    {
        $nativeReflection = $node->getClassReflection()->getNativeReflection();

        if (!$nativeReflection instanceof ReflectionEnum) {
            return [];
        }

        $required = [];

        foreach ($nativeReflection->getAttributes(RequiredMeta::class) as $attribute) {
            $instance = $attribute->newInstance();

            if ($instance instanceof RequiredMeta) {
                $required = [...$required, ...$instance->metaProperties];
            }
        }

        $errors = [];

        foreach ($nativeReflection->getCases() as $case) {
            foreach ($required as $metaProperty) {
                if ([] === $case->getAttributes($metaProperty)) {
                    $errors[] = RuleErrorBuilder::message(\sprintf('Case %s::%s does not carry #[%s], which the enum requires.', $nativeReflection->getName(), $case->getName(), $metaProperty))
                        ->identifier('philEnums.requiredMeta')
                        ->build();
                }
            }
        }

        return $errors;
    }
}
