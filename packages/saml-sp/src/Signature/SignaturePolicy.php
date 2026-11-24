<?php

declare(strict_types=1);

namespace Phil\SamlSp\Signature;

use InvalidArgumentException;

/**
 * What a signature must be to be verified: SHA-1 refused unless allowed, RSA keys of 2048 bits or more.
 */
final readonly class SignaturePolicy
{
    public const int MIN_RSA_BITS = 1024;

    public function __construct(
        public bool $allowSha1 = false,
        public int $minRsaBits = 2048,
    ) {
        if ($minRsaBits < self::MIN_RSA_BITS || $minRsaBits > 16384) {
            throw new InvalidArgumentException(\sprintf('The smallest RSA key is between %d and 16384 bits; %d is not.', self::MIN_RSA_BITS, $minRsaBits));
        }
    }
}
