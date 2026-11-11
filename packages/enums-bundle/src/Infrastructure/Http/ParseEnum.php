<?php

declare(strict_types=1);

namespace Phil\EnumsBundle\Infrastructure\Http;

use Attribute;
use Symfony\Component\HttpKernel\Attribute\ValueResolver;

/**
 * Reads a controller argument typed by an enum from the route attribute of the same name — or of the name given —,
 * leniently: by value, by name or by `#[Alias]`, with or without regard to letter case. A value that is no case
 * gives a 404, as a missing resource would.
 *
 * ```php
 * #[Route('/orders/{status}')]
 * public function list(#[ParseEnum] OrderStatus $status): Response
 * ```
 */
#[Attribute(Attribute::TARGET_PARAMETER)]
final class ParseEnum extends ValueResolver
{
    public function __construct(
        public readonly ?string $attribute = null,
        public readonly bool $ignoreCase = false,
    ) {
        parent::__construct(ParsedEnumValueResolver::class);
    }
}
