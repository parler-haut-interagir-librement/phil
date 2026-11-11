<?php

declare(strict_types=1);

namespace Phil\InstantMessenger\Tests\Unit;

use Phil\Enums\Collection\EnumSet;
use Phil\InstantMessenger\Exception\InstantMessengerException;
use Phil\InstantMessenger\Exception\InvalidHandle;
use Phil\InstantMessenger\Handle;
use Phil\InstantMessenger\KnownMessenger;
use Phil\InstantMessenger\Messengers;
use Phil\InstantMessenger\Tests\Unit\Fixtures\ForumMessenger;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Handle::class)]
#[CoversClass(Messengers::class)]
#[CoversClass(InvalidHandle::class)]
final class HandleTest extends TestCase
{
    public function testAHandleIsKeptCanonical(): void
    {
        $handle = Handle::of(KnownMessenger::Telegram, 'https://t.me/Ada_Love');

        self::assertSame([KnownMessenger::Telegram, 'ada_love', '@ada_love', 'https://t.me/ada_love', 'ada_love'], [$handle->type, $handle->value, $handle->display(), $handle->link(), (string) $handle]);
        self::assertTrue($handle->equals(Handle::of(KnownMessenger::Telegram, '@ada_love')));
        self::assertFalse($handle->equals(Handle::of(KnownMessenger::Telegram, '@ada_lovelace')));
        self::assertFalse(Handle::of(KnownMessenger::Line, 'ada_love')->equals(Handle::of(KnownMessenger::Discord, 'ada_love')));
    }

    /**
     * A handle kept earlier is taken back as it is: a number the plans of a later import no longer know still loads.
     */
    public function testAKeptHandleIsTakenBackAsItIs(): void
    {
        $kept = Handle::fromCanonical(KnownMessenger::WhatsApp, '+0145550123');

        self::assertNull(KnownMessenger::WhatsApp->normalize('+0145550123'));
        self::assertSame(['+0145550123', 'https://wa.me/0145550123'], [$kept->value, $kept->link()]);
        self::assertTrue(Handle::fromCanonical(KnownMessenger::Telegram, 'adalove')->equals(Handle::of(KnownMessenger::Telegram, 'https://t.me/AdaLove')));
    }

    public function testADetectedHandleIsTheCanonicalOne(): void
    {
        $handle = Messengers::detect('https://line.me/R/ti/p/@Acme', EnumSet::all(KnownMessenger::class));

        self::assertSame([KnownMessenger::Line, '@acme', 'https://line.me/R/ti/p/@acme'], [$handle?->type, $handle?->value, $handle?->link()]);
        self::assertNull(Messengers::detect('https://t.me/joinchat/AbCdEfGh', EnumSet::all(KnownMessenger::class)));
    }

    public function testAHandleOfAnotherServiceIsRefused(): void
    {
        try {
            Handle::of(KnownMessenger::WhatsApp, '514 555-0123');
            self::fail('A national number was taken.');
        } catch (InvalidHandle $invalidHandle) {
            self::assertSame('"514 555-0123" is not a WhatsApp handle.', $invalidHandle->getMessage());
            self::assertInstanceOf(InstantMessengerException::class, $invalidHandle);
        }

        self::assertSame(\sprintf('"%s" is not a Telegram handle.', str_repeat('é', 100)), InvalidHandle::notOfType(str_repeat('é', 150), 'Telegram')->getMessage());
        self::assertSame(\sprintf('"%s" is a handle on none of the accepted services.', str_repeat('é', 100)), InvalidHandle::noType(str_repeat('é', 150))->getMessage());
    }

    public function testTheServiceIsDetectedFromALink(): void
    {
        $all = EnumSet::all(KnownMessenger::class);

        self::assertSame(KnownMessenger::WhatsApp, Messengers::detect('https://wa.me/15145550123', $all)?->type);
        self::assertSame(KnownMessenger::Matrix, Messengers::detect('@ada:matrix.org', $all)?->type);
        self::assertSame(KnownMessenger::Telegram, Messengers::detect('t.me/adalove', EnumSet::of(KnownMessenger::class, KnownMessenger::Signal, KnownMessenger::Telegram))?->type);
        self::assertNull(Messengers::detect('nothing at all', EnumSet::of(KnownMessenger::class, KnownMessenger::WhatsApp)));
        self::assertNull(Messengers::detect('+15145550123'));
    }

    public function testAnApplicationsServicesComeFirstWhenListedFirst(): void
    {
        $forum = EnumSet::all(ForumMessenger::class);
        $known = EnumSet::all(KnownMessenger::class);

        self::assertSame(ForumMessenger::Forum, Messengers::detect('forum:ada', $forum, $known)?->type);
        self::assertSame(KnownMessenger::Telegram, Messengers::detect('adalove', $forum, $known)?->type);
        self::assertSame('https://forum.example/u/ada', Messengers::handle('forum:ada', $known, $forum)->link());

        $this->expectException(InvalidHandle::class);
        $this->expectExceptionMessageIsOrContains('"forum:ada" is a handle on none of the accepted services.');

        Messengers::handle('forum:ada', EnumSet::of(KnownMessenger::class, KnownMessenger::WhatsApp));
    }
}
