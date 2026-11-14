<?php

declare(strict_types=1);

namespace Phil\NotificationsBundle\Tests\Unit\Application;

use Phil\NotificationsBundle\Application\OutgoingNotification;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Notifier\Recipient\NoRecipient;

#[CoversClass(OutgoingNotification::class)]
final class OutgoingNotificationTest extends TestCase
{
    public function testAChatMessageCarriesTheTitleThenTheBody(): void
    {
        $message = new OutgoingNotification('A new release', ['chat/discord'])->content('The notes are online.')->asChatMessage(new NoRecipient(), 'discord');

        self::assertSame("A new release\n\nThe notes are online.", $message->getSubject());
        self::assertInstanceOf(OutgoingNotification::class, $message->getNotification());
    }

    public function testAChatMessageWithoutABodyCarriesTheTitleAlone(): void
    {
        $message = new OutgoingNotification('A new release', ['chat/discord'])->asChatMessage(new NoRecipient());

        self::assertSame('A new release', $message->getSubject());
    }
}
