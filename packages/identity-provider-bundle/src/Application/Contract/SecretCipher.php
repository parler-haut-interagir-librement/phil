<?php

declare(strict_types=1);

namespace Phil\IdentityProviderBundle\Application\Contract;

use SensitiveParameter;

/**
 * Seals the secrets the bundle keeps in its tables — TOTP secrets, the private material of signing keys — and opens
 * them again. The context is bound to the sealed text, so that a value copied from one row to another does not open.
 */
interface SecretCipher
{
    public function seal(#[SensitiveParameter] string $plaintext, string $context): string;

    public function open(string $sealed, string $context): string;
}
