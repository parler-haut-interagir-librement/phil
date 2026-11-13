<?php

declare(strict_types=1);

namespace Phil\PagedMediaBundle\Application;

/**
 * What every signature of the application says unless a call says otherwise: why, where, how to reach the signer,
 * who signs, and whether the signature is PAdES.
 */
final readonly class SignatureDefaults
{
    public function __construct(
        public ?string $reason = null,
        public ?string $location = null,
        public ?string $contactInfo = null,
        public ?string $name = null,
        public bool $pades = false,
    ) {
    }
}
