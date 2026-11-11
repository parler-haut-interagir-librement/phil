<?php

declare(strict_types=1);

namespace Phil\Checkin;

/**
 * A credential of a catalog with one of its codes.
 */
final readonly class CatalogEntry
{
    public function __construct(
        public CredentialCode $code,
        public Credential $credential,
    ) {
    }
}
