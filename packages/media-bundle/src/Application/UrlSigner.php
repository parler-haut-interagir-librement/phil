<?php

declare(strict_types=1);

namespace Phil\MediaBundle\Application;

use SensitiveParameter;

/**
 * Signs the path of a medium's URL, and the moment it expires when it does, so that nobody reaches a file or a format
 * the application did not hand out.
 */
final readonly class UrlSigner
{
    /** The usage label the key is derived under, by HKDF-SHA256, from the secret given. */
    public const string USAGE = 'phil_media.url_signature';

    /** The key of this usage alone: no other usage of the same secret gives the same hash. */
    private string $key;

    public function __construct(
        #[SensitiveParameter]
        string $secret,
    ) {
        $this->key = hash_hkdf('sha256', $secret, 32, self::USAGE);
    }

    public function signature(string $path, ?int $expires): string
    {
        return sodium_bin2base64(hash_hmac('sha256', $path.'|'.$expires, $this->key, true), \SODIUM_BASE64_VARIANT_URLSAFE_NO_PADDING);
    }

    public function verify(string $path, ?int $expires, string $signature, int $now): bool
    {
        return (null === $expires || $expires >= $now) && hash_equals($this->signature($path, $expires), $signature);
    }
}
