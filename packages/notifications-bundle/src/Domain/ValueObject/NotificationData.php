<?php

declare(strict_types=1);

namespace Phil\NotificationsBundle\Domain\ValueObject;

use Phil\NotificationsBundle\Domain\Exception\InvalidNotification;
use Phil\NotificationsBundle\Domain\Identifiers;

/**
 * The extra references of a notification, up to sixteen: each a key naming an integer or an identifier, such as
 * `{"event": 42, "ticket": "T-7"}`. No text, no address, no name: what is heavy or personal is looked up when the
 * notification is shown, from these references.
 */
final readonly class NotificationData
{
    public const int MAX_ENTRIES = 16;

    /**
     * @param array<string, int|string> $values
     */
    private function __construct(
        public array $values,
    ) {
    }

    /**
     * @param array<array-key, mixed> $values
     *
     * @throws InvalidNotification when an entry is not a key naming an integer or an identifier, or there are too many
     */
    public static function from(array $values): self
    {
        if (\count($values) > self::MAX_ENTRIES) {
            throw InvalidNotification::because(\sprintf('The data of a notification holds %d entries at most.', self::MAX_ENTRIES));
        }

        $checked = [];

        foreach ($values as $key => $value) {
            $key = Identifiers::key((string) $key, 'data key');

            if (\is_string($value)) {
                $value = Identifiers::id($value, 'data value of "'.$key.'"');
            } elseif (!\is_int($value)) {
                throw InvalidNotification::because(\sprintf('The data value of "%s" is an integer or an identifier, not %s.', $key, get_debug_type($value)));
            }

            $checked[$key] = $value;
        }

        ksort($checked);

        return new self($checked);
    }

    public static function none(): self
    {
        return new self([]);
    }
}
