<?php

declare(strict_types=1);

namespace Phil\Enums\PHPStan;

use Override;
use PHPStan\Reflection\ClassMemberReflection;
use PHPStan\Reflection\ClassReflection;
use PHPStan\Reflection\FunctionVariant;
use PHPStan\Reflection\MethodReflection;
use PHPStan\TrinaryLogic;
use PHPStan\Type\Generic\TemplateTypeMap;
use PHPStan\Type\Type;

/**
 * A public method a trait of this library answers through `__call()` or `__callStatic()`, described to PHPStan as if
 * it were declared: no parameter, one return type, no side effect.
 *
 * When the magic method that answers it is known, its doc comment, deprecation and thrown type are reported as the
 * virtual method's own, so that what PHPStan says of a static case call is what it says of `__callStatic()`.
 */
final readonly class VirtualMethod implements MethodReflection
{
    public function __construct(
        private ClassReflection $declaringClass,
        private string $name,
        private bool $static,
        private Type $returnType,
        private ?MethodReflection $answeredBy = null,
    ) {
    }

    #[Override]
    public function getDeclaringClass(): ClassReflection
    {
        return $this->declaringClass;
    }

    #[Override]
    public function isStatic(): bool
    {
        return $this->static;
    }

    #[Override]
    public function isPrivate(): bool
    {
        return false;
    }

    #[Override]
    public function isPublic(): bool
    {
        return true;
    }

    #[Override]
    public function getDocComment(): ?string
    {
        return $this->answeredBy?->getDocComment();
    }

    #[Override]
    public function getName(): string
    {
        return $this->name;
    }

    #[Override]
    public function getPrototype(): ClassMemberReflection
    {
        return $this;
    }

    #[Override]
    public function getVariants(): array
    {
        return [new FunctionVariant(TemplateTypeMap::createEmpty(), TemplateTypeMap::createEmpty(), [], false, $this->returnType)];
    }

    #[Override]
    public function isDeprecated(): TrinaryLogic
    {
        return $this->answeredBy?->isDeprecated() ?? TrinaryLogic::createNo();
    }

    #[Override]
    public function getDeprecatedDescription(): ?string
    {
        return $this->answeredBy?->getDeprecatedDescription();
    }

    #[Override]
    public function isFinal(): TrinaryLogic
    {
        return TrinaryLogic::createNo();
    }

    #[Override]
    public function isInternal(): TrinaryLogic
    {
        return TrinaryLogic::createNo();
    }

    #[Override]
    public function getThrowType(): ?Type
    {
        return $this->answeredBy?->getThrowType();
    }

    #[Override]
    public function hasSideEffects(): TrinaryLogic
    {
        return TrinaryLogic::createNo();
    }
}
