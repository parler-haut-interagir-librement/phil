<?php

declare(strict_types=1);

namespace Phil\NotificationsBundle\Application;

use Phil\NotificationsBundle\Application\Exception\UnknownNotificationType;

/**
 * The notification types of the configuration, by key.
 */
final readonly class NotificationTypes
{
    /** @var array<string, NotificationType> */
    private array $types;

    public function __construct(NotificationType ...$types)
    {
        $byKey = [];

        foreach ($types as $type) {
            $byKey[$type->key] = $type;
        }

        $this->types = $byKey;
    }

    /**
     * @throws UnknownNotificationType when no type has the key
     */
    public function get(string $key): NotificationType
    {
        return $this->types[$key] ?? throw UnknownNotificationType::named($key);
    }

    public function has(string $key): bool
    {
        return isset($this->types[$key]);
    }

    /**
     * @return list<NotificationType>
     */
    public function all(): array
    {
        return array_values($this->types);
    }
}
