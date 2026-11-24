<?php

declare(strict_types=1);

namespace Phil\SamlSp\Signature;

use Phil\SamlSp\Exception\InvalidSignature;

/**
 * The digests of a reference. SHA-1 is read only when the policy allows it.
 */
enum DigestAlgorithm: string
{
    case Sha1 = 'http://www.w3.org/2000/09/xmldsig#sha1';
    case Sha256 = 'http://www.w3.org/2001/04/xmlenc#sha256';
    case Sha384 = 'http://www.w3.org/2001/04/xmldsig-more#sha384';
    case Sha512 = 'http://www.w3.org/2001/04/xmlenc#sha512';

    public static function fromUri(string $uri, SignaturePolicy $policy): self
    {
        $algorithm = self::tryFrom($uri);

        if (null === $algorithm || (self::Sha1 === $algorithm && !$policy->allowSha1)) {
            throw InvalidSignature::because(\sprintf('The digest algorithm "%s" is refused.', $uri));
        }

        return $algorithm;
    }

    /**
     * The name `hash()` knows.
     */
    public function hash(): string
    {
        return match ($this) {
            self::Sha1 => 'sha1',
            self::Sha256 => 'sha256',
            self::Sha384 => 'sha384',
            self::Sha512 => 'sha512',
        };
    }
}
