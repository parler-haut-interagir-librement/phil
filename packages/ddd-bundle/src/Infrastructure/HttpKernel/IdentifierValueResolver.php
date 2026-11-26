<?php

declare(strict_types=1);

namespace Phil\DddBundle\Infrastructure\HttpKernel;

use Override;
use Phil\DddBundle\Domain\Exception\InvalidValue;
use Phil\DddBundle\Domain\Identifier\Identifier;
use ReflectionClass;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\ValueResolver;
use Symfony\Component\HttpKernel\Controller\ValueResolverInterface;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Turns the request attribute named like a controller argument — a route placeholder, most of the time — into the
 * identifier class that argument declares.
 *
 * ```php
 * #[Route('/orders/{order}')]
 * public function show(OrderId $order): Response
 * ```
 *
 * **A value that is not an identifier of that class is a 404, not a 500.** A URL is typed by whoever sends it: a
 * string that is not a UUIDv7 names no resource, which is what a 404 says. The response carries no detail of the
 * refusal.
 *
 * The resolver abstains — and lets the next one try — when the argument is not typed with a concrete subclass of
 * `Identifier`, when it is variadic, or when the request has no attribute of that name: a nullable argument or one
 * with a default value then gets `null` or its default from Symfony's own resolvers. An attribute that already holds
 * an identifier of the declared class, put there by a listener, is passed through as it is.
 *
 * It is registered under its class name, which is what `#[ValueResolver]` names: an argument opts out with
 * `#[ValueResolver(IdentifierValueResolver::class, disabled: true)]`. The resolver reads that attribute itself as
 * well: with the kernel in debug mode, Symfony wraps each resolver in a `TraceableValueResolver` and no longer
 * recognises it by its class name.
 *
 * Stateless: one instance serves every request of a worker.
 */
final readonly class IdentifierValueResolver implements ValueResolverInterface
{
    /**
     * @return list<Identifier>
     */
    #[Override]
    public function resolve(Request $request, ArgumentMetadata $argument): array
    {
        $class = $this->identifierClass($argument);

        if (null === $class || $argument->isVariadic() || $this->isDisabled($argument) || !$request->attributes->has($argument->getName())) {
            return [];
        }

        $value = $request->attributes->get($argument->getName());

        if ($value instanceof $class) {
            return [$value];
        }

        // `null` for a nullable argument is Symfony's own resolvers' answer to give.
        if (null === $value) {
            return [];
        }

        if (!\is_string($value)) {
            throw $this->notFound($argument);
        }

        try {
            return [$class::fromString($value)];
        } catch (InvalidValue $invalidValue) {
            throw $this->notFound($argument, $invalidValue);
        }
    }

    /**
     * The concrete identifier class the argument declares, or null when it declares anything else — a union, a
     * scalar, `Identifier` itself, an abstract subclass.
     *
     * @return class-string<Identifier>|null
     */
    private function identifierClass(ArgumentMetadata $argument): ?string
    {
        $type = $argument->getType();

        if (null === $type || !is_subclass_of($type, Identifier::class)) {
            return null;
        }

        return new ReflectionClass($type)->isAbstract() ? null : $type;
    }

    private function isDisabled(ArgumentMetadata $argument): bool
    {
        return array_any($argument->getAttributesOfType(ValueResolver::class), static fn (ValueResolver $attribute): bool => $attribute->disabled && self::class === $attribute->resolver);
    }

    private function notFound(ArgumentMetadata $argument, ?InvalidValue $previous = null): NotFoundHttpException
    {
        return new NotFoundHttpException(\sprintf('The value of the "%s" argument is not an identifier of its class.', $argument->getName()), $previous);
    }
}
