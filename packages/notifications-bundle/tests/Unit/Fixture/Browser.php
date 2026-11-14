<?php

declare(strict_types=1);

namespace Phil\NotificationsBundle\Tests\Unit\Fixture;

use Phil\NotificationsBundle\Application\WebPush\PushSubscription;
use Phil\NotificationsBundle\Infrastructure\WebPush\VapidKeys;

/**
 * Subscriptions as a browser makes them: a real P-256 public key, so that a message can be encrypted for it, and a
 * random authentication secret.
 */
final class Browser
{
    public const string AUTH = 'AAECAwQFBgcICQoLDA0ODw';

    private static ?string $key = null;

    public static function key(): string
    {
        return self::$key ??= VapidKeys::generate()['publicKey'];
    }

    public static function subscription(string $endpoint = 'https://fcm.googleapis.com/fcm/send/abc:123', string $encoding = PushSubscription::AES128GCM): PushSubscription
    {
        return PushSubscription::of($endpoint, self::key(), self::AUTH, $encoding);
    }

    public static function json(string $endpoint = 'https://fcm.googleapis.com/fcm/send/abc:123', ?string $encoding = null): string
    {
        return json_encode(['endpoint' => $endpoint, 'expirationTime' => null, 'keys' => ['p256dh' => self::key(), 'auth' => self::AUTH]] + (null === $encoding ? [] : ['contentEncoding' => $encoding]), \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_SLASHES);
    }
}
