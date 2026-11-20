<?php

declare(strict_types=1);

namespace Phil\NotificationsBundle\Application;

use Phil\NotificationsBundle\Domain\Exception\InvalidNotification;
use Symfony\Component\Notifier\Recipient\EmailRecipientInterface;
use Symfony\Component\Notifier\Recipient\RecipientInterface;
use Symfony\Component\Notifier\Recipient\SmsRecipientInterface;

/**
 * The channels a notification type names. `site` keeps it in the recipient's centre and shows it as a toast;
 * `web_push` hands it to the application's Web Push sender; every other name is a channel of Symfony Notifier —
 * `email`, `sms`, `push`, `chat/slack`, `chat/discord` —, a transport after the slash.
 */
final class Channels
{
    public const string SITE = 'site';

    public const string EMAIL = 'email';

    public const string WEB_PUSH = 'web_push';

    public const string SMS = 'sms';

    private const string PATTERN = '/^[a-z][a-z0-9_]{0,31}(\/[a-z0-9][a-z0-9_\-]{0,63})?$/D';

    /**
     * @throws InvalidNotification when the name is not one of a channel
     */
    public static function check(string $channel): string
    {
        if (1 !== preg_match(self::PATTERN, $channel)) {
            throw InvalidNotification::because(\sprintf('"%s" is not the name of a channel, such as "site", "email" or "chat/slack".', $channel));
        }

        return $channel;
    }

    /**
     * Whether quiet hours hold the channel back: every channel but the centre and email, which do not interrupt.
     */
    public static function interrupts(string $channel): bool
    {
        return self::SITE !== $channel && self::EMAIL !== $channel;
    }

    /**
     * The channel without its transport: "chat" for "chat/slack".
     */
    public static function base(string $channel): string
    {
        return explode('/', $channel, 2)[0];
    }

    /**
     * Whether the recipient directory gave what the channel needs: an `EmailRecipientInterface` with an address for
     * email, an `SmsRecipientInterface` with a phone number for SMS. Any other channel needs nothing from it.
     */
    public static function reaches(string $channel, ?RecipientInterface $contact): bool
    {
        return match (self::base($channel)) {
            self::EMAIL => $contact instanceof EmailRecipientInterface && '' !== $contact->getEmail(),
            self::SMS => $contact instanceof SmsRecipientInterface && '' !== $contact->getPhone(),
            default => true,
        };
    }
}
