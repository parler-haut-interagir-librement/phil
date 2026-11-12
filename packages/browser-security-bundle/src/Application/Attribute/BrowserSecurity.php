<?php

declare(strict_types=1);

namespace Phil\BrowserSecurityBundle\Application\Attribute;

use Attribute;
use Phil\BrowserSecurityBundle\Application\ProtectionOverride;

/**
 * Changes the protections of the responses of a controller, by the name of the header or of the protection: on its
 * class, then on its method, in that order.
 *
 * ```php
 * #[BrowserSecurity(replace: ['X-Frame-Options' => 'DENY'], remove: ['Cross-Origin-Embedder-Policy'])]
 * #[BrowserSecurity(add: ['Permissions-Policy' => ['camera' => ['self']], 'external-redirects' => ['pay.example.com']])]
 * ```
 */
#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
final readonly class BrowserSecurity
{
    /**
     * @param array<string, mixed> $add     what is added, by name
     * @param array<string, mixed> $replace what is written instead of the configured value, by name
     * @param list<string>         $remove  the names of the protections this response goes without
     */
    public function __construct(
        public array $add = [],
        public array $replace = [],
        public array $remove = [],
    ) {
    }

    public function toOverride(): ProtectionOverride
    {
        return new ProtectionOverride($this->add, $this->replace, $this->remove);
    }
}
