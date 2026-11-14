<?php

declare(strict_types=1);

namespace Phil\NotificationsBundle\Application\WebPush;

use Phil\NotificationsBundle\Application\Exception\InvalidPushSubscription;
use Phil\Url\Exception\InvalidUrl;
use Phil\Url\Url;
use SodiumException;

/**
 * A browser's push subscription (W3C Push API): the endpoint of its push service, and the keys a message is encrypted
 * with (RFC 8291) — the browser's P-256 public key, 65 bytes uncompressed, and its 16-byte authentication secret,
 * both in URL-safe Base64 as `PushSubscription.toJSON()` writes them.
 *
 * The endpoint is an HTTPS address of a public host, read by phil/url: an IP address, `localhost`, credentials or a
 * port 0 are refused, so that a member cannot have the site post to its own network. It is kept in its canonical
 * form, the one validated and the one posted to.
 */
final readonly class PushSubscription
{
    public const string AES128GCM = 'aes128gcm';

    public const string AESGCM = 'aesgcm';

    public const array ENCODINGS = [self::AES128GCM, self::AESGCM];

    /** The length of a browser's public key: an uncompressed P-256 point, `0x04`, then its two coordinates. */
    public const int PUBLIC_KEY_BYTES = 65;

    public const int AUTH_BYTES = 16;

    /** What a browser's subscription takes once written as JSON, an endpoint of 2048 characters at most included. */
    public const int MAX_JSON = 4096;

    private function __construct(
        public string $endpoint,
        public string $host,
        public string $publicKey,
        public string $authToken,
        public string $contentEncoding,
    ) {
    }

    /**
     * @throws InvalidPushSubscription when the endpoint, a key or the encoding is not one a push service takes
     */
    public static function of(string $endpoint, string $publicKey, string $authToken, string $contentEncoding = self::AES128GCM): self
    {
        try {
            $url = Url::fromString($endpoint);
        } catch (InvalidUrl $invalidUrl) {
            throw InvalidPushSubscription::because('The endpoint of a push subscription is a web address: '.$invalidUrl->getMessage());
        }

        if ('https' !== $url->scheme) {
            throw InvalidPushSubscription::because('The endpoint of a push subscription is an HTTPS address.');
        }

        if (!\in_array($contentEncoding, self::ENCODINGS, true)) {
            throw InvalidPushSubscription::because(\sprintf('The content encoding of a push subscription is "aes128gcm" or "aesgcm", not "%s".', mb_strcut($contentEncoding, 0, 40)));
        }

        $key = self::decoded($publicKey, 'public key');

        if (self::PUBLIC_KEY_BYTES !== \strlen($key) || "\x04" !== $key[0]) {
            throw InvalidPushSubscription::because(\sprintf('The public key of a push subscription is an uncompressed P-256 point of %d bytes.', self::PUBLIC_KEY_BYTES));
        }

        if (self::AUTH_BYTES !== \strlen(self::decoded($authToken, 'authentication secret'))) {
            throw InvalidPushSubscription::because(\sprintf('The authentication secret of a push subscription is %d bytes.', self::AUTH_BYTES));
        }

        return new self($url->toString(), $url->host, rtrim($publicKey, '='), rtrim($authToken, '='), $contentEncoding);
    }

    /**
     * Reads what the browser sent: `{"endpoint": …, "keys": {"p256dh": …, "auth": …}}`, with the `contentEncoding`
     * the page chose from `PushManager.supportedContentEncodings`, `aes128gcm` when it says none.
     *
     * @throws InvalidPushSubscription when the JSON is not a subscription
     */
    public static function fromJson(string $json): self
    {
        if (\strlen($json) > self::MAX_JSON) {
            throw InvalidPushSubscription::because(\sprintf('A push subscription is %d bytes of JSON at most.', self::MAX_JSON));
        }

        $data = json_decode($json, true, 4);
        $keys = \is_array($data) ? ($data['keys'] ?? null) : null;

        if (!\is_array($data) || !\is_string($data['endpoint'] ?? null) || !\is_array($keys) || !\is_string($keys['p256dh'] ?? null) || !\is_string($keys['auth'] ?? null)) {
            throw InvalidPushSubscription::because('A push subscription is a JSON object with an "endpoint" and "keys" holding "p256dh" and "auth".');
        }

        $encoding = $data['contentEncoding'] ?? self::AES128GCM;

        return self::of($data['endpoint'], $keys['p256dh'], $keys['auth'], \is_string($encoding) ? $encoding : '');
    }

    /**
     * Whether the endpoint's host is one of these push services, or under one; any public host when none is named.
     *
     * @param list<string> $services
     */
    public function isOn(array $services): bool
    {
        if ([] === $services) {
            return true;
        }

        foreach ($services as $service) {
            $service = strtolower($service);

            if ($this->host === $service || str_ends_with($this->host, '.'.$service)) {
                return true;
            }
        }

        return false;
    }

    /**
     * The key a store keeps the endpoint under: its SHA-256 hash, in hexadecimal, 64 characters.
     */
    public function id(): string
    {
        return self::idOf($this->endpoint);
    }

    /**
     * The canonical form of an endpoint, the one a store keeps; `null` for what is no web address.
     */
    public static function canonical(string $endpoint): ?string
    {
        try {
            return Url::fromString($endpoint)->toString();
        } catch (InvalidUrl) {
            return null;
        }
    }

    public static function idOf(string $endpoint): string
    {
        return hash('sha256', $endpoint);
    }

    private static function decoded(string $value, string $what): string
    {
        try {
            return sodium_base642bin(rtrim($value, '='), \SODIUM_BASE64_VARIANT_URLSAFE_NO_PADDING);
        } catch (SodiumException) {
            throw InvalidPushSubscription::because(\sprintf('The %s of a push subscription is written in URL-safe Base64.', $what));
        }
    }
}
