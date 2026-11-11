<?php

declare(strict_types=1);

namespace Phil\EnumsBundle\Infrastructure\Http;

use LogicException;
use Override;
use Phil\Enums\Meta\Reflection;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Controller\ValueResolverInterface;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use UnitEnum;

/**
 * The resolver of `#[ParseEnum]`: the case a route attribute stands for, by value, then name, then alias. It reads
 * any enum, whether or not it uses `AsParsableEnumTrait`, through `Reflection::parse()`.
 */
final readonly class ParsedEnumValueResolver implements ValueResolverInterface
{
    /**
     * @return list<UnitEnum|null>
     */
    #[Override]
    public function resolve(Request $request, ArgumentMetadata $argument): iterable
    {
        $attribute = $argument->getAttributesOfType(ParseEnum::class, ArgumentMetadata::IS_INSTANCEOF)[0] ?? null;

        if (!$attribute instanceof ParseEnum) {
            return [];
        }

        $enum = $argument->getType();

        if (null === $enum || !enum_exists($enum)) {
            throw new LogicException(\sprintf('#[ParseEnum] reads enums; the argument "$%s" is typed %s.', $argument->getName(), $enum ?? 'nothing'));
        }

        $input = $request->attributes->get($attribute->attribute ?? $argument->getName());

        if (null === $input && $argument->isNullable()) {
            return [null];
        }

        if (!\is_int($input) && !\is_string($input)) {
            throw new NotFoundHttpException(\sprintf('No value for the argument "$%s".', $argument->getName()));
        }

        return [Reflection::parse($enum, $input, $attribute->ignoreCase) ?? throw new NotFoundHttpException(\sprintf('"%s" is no case of %s.', $input, $enum))];
    }
}
