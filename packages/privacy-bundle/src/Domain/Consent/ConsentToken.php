<?php

declare(strict_types=1);

namespace Phil\PrivacyBundle\Domain\Consent;

use JsonException;
use Phil\PrivacyBundle\Domain\Exception\InvalidPrivacyValue;
use Phil\PrivacyBundle\Domain\Secret\KeyDerivation;
use SensitiveParameter;
use SodiumException;

/**
 * The value of the consent cookie: the choice in JSON, base64url, then its HMAC-SHA-256.
 *
 * **Signed so that it can be trusted as a record**, and because a value written by a script or another subdomain must
 * not be able to grant a category. A value that is too long, malformed or signed by another key reads as no choice at
 * all, which refuses every optional category; nothing that a browser sends can raise an error.
 */
final readonly class ConsentToken
{
    public const int MAX_LENGTH = 2048;

    private string $key;

    public function __construct(#[SensitiveParameter] string $secret)
    {
        $this->key = KeyDerivation::key($secret, KeyDerivation::CONSENT);
    }

    public function encode(ConsentChoice $choice): string
    {
        $payload = $this->base64(json_encode([
            'i' => $choice->id,
            'v' => $choice->version,
            'g' => $choice->granted,
            't' => $choice->decidedAtTimestamp(),
            'p' => $choice->globalPrivacyControl,
        ], \JSON_THROW_ON_ERROR));

        return $payload.'.'.$this->signature($payload);
    }

    public function decode(string $value): ?ConsentChoice
    {
        if (\strlen($value) > self::MAX_LENGTH || 1 !== substr_count($value, '.')) {
            return null;
        }

        [$payload, $signature] = explode('.', $value);

        if (!hash_equals($this->signature($payload), $signature)) {
            return null;
        }

        return $this->read($payload);
    }

    private function read(string $payload): ?ConsentChoice
    {
        try {
            $data = json_decode(sodium_base642bin($payload, \SODIUM_BASE64_VARIANT_URLSAFE_NO_PADDING), true, 3, \JSON_THROW_ON_ERROR);
        } catch (JsonException|SodiumException) {
            return null;
        }

        if (!\is_array($data) || !\is_string($data['i'] ?? null) || !\is_string($data['v'] ?? null) || !\is_int($data['t'] ?? null) || !\is_bool($data['p'] ?? null)) {
            return null;
        }

        $granted = $data['g'] ?? null;

        if (!\is_array($granted) || !array_is_list($granted) || $granted !== array_filter($granted, is_string(...))) {
            return null;
        }

        try {
            return new ConsentChoice($data['i'], $data['v'], $granted, ConsentChoice::dateOf($data['t']), $data['p']);
        } catch (InvalidPrivacyValue) {
            return null;
        }
    }

    private function signature(string $payload): string
    {
        return $this->base64(hash_hmac('sha256', $payload, $this->key, true));
    }

    private function base64(string $bytes): string
    {
        return sodium_bin2base64($bytes, \SODIUM_BASE64_VARIANT_URLSAFE_NO_PADDING);
    }
}
