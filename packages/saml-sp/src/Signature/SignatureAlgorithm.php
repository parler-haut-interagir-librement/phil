<?php

declare(strict_types=1);

namespace Phil\SamlSp\Signature;

use Phil\SamlSp\Exception\InvalidSignature;

/**
 * The RSA signatures of XML Signature and of the query of the redirect binding. SHA-1 is read only when the policy
 * allows it, and never written.
 */
enum SignatureAlgorithm: string
{
    case RsaSha1 = 'http://www.w3.org/2000/09/xmldsig#rsa-sha1';
    case RsaSha256 = 'http://www.w3.org/2001/04/xmldsig-more#rsa-sha256';
    case RsaSha384 = 'http://www.w3.org/2001/04/xmldsig-more#rsa-sha384';
    case RsaSha512 = 'http://www.w3.org/2001/04/xmldsig-more#rsa-sha512';

    public static function fromUri(string $uri, SignaturePolicy $policy): self
    {
        $algorithm = self::tryFrom($uri);

        if (null === $algorithm || (self::RsaSha1 === $algorithm && !$policy->allowSha1)) {
            throw InvalidSignature::because(\sprintf('The signature algorithm "%s" is refused.', $uri));
        }

        return $algorithm;
    }

    /**
     * The algorithm `openssl_sign()` and `openssl_verify()` know.
     */
    public function openssl(): int
    {
        return match ($this) {
            self::RsaSha1 => \OPENSSL_ALGO_SHA1,
            self::RsaSha256 => \OPENSSL_ALGO_SHA256,
            self::RsaSha384 => \OPENSSL_ALGO_SHA384,
            self::RsaSha512 => \OPENSSL_ALGO_SHA512,
        };
    }

    /**
     * The digest written with this signature by the signer of the library.
     */
    public function digest(): DigestAlgorithm
    {
        return match ($this) {
            self::RsaSha1 => DigestAlgorithm::Sha1,
            self::RsaSha256 => DigestAlgorithm::Sha256,
            self::RsaSha384 => DigestAlgorithm::Sha384,
            self::RsaSha512 => DigestAlgorithm::Sha512,
        };
    }
}
