<?php

declare(strict_types=1);

namespace Phil\SeoCheck;

/**
 * What a finding says, as a key of the domain `phil_seo_check` and the values of its placeholders: `canonical.mismatch`
 * with `{found}` and `{expected}`. `Translation\Messages` writes it in a language with `symfony/translation`; an
 * application with a translator of its own translates the key.
 */
final readonly class Message
{
    /**
     * @param array<string, int|string> $parameters the values of the placeholders, by name without braces
     */
    public function __construct(
        public string $key,
        public array $parameters = [],
    ) {
    }
}
