<?php

declare(strict_types=1);

namespace Phil\Enums\PHPStan\Ordinal;

use Phil\Enums\Traits\AsOrdinalEnumTrait;
use PhpParser\Node;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Identifier;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\Php\PhpFunctionFromParserNodeReflection;
use PHPStan\Rules\IdentifierRuleError;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * Reports `ordinal()` called where a value leaves the process — in `jsonSerialize()`, `__serialize()`,
 * `normalize()` or a Doctrine type's `convertToDatabaseValue()` —, with the identifier `philEnums.storedOrdinal`.
 * An ordinal is the position of a case in the source file: reordering the cases changes every stored one. Store the
 * value of the case instead.
 *
 * It is a heuristic: it knows these methods by name, and reads no data flow beyond them.
 *
 * @implements Rule<MethodCall>
 */
final class StoredOrdinalRule implements Rule
{
    private const array OUTWARD = ['jsonserialize', '__serialize', 'normalize', 'converttodatabasevalue'];

    public function getNodeType(): string
    {
        return MethodCall::class;
    }

    /**
     * @return list<IdentifierRuleError>
     */
    public function processNode(Node $node, Scope $scope): array
    {
        $function = $scope->getFunction();

        if (!$node->name instanceof Identifier || 'ordinal' !== $node->name->toLowerString() || !$function instanceof PhpFunctionFromParserNodeReflection || !\in_array(strtolower($function->getName()), self::OUTWARD, true)) {
            return [];
        }

        foreach ($scope->getType($node->var)->getObjectClassReflections() as $class) {
            if ($class->isEnum() && $class->hasTraitUse(AsOrdinalEnumTrait::class)) {
                return [
                    RuleErrorBuilder::message(\sprintf('An ordinal of %s leaves the process in %s(); reordering the cases would change it. Store the value of the case.', $class->getName(), $function->getName()))
                        ->identifier('philEnums.storedOrdinal')
                        ->build(),
                ];
            }
        }

        return [];
    }
}
