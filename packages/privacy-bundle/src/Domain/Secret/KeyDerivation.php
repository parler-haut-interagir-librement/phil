<?php

declare(strict_types=1);

namespace Phil\PrivacyBundle\Domain\Secret;

use Phil\PrivacyBundle\Domain\Exception\InvalidPrivacyValue;
use SensitiveParameter;

/**
 * The keys of the bundle, each derived from the application secret by HKDF-SHA-256 (RFC 5869) under its own label.
 *
 * One label per usage: the key that seals the addresses of guest requests, the one that seals export archives, the one
 * that signs the consent cookie and the ones that hash the keys of the rate limits are unrelated, so that what leaks of
 * one usage gives nothing of another. An empty secret is refused rather than derived into a key anyone can compute.
 */
final class KeyDerivation
{
    /** The length of every derived key, in bytes: the key of libsodium's secretbox, and of HMAC-SHA-256. */
    public const int LENGTH = 32;

    public const string GUEST_REQUESTS_CLIENT = 'phil_privacy.guest_requests.client';
    public const string GUEST_REQUESTS_ADDRESS = 'phil_privacy.guest_requests.address';
    public const string GUEST_REQUEST_STORE = 'phil_privacy.guest_request_store';
    public const string EXPORT_ARCHIVE = 'phil_privacy.export_archive';
    public const string CONSENT = 'phil_privacy.consent';

    private function __construct()
    {
    }

    /**
     * @param non-empty-string $usage the label of the usage, never shared by two usages
     *
     * @throws InvalidPrivacyValue when the secret is empty
     */
    public static function key(#[SensitiveParameter] string $secret, string $usage): string
    {
        if ('' === $secret) {
            throw InvalidPrivacyValue::because('The application secret is empty: set kernel.secret to derive the keys of the bundle.');
        }

        return hash_hkdf('sha256', $secret, self::LENGTH, $usage);
    }
}
