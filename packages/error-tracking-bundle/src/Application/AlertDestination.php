<?php

declare(strict_types=1);

namespace Phil\ErrorTrackingBundle\Application;

/**
 * Where the alerts of new groups and regressions go, as the container was built: to notified recipients or an
 * audience, to a chat, nowhere because they are turned off, or nowhere although they are on — the degraded state, told
 * by the log, the administration pages and `phil:error-tracking:status`, never by a failure.
 */
enum AlertDestination: string
{
    case Notification = 'notification';
    case Chat = 'chat';
    case Disabled = 'disabled';
    case None = 'none';

    /**
     * Whether the alerts are on but reach no one: no recipient, no audience and no chatter in the notifier.
     */
    public function isDegraded(): bool
    {
        return self::None === $this;
    }

    /**
     * One sentence for the administration pages and the status command.
     */
    public function describe(): string
    {
        return match ($this) {
            self::Notification => 'The alerts are notified to the configured recipients or audience.',
            self::Chat => "The alerts are sent as chat messages through the notifier's chatter.",
            self::Disabled => 'The alerts are turned off.',
            self::None => 'No alert destination: name recipients or an audience, or configure a chatter transport.',
        };
    }
}
