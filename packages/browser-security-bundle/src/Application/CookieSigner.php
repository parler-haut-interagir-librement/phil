<?php

declare(strict_types=1);

namespace Phil\BrowserSecurityBundle\Application;

/**
 * Signs the value of a cookie so that the browser cannot change it: the value, a dot, then an HMAC-SHA-256 of the name
 * and the value in base64url. The signature follows the name of the cookie, so that one cookie's value does not pass
 * for another's. Earlier secrets still verify, so that a secret can be replaced without signing everyone out.
 *
 * A signature proves who wrote the value; the value stays readable by the browser.
 */
final readonly class CookieSigner
{
    private const string CONTEXT = 'phil-browser-security-cookie';

    /** @var non-empty-list<string> the keys derived from the secrets, the one that signs first */
    private array $keys;

    /**
     * @param list<string> $previousSecrets secrets no longer used to sign and still accepted
     */
    public function __construct(string $secret, array $previousSecrets = [])
    {
        $this->keys = array_map(static fn (string $key): string => hash_hkdf('sha256', $key, 32, self::CONTEXT), [$secret, ...$previousSecrets]);
    }

    public function sign(string $name, string $value): string
    {
        return $value.'.'.$this->mac($this->keys[0], $name, $value);
    }

    /**
     * The value a signed cookie holds, or null when its signature is not one of ours.
     */
    public function verify(string $name, string $signed): ?string
    {
        $dot = strrpos($signed, '.');

        if (false === $dot) {
            return null;
        }

        $value = substr($signed, 0, $dot);
        $mac = substr($signed, $dot + 1);

        return array_any($this->keys, fn (string $key): bool => hash_equals($this->mac($key, $name, $value), $mac)) ? $value : null;
    }

    private function mac(string $key, string $name, string $value): string
    {
        return sodium_bin2base64(hash_hmac('sha256', $name."\0".$value, $key, true), \SODIUM_BASE64_VARIANT_URLSAFE_NO_PADDING);
    }
}
