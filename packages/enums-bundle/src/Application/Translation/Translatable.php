<?php

declare(strict_types=1);

namespace Phil\EnumsBundle\Application\Translation;

use Attribute;

/**
 * The translation domain of an enum's cases, and the prefix of the keys generated for the cases without a label.
 *
 * ```php
 * #[Translatable(domain: 'shop', prefix: 'order')]
 * enum OrderStatus: string implements TranslatableInterface { use AsTranslatableEnumTrait; … }
 * ```
 *
 * Without it, the domain is `enums` and the prefix `enum`.
 */
#[Attribute(Attribute::TARGET_CLASS)]
final readonly class Translatable
{
    public const string DEFAULT_DOMAIN = 'enums';

    public const string DEFAULT_PREFIX = 'enum';

    public function __construct(
        public string $domain = self::DEFAULT_DOMAIN,
        public string $prefix = self::DEFAULT_PREFIX,
    ) {
    }
}
