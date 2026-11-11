<?php

declare(strict_types=1);

namespace Phil\Enums\PHPStan\MetadatableEnum;

use Override;
use Phil\Enums\Attribute\Meta;
use Phil\Enums\Meta\AbstractMetaProperty;
use Phil\Enums\PHPStan\VirtualMethod;
use Phil\Enums\Traits\AsMetadatableEnumTrait;
use PHPStan\BetterReflection\Reflection\Adapter\ReflectionEnum;
use PHPStan\Reflection\ClassReflection;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Reflection\MethodsClassReflectionExtension;
use PHPStan\Reflection\ReflectionProvider;
use PHPStan\Type\MixedType;
use PHPStan\Type\Type;
use PHPStan\Type\TypeCombinator;
use ReflectionClassConstant;

/**
 * Tells PHPStan which accessors an enum using `AsMetadatableEnumTrait` answers — one per meta property its
 * `#[Meta]`, or that of a trait it uses, enables — and what each returns: the property's `TValue`, with the type of
 * its `defaultValue()` when some case does not carry it. Any other name stays an unknown method, as it fails at run
 * time.
 */
final readonly class ReflectionExtension implements MethodsClassReflectionExtension
{
    public function __construct(
        private ReflectionProvider $reflectionProvider,
    ) {
    }

    #[Override]
    public function hasMethod(ClassReflection $classReflection, string $methodName): bool
    {
        return null !== $this->metaPropertyFor($classReflection, $methodName);
    }

    #[Override]
    public function getMethod(ClassReflection $classReflection, string $methodName): MethodReflection
    {
        $metaProperty = $this->metaPropertyFor($classReflection, $methodName);

        return new VirtualMethod(
            $classReflection,
            $methodName,
            false,
            null === $metaProperty ? new MixedType() : $this->valueType($classReflection, $metaProperty),
        );
    }

    /**
     * The meta property class whose accessor is named $methodName, if any.
     *
     * @return class-string<AbstractMetaProperty<*>>|null
     */
    private function metaPropertyFor(ClassReflection $classReflection, string $methodName): ?string
    {
        $nativeReflection = $classReflection->getNativeReflection();

        if (!$nativeReflection instanceof ReflectionEnum) {
            return null;
        }

        if (!$classReflection->hasTraitUse(AsMetadatableEnumTrait::class)) {
            return null;
        }

        // Where the runtime reads them: the `Meta` attribute of the enum, then that of each trait it uses, so that
        // an accessor PHPStan accepts is one that exists.
        foreach ([$nativeReflection, ...array_values($nativeReflection->getTraits())] as $source) {
            foreach ($source->getAttributes(Meta::class) as $attribute) {
                $meta = $attribute->newInstance();

                if (!$meta instanceof Meta) {
                    continue;
                }

                foreach ($meta->metaProperties as $metaPropertyClass) {
                    if ($metaPropertyClass::method() === $methodName) {
                        return $metaPropertyClass;
                    }
                }
            }
        }

        return null;
    }

    /**
     * The type the accessor returns: the meta property's `TValue`, joined with the return type of its
     * `defaultValue()` only when a case lacks the attribute and so falls back on it.
     *
     * @param class-string<AbstractMetaProperty<*>> $metaPropertyClass
     */
    private function valueType(ClassReflection $classReflection, string $metaPropertyClass): Type
    {
        if (!$this->reflectionProvider->hasClass($metaPropertyClass)) {
            return new MixedType();
        }

        $metaClass = $this->reflectionProvider->getClass($metaPropertyClass);
        $ancestor = $metaClass->getAncestorWithClassName(AbstractMetaProperty::class);

        $valueType = $ancestor?->getActiveTemplateTypeMap()->getType('TValue') ?? new MixedType();

        if ($this->everyCaseCarries($classReflection, $metaPropertyClass)) {
            return $valueType;
        }

        if (!$metaClass->hasNativeMethod('defaultValue')) {
            return $valueType;
        }

        $variants = $metaClass->getNativeMethod('defaultValue')->getVariants();

        if (1 !== \count($variants)) {
            return $valueType;
        }

        return TypeCombinator::union($valueType, $variants[0]->getReturnType());
    }

    /**
     * Whether no case of the enum falls back on the default value of the meta property.
     *
     * @param class-string<AbstractMetaProperty<*>> $metaPropertyClass
     */
    private function everyCaseCarries(ClassReflection $classReflection, string $metaPropertyClass): bool
    {
        $nativeReflection = $classReflection->getNativeReflection();

        if (!$nativeReflection instanceof ReflectionEnum) {
            return false;
        }

        $cases = $nativeReflection->getCases();

        if ([] === $cases) {
            return false;
        }

        return array_all($cases, static fn (ReflectionClassConstant $case): bool => [] !== $case->getAttributes($metaPropertyClass));
    }
}
