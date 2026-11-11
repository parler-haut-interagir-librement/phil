<?php

declare(strict_types=1);

namespace Phil\Enums\PHPStan\InvocableEnum;

use Override;
use Phil\Enums\PHPStan\VirtualMethod;
use Phil\Enums\Traits\AsInvocableEnumTrait;
use PHPStan\Analyser\OutOfClassScope;
use PHPStan\BetterReflection\Reflection\Adapter\ReflectionEnum;
use PHPStan\Reflection\ClassReflection;
use PHPStan\Reflection\MethodReflection;
use PHPStan\Reflection\MethodsClassReflectionExtension;
use PHPStan\Type\StringType;

/**
 * Tells PHPStan that an enum using `AsInvocableEnumTrait` answers a static call named after each of its cases
 * (`Status::PAID()`), with the enum's backing type — or `string`, the name, for a pure enum. A name no case has
 * stays an unknown method.
 */
final class ReflectionExtension implements MethodsClassReflectionExtension
{
    #[Override]
    public function hasMethod(ClassReflection $classReflection, string $methodName): bool
    {
        $enum = $classReflection->getNativeReflection();

        return $enum instanceof ReflectionEnum
            && $classReflection->hasTraitUse(AsInvocableEnumTrait::class)
            && $enum->hasCase($methodName);
    }

    #[Override]
    public function getMethod(ClassReflection $classReflection, string $methodName): MethodReflection
    {
        return new VirtualMethod(
            $classReflection,
            $methodName,
            true,
            $classReflection->getBackedEnumType() ?? new StringType(),
            $classReflection->getMethod('__callStatic', new OutOfClassScope()),
        );
    }
}
