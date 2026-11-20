<?php

declare(strict_types=1);

namespace Phil\NotificationsBundle\Tests\Unit\Fixture;

use Override;
use Phil\NotificationsBundle\Application\Contract\ChannelSender;
use Phil\NotificationsBundle\Application\Delivery\ChannelOutcome;
use Phil\NotificationsBundle\Application\NotificationType;
use Phil\NotificationsBundle\Application\RenderedNotification;
use Symfony\Component\Notifier\Recipient\RecipientInterface;
use Throwable;

/**
 * A fake transport for every channel it is given: each answers what it is told — accepted, unreachable, refused —,
 * or throws, until told otherwise, and every call is kept.
 */
final class ScriptedChannels implements ChannelSender
{
    /** @var list<string> */
    public array $calls = [];

    /** @var list<array{string, RenderedNotification, ?RecipientInterface}> */
    public array $sent = [];

    /**
     * @param array<string, ChannelOutcome|Throwable> $answers by channel
     */
    public function __construct(
        public array $answers = [],
    ) {
    }

    #[Override]
    public function supports(string $channel): bool
    {
        return isset($this->answers[$channel]);
    }

    #[Override]
    public function send(string $channel, RenderedNotification $notification, NotificationType $type, ?RecipientInterface $contact): ChannelOutcome
    {
        $this->calls[] = $channel;
        $answer = $this->answers[$channel];

        if ($answer instanceof Throwable) {
            throw $answer;
        }

        if (ChannelOutcome::Accepted === $answer) {
            $this->sent[] = [$channel, $notification, $contact];
        }

        return $answer;
    }
}
