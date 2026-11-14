<?php

declare(strict_types=1);

namespace Phil\NotificationsBundle\Application;

use InvalidArgumentException;

/**
 * What a `WebPushSender` sends for a notification, before it is encrypted for each subscription: the JSON the
 * application's service worker shows, and the headers of the push service (RFC 8030).
 *
 * - The payload holds the toast's fields; a body too long for one encrypted record (RFC 8291 leaves 3993 bytes of
 *   plaintext in a 4096-byte record) is shortened, on a character boundary, with an ellipsis; then the title, then
 *   the URL is left out.
 * - The urgency follows the type's importance: `urgent` and `high` are `high`, `medium` is `normal`, `low` is `low`.
 * - A type that collapses gives a topic, so that a push service still holding an older message of the same group
 *   replaces it: 32 characters of the URL-safe Base64 alphabet, from a SHA-256 hash of the group.
 * - The time to live is how long a push service keeps a message for a browser that is offline.
 */
final readonly class WebPushMessage
{
    /** The plaintext an aes128gcm record of 4096 bytes holds: 4096 less its header (86), its tag (16) and its delimiter. */
    public const int MAX_PAYLOAD = 3993;

    /** A day: what a push service keeps by default. */
    public const int TTL = 86400;

    /** The four weeks most push services keep a message at most. */
    public const int MAX_TTL = 2_419_200;

    private const string ELLIPSIS = '…';

    /**
     * @param 'high'|'normal'|'low' $urgency
     */
    private function __construct(
        public string $payload,
        public string $urgency,
        public ?string $topic,
        public int $ttl,
    ) {
    }

    /**
     * @throws InvalidArgumentException when the time to live is negative or longer than four weeks
     */
    public static function of(RenderedNotification $notification, NotificationType $type, int $ttl = self::TTL): self
    {
        if ($ttl < 0 || $ttl > self::MAX_TTL) {
            throw new InvalidArgumentException(\sprintf('The time to live of a Web Push message is between 0 and %d seconds, not %d.', self::MAX_TTL, $ttl));
        }

        return new self(
            self::payload($notification),
            match ($type->importance) {
                'urgent', 'high' => 'high',
                'low' => 'low',
                default => 'normal',
            },
            $type->collapse ? self::topic($notification->notification->groupKey()) : null,
            $ttl,
        );
    }

    private static function payload(RenderedNotification $notification): string
    {
        $fields = $notification->toArray();
        $payload = self::encode($fields);

        foreach (['body', 'title'] as $field) {
            if (\strlen($payload) <= self::MAX_PAYLOAD) {
                break;
            }

            // The longest beginning that fits, found by halving: JSON escapes make a byte count for up to six.
            $text = $fields[$field];
            $low = 0;
            $high = \strlen($text) - 1;

            while ($low < $high) {
                $middle = intdiv($low + $high + 1, 2);
                $fields[$field] = self::shortened($text, $middle);

                if (\strlen(self::encode($fields)) <= self::MAX_PAYLOAD) {
                    $low = $middle;
                } else {
                    $high = $middle - 1;
                }
            }

            $fields[$field] = self::shortened($text, $low);
            $payload = self::encode($fields);
        }

        if (\strlen($payload) > self::MAX_PAYLOAD) {
            $fields['url'] = null;
            $payload = self::encode($fields);
        }

        return $payload;
    }

    private static function shortened(string $text, int $bytes): string
    {
        $cut = mb_strcut($text, 0, $bytes, 'UTF-8');

        return '' === $cut ? '' : $cut.self::ELLIPSIS;
    }

    /**
     * @param array<string, mixed> $fields
     */
    private static function encode(array $fields): string
    {
        return json_encode($fields, \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES);
    }

    private static function topic(string $group): string
    {
        return substr(sodium_bin2base64(hash('sha256', $group, true), \SODIUM_BASE64_VARIANT_URLSAFE_NO_PADDING), 0, 32);
    }
}
