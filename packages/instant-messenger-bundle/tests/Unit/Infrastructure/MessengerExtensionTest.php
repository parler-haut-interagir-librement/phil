<?php

declare(strict_types=1);

namespace Phil\InstantMessengerBundle\Tests\Unit\Infrastructure;

use Phil\InstantMessenger\Handle;
use Phil\InstantMessenger\KnownMessenger;
use Phil\InstantMessengerBundle\Infrastructure\Twig\MessengerExtension;
use Phil\InstantMessengerBundle\Tests\Unit\Fixtures\ForumMessenger;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(MessengerExtension::class)]
final class MessengerExtensionTest extends TestCase
{
    public function testAHandleIsShownLinkedAndNamed(): void
    {
        $extension = new MessengerExtension();
        $telegram = Handle::of(KnownMessenger::Telegram, 'adalove');

        self::assertSame(['@adalove', 'https://t.me/adalove'], [$extension->display($telegram), $extension->link($telegram)]);
        self::assertSame(['', null], [$extension->display(null), $extension->link(null)]);
        self::assertSame(['XMPP', 'Telegram', 'Forum', ''], [$extension->label(KnownMessenger::Xmpp), $extension->label($telegram), $extension->label(ForumMessenger::Forum), $extension->label(null)]);
    }
}
