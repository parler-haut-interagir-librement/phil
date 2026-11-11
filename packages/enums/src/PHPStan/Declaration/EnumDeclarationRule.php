<?php

declare(strict_types=1);

namespace Phil\Enums\PHPStan\Declaration;

use Phil\Enums\Attribute\Alias;
use Phil\Enums\Attribute\BitFlags;
use Phil\Enums\Attribute\DefaultCase;
use Phil\Enums\Attribute\Meta;
use Phil\Enums\Attribute\RequiredMeta;
use Phil\Enums\Attribute\Weight;
use Phil\Enums\Traits\AsMetadatableEnumTrait;
use PhpParser\Node;
use PHPStan\Analyser\Scope;
use PHPStan\BetterReflection\Reflection\Adapter\ReflectionEnum;
use PHPStan\BetterReflection\Reflection\Adapter\ReflectionEnumBackedCase;
use PHPStan\BetterReflection\Reflection\Adapter\ReflectionEnumUnitCase;
use PHPStan\Node\InClassNode;
use PHPStan\Rules\IdentifierRuleError;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * Reports what the attributes of an enum declare wrongly, before the run time does:
 *
 * - `philEnums.defaultCase`: more than one case marked `#[DefaultCase]`;
 * - `philEnums.ambiguousAlias`: an `#[Alias]` that is another case's value or name, or that two cases share —
 *   `parse()` would never reach it;
 * - `philEnums.negativeWeight`: a `#[Weight]` below 0;
 * - `philEnums.notBitFlags`: a case of a `#[BitFlags]` enum whose value is not a power of two;
 * - `philEnums.requiredMetaUndeclared`: a property `#[RequiredMeta]` asks for that the enum's `#[Meta]` does not
 *   declare, on an enum that reads its metadata through accessors.
 *
 * @implements Rule<InClassNode>
 */
final class EnumDeclarationRule implements Rule
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
        $classReflection = $node->getClassReflection();
        $enum = $classReflection->getNativeReflection();

        if (!$enum instanceof ReflectionEnum) {
            return [];
        }

        $cases = $enum->getCases();
        $messages = [
            ...$this->defaultCases($enum->getName(), $cases),
            ...$this->aliases($enum->getName(), $cases),
            ...$this->weights($enum->getName(), $cases),
            ...$this->bitFlags($enum, $cases),
        ];

        if ($classReflection->hasTraitUse(AsMetadatableEnumTrait::class)) {
            $messages = [...$messages, ...$this->undeclaredRequiredMeta($enum)];
        }

        return array_map(
            static fn (array $message): IdentifierRuleError => RuleErrorBuilder::message($message[1])->identifier('philEnums.'.$message[0])->build(),
            $messages,
        );
    }

    /**
     * @param list<ReflectionEnumBackedCase|ReflectionEnumUnitCase> $cases
     *
     * @return list<array{string, string}>
     */
    private function defaultCases(string $enum, array $cases): array
    {
        $marked = array_values(array_map(
            static fn (ReflectionEnumBackedCase|ReflectionEnumUnitCase $case): string => $case->getName(),
            array_filter($cases, static fn (ReflectionEnumBackedCase|ReflectionEnumUnitCase $case): bool => [] !== $case->getAttributes(DefaultCase::class)),
        ));

        return \count($marked) > 1
            ? [['defaultCase', \sprintf('Enum %s marks %s with #[DefaultCase]; it may mark one case only.', $enum, implode(', ', $marked))]]
            : [];
    }

    /**
     * @param list<ReflectionEnumBackedCase|ReflectionEnumUnitCase> $cases
     *
     * @return list<array{string, string}>
     */
    private function aliases(string $enum, array $cases): array
    {
        $spellings = [];

        foreach ($cases as $case) {
            $spellings[$case->getName()] = $case->getName();

            if ($case instanceof ReflectionEnumBackedCase) {
                $spellings[(string) $case->getBackingValue()] = $case->getName();
            }
        }

        $messages = [];
        $aliases = [];

        foreach ($cases as $case) {
            foreach ($case->getAttributes(Alias::class) as $attribute) {
                $alias = $attribute->getArguments()[0] ?? null;

                if (!\is_string($alias)) {
                    continue;
                }

                $owner = $spellings[$alias] ?? $aliases[$alias] ?? null;

                if (null !== $owner && $owner !== $case->getName()) {
                    $messages[] = ['ambiguousAlias', \sprintf('The alias "%s" of %s::%s is already a value, a name or an alias of %s::%s; parse() never reaches it.', $alias, $enum, $case->getName(), $enum, $owner)];
                }

                $aliases[$alias] ??= $case->getName();
            }
        }

        return $messages;
    }

    /**
     * @param list<ReflectionEnumBackedCase|ReflectionEnumUnitCase> $cases
     *
     * @return list<array{string, string}>
     */
    private function weights(string $enum, array $cases): array
    {
        $messages = [];

        foreach ($cases as $case) {
            foreach ($case->getAttributes(Weight::class) as $attribute) {
                $weight = $attribute->getArguments()[0] ?? null;

                if (\is_int($weight) && $weight < 0) {
                    $messages[] = ['negativeWeight', \sprintf('%s::%s weighs %d; a weight is 0 or more.', $enum, $case->getName(), $weight)];
                }
            }
        }

        return $messages;
    }

    /**
     * @param list<ReflectionEnumBackedCase|ReflectionEnumUnitCase> $cases
     *
     * @return list<array{string, string}>
     */
    private function bitFlags(ReflectionEnum $enum, array $cases): array
    {
        if ([] === $enum->getAttributes(BitFlags::class)) {
            return [];
        }

        $messages = [];

        foreach ($cases as $case) {
            $value = $case instanceof ReflectionEnumBackedCase ? $case->getBackingValue() : null;

            if (!\is_int($value) || $value <= 0 || 0 !== ($value & ($value - 1))) {
                $messages[] = ['notBitFlags', \sprintf('%s::%s is not a bit flag: the cases of a #[BitFlags] enum are integers that are powers of two.', $enum->getName(), $case->getName())];
            }
        }

        return $messages;
    }

    /**
     * @return list<array{string, string}>
     */
    private function undeclaredRequiredMeta(ReflectionEnum $enum): array
    {
        $declared = [];

        foreach ([$enum, ...array_values($enum->getTraits())] as $source) {
            foreach ($source->getAttributes(Meta::class) as $attribute) {
                $meta = $attribute->newInstance();
                $declared = $meta instanceof Meta ? [...$declared, ...$meta->metaProperties] : $declared;
            }
        }

        $messages = [];

        foreach ($enum->getAttributes(RequiredMeta::class) as $attribute) {
            $required = $attribute->newInstance();

            foreach ($required instanceof RequiredMeta ? $required->metaProperties : [] as $metaProperty) {
                if (!\in_array($metaProperty, $declared, true)) {
                    $messages[] = ['requiredMetaUndeclared', \sprintf('Enum %s requires #[%s] but its #[Meta] does not declare it, so it has no accessor.', $enum->getName(), $metaProperty)];
                }
            }
        }

        return $messages;
    }
}
