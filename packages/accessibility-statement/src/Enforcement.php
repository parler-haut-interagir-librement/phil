<?php

declare(strict_types=1);

namespace Phil\AccessibilityStatement;

/**
 * What a reader can do when the answer to their feedback does not satisfy them: the procedure, the authority that
 * hears a complaint, and its page.
 */
final readonly class Enforcement
{
    public function __construct(
        public LocalizedText $procedure,
        public ?LocalizedText $authority = null,
        public ?string $url = null,
    ) {
        if (null !== $url) {
            Address::check($url, 'enforcement page', path: false);
        }
    }
}
