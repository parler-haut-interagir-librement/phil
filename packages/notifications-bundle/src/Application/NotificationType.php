<?php

declare(strict_types=1);

namespace Phil\NotificationsBundle\Application;

use Phil\NotificationsBundle\Domain\Exception\InvalidNotification;
use Phil\NotificationsBundle\Domain\Identifiers;

/**
 * A kind of notification the application declares: the channels each recipient gets it on by default, the channels it
 * is posted to once whoever the recipients are, whether similar ones collapse, whether email leaves it to the digest
 * of the recipients who chose one, whether the centre shows it as a toast, its importance for Symfony Notifier, and the
 * translation keys of its title and body in the `phil_notifications` domain.
 */
final readonly class NotificationType
{
    public const string DOMAIN = 'phil_notifications';

    public const array IMPORTANCES = ['urgent', 'high', 'medium', 'low'];

    public string $key;

    /** @var list<string> */
    public array $channels;

    /** @var list<string> */
    public array $broadcast;

    public string $title;

    public string $body;

    /**
     * @param list<string> $channels  each recipient's channels, by default
     * @param list<string> $broadcast the channels the notification is posted to once, such as "chat/discord"
     *
     * @throws InvalidNotification when the key, a channel or the importance is not valid
     */
    public function __construct(
        string $key,
        array $channels = [Channels::SITE],
        array $broadcast = [],
        public bool $collapse = false,
        public bool $digest = true,
        public bool $toast = true,
        public string $importance = 'medium',
        ?string $title = null,
        ?string $body = null,
    ) {
        $this->key = Identifiers::key($key, 'notification type');
        $this->channels = array_values(array_unique(array_map(Channels::check(...), $channels)));
        $this->broadcast = array_values(array_unique(array_map(Channels::check(...), $broadcast)));

        if (!\in_array($importance, self::IMPORTANCES, true)) {
            throw InvalidNotification::because(\sprintf('The importance of "%s" is one of "%s".', $key, implode('", "', self::IMPORTANCES)));
        }

        $this->title = $title ?? $key.'.title';
        $this->body = $body ?? $key.'.body';
    }
}
