<?php

declare(strict_types=1);

namespace Phil\AccessibilityCheck;

/**
 * What a finding says, as a key of the domain `phil_accessibility_check` and the values of its placeholders:
 * `image-alt.missing` with `{source}`. `Translation\Messages` writes it in a language with `symfony/translation`; an
 * application with a translator of its own translates the key.
 */
final readonly class Message
{
    /**
     * @param array<string, int|float|string> $parameters the values of the placeholders, by name without braces
     */
    public function __construct(
        public string $key,
        public array $parameters = [],
    ) {
    }
}
