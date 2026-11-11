<?php

declare(strict_types=1);

namespace Phil\InstantMessenger\Tests\Unit;

use Phil\InstantMessenger\KnownMessenger;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(KnownMessenger::class)]
final class KnownMessengerTest extends TestCase
{
    /**
     * @return iterable<string, array{KnownMessenger, string, ?string}>
     */
    public static function handles(): iterable
    {
        yield 'whatsapp number' => [KnownMessenger::WhatsApp, '+1 (514) 555-0123', '+15145550123'];
        yield 'whatsapp dotted' => [KnownMessenger::WhatsApp, "+33\u{A0}6.12.34.56.78", '+33612345678'];
        yield 'whatsapp link' => [KnownMessenger::WhatsApp, 'https://wa.me/15145550123', '+15145550123'];
        yield 'whatsapp link without scheme' => [KnownMessenger::WhatsApp, 'wa.me/15145550123', '+15145550123'];
        yield 'whatsapp api link' => [KnownMessenger::WhatsApp, 'https://api.whatsapp.com/send?phone=15145550123', '+15145550123'];
        yield 'whatsapp api link with plus' => [KnownMessenger::WhatsApp, 'https://api.whatsapp.com/send?phone=%2B15145550123', '+15145550123'];
        yield 'whatsapp api link without phone' => [KnownMessenger::WhatsApp, 'https://api.whatsapp.com/send', null];
        yield 'whatsapp bare link' => [KnownMessenger::WhatsApp, 'https://wa.me/', null];
        yield 'whatsapp national number' => [KnownMessenger::WhatsApp, '514 555-0123', null];
        yield 'whatsapp leading zero' => [KnownMessenger::WhatsApp, '+0145550123', null];
        yield 'whatsapp short' => [KnownMessenger::WhatsApp, '+123456', null];
        yield 'whatsapp not in the plan' => [KnownMessenger::WhatsApp, '+1234567', null];
        yield 'whatsapp too long' => [KnownMessenger::WhatsApp, '+1234567890123456', null];
        yield 'whatsapp with an extension' => [KnownMessenger::WhatsApp, '+1 514-555-0123 ext. 12', null];
        yield 'whatsapp letters' => [KnownMessenger::WhatsApp, '+1 514 FLOWERS', null];
        yield 'whatsapp tel' => [KnownMessenger::WhatsApp, 'tel:+33-6-12-34-56-78', null];
        yield 'whatsapp french mobile' => [KnownMessenger::WhatsApp, '+33 (0)6 12 34 56 78', '+33612345678'];
        yield 'whatsapp other link' => [KnownMessenger::WhatsApp, 'https://t.me/15145550123', null];
        yield 'viber number' => [KnownMessenger::Viber, '+15145550123', '+15145550123'];
        yield 'viber has no link to read' => [KnownMessenger::Viber, 'https://wa.me/15145550123', null];
        yield 'telegram name' => [KnownMessenger::Telegram, '@Ada_Lovelace', 'ada_lovelace'];
        yield 'telegram bare' => [KnownMessenger::Telegram, 'adalove', 'adalove'];
        yield 'telegram link' => [KnownMessenger::Telegram, 'https://t.me/ada_love', 'ada_love'];
        yield 'telegram old link' => [KnownMessenger::Telegram, 'telegram.me/ada_love', 'ada_love'];
        yield 'telegram short' => [KnownMessenger::Telegram, 'ada', null];
        yield 'telegram five' => [KnownMessenger::Telegram, 'adalo', 'adalo'];
        yield 'telegram digit first' => [KnownMessenger::Telegram, '1adalove', null];
        yield 'telegram underscore last' => [KnownMessenger::Telegram, 'adalove_', null];
        yield 'telegram long' => [KnownMessenger::Telegram, 'a'.str_repeat('b', 32), null];
        yield 'telegram longest' => [KnownMessenger::Telegram, 'a'.str_repeat('b', 31), 'a'.str_repeat('b', 31)];
        yield 'signal number' => [KnownMessenger::Signal, '+15145550123', '+15145550123'];
        yield 'signal link' => [KnownMessenger::Signal, 'https://signal.me/#p/+15145550123', '+15145550123'];
        yield 'signal link without fragment' => [KnownMessenger::Signal, 'https://signal.me/', null];
        yield 'signal user name' => [KnownMessenger::Signal, 'Ada.01', 'ada.01'];
        yield 'signal user name without number' => [KnownMessenger::Signal, 'ada', null];
        yield 'signal user name with one digit' => [KnownMessenger::Signal, 'ada.1', null];
        yield 'messenger name' => [KnownMessenger::Messenger, 'ada.lovelace', 'ada.lovelace'];
        yield 'messenger link' => [KnownMessenger::Messenger, 'https://m.me/AdaLovelace', 'adalovelace'];
        yield 'messenger short' => [KnownMessenger::Messenger, 'ada', null];
        yield 'matrix id' => [KnownMessenger::Matrix, '@Ada:Matrix.org', '@ada:matrix.org'];
        yield 'matrix with port' => [KnownMessenger::Matrix, '@ada:example.org:8448', '@ada:example.org:8448'];
        yield 'matrix link' => [KnownMessenger::Matrix, 'https://matrix.to/#/@ada:matrix.org', '@ada:matrix.org'];
        yield 'matrix link without fragment' => [KnownMessenger::Matrix, 'https://matrix.to/', null];
        yield 'matrix without server' => [KnownMessenger::Matrix, '@ada', null];
        yield 'xmpp id' => [KnownMessenger::Xmpp, 'Ada@Jabber.org', 'ada@jabber.org'];
        yield 'xmpp uri' => [KnownMessenger::Xmpp, 'xmpp:ada@jabber.org', 'ada@jabber.org'];
        yield 'xmpp without domain' => [KnownMessenger::Xmpp, 'ada@', null];
        yield 'threema id' => [KnownMessenger::Threema, 'abcd1234', 'ABCD1234'];
        yield 'threema gateway' => [KnownMessenger::Threema, '*ABCD123', '*ABCD123'];
        yield 'threema link' => [KnownMessenger::Threema, 'https://threema.id/ABCD1234', 'ABCD1234'];
        yield 'threema short' => [KnownMessenger::Threema, 'ABC1234', null];
        yield 'line id' => [KnownMessenger::Line, '~Ada.Love', 'ada.love'];
        yield 'line link' => [KnownMessenger::Line, 'https://line.me/ti/p/~adalove', 'adalove'];
        yield 'line short' => [KnownMessenger::Line, 'ada', null];
        yield 'discord name' => [KnownMessenger::Discord, '@Ada.Love', 'ada.love'];
        yield 'discord two dots' => [KnownMessenger::Discord, 'ada..love', null];
        yield 'discord one letter' => [KnownMessenger::Discord, 'a', null];
        yield 'wechat id' => [KnownMessenger::WeChat, 'AdaLove_1', 'AdaLove_1'];
        yield 'wechat digit first' => [KnownMessenger::WeChat, '1AdaLove', null];
        yield 'wechat short' => [KnownMessenger::WeChat, 'Ada12', null];
        yield 'whatsapp foreign link' => [KnownMessenger::WhatsApp, 'https://example.com/15145550123', null];
        yield 'whatsapp foreign query' => [KnownMessenger::WhatsApp, 'https://example.com/send?phone=15145550123', null];
        yield 'telegram foreign link' => [KnownMessenger::Telegram, 'https://example.com/adalove', null];
        yield 'signal foreign link' => [KnownMessenger::Signal, 'https://example.com/#p/+15145550123', null];
        yield 'signal plus without number' => [KnownMessenger::Signal, '+ada.01', null];
        yield 'messenger foreign link' => [KnownMessenger::Messenger, 'https://example.com/adalove', null];
        yield 'matrix foreign link' => [KnownMessenger::Matrix, 'https://example.com/#/@ada:matrix.org', null];
        yield 'threema foreign link' => [KnownMessenger::Threema, 'https://example.com/ABCD1234', null];
        yield 'line foreign link' => [KnownMessenger::Line, 'https://example.com/ti/p/~adalove', null];
        yield 'line short link' => [KnownMessenger::Line, 'https://line.me/ti', null];
        yield 'line share link' => [KnownMessenger::Line, 'https://line.me/R/ti/p/~AdaLove', 'adalove'];
        yield 'line official account link' => [KnownMessenger::Line, 'https://line.me/R/ti/p/@acme.shop', '@acme.shop'];
        yield 'line official account link encoded' => [KnownMessenger::Line, 'https://line.me/R/ti/p/%40acme', '@acme'];
        yield 'line official account' => [KnownMessenger::Line, '@Acme.Shop', '@acme.shop'];
        yield 'line official account short' => [KnownMessenger::Line, '@acm', null];
        yield 'line link of a friend code' => [KnownMessenger::Line, 'https://line.me/ti/p/Xy9Kq_Ab', null];
        yield 'line link of another page' => [KnownMessenger::Line, 'https://line.me/R/ti/g/~adalove', null];
        yield 'telegram invitation' => [KnownMessenger::Telegram, 'https://t.me/joinchat/AbCdEfGh', null];
        yield 'telegram sticker set' => [KnownMessenger::Telegram, 't.me/addstickers/Animals', null];
        yield 'telegram proxy' => [KnownMessenger::Telegram, 'https://t.me/proxy?server=example.org', null];
        yield 'telegram share' => [KnownMessenger::Telegram, 'https://telegram.me/share/url?url=x', null];
        yield 'telegram contact token' => [KnownMessenger::Telegram, 'https://t.me/contact/AbCdEfGh', null];
        yield 'telegram invitation in capitals' => [KnownMessenger::Telegram, 'https://t.me/JoinChat/AbCdEfGh', null];
        yield 'line link with a page after the id' => [KnownMessenger::Line, 'https://line.me/ti/p/~adalove/posts', null];
        yield 'line share link in lower case' => [KnownMessenger::Line, 'https://line.me/r/TI/p/@acme.shop', '@acme.shop'];
        yield 'xmpp with a query mark' => [KnownMessenger::Xmpp, 'ada?x@example.org', null];
        yield 'xmpp with a fragment mark' => [KnownMessenger::Xmpp, 'ada#x@example.org', null];
        yield 'xmpp with a control character' => [KnownMessenger::Xmpp, "ada\x01@example.org", null];
        yield 'xmpp with a delete character' => [KnownMessenger::Xmpp, "ada\x7F@example.org", null];
        yield 'xmpp with a semicolon' => [KnownMessenger::Xmpp, 'ada;x=1@example.org', 'ada;x=1@example.org'];
        yield 'xmpp international' => [KnownMessenger::Xmpp, 'ada.lové@example.org', 'ada.lové@example.org'];
        yield 'imessage number' => [KnownMessenger::IMessage, '+1 514 555-0123', '+15145550123'];
        yield 'imessage email' => [KnownMessenger::IMessage, 'Ada.Love+chat@iCloud.com', 'ada.love+chat@icloud.com'];
        yield 'imessage email on a subdomain' => [KnownMessenger::IMessage, 'ada@mail.example.co', 'ada@mail.example.co'];
        yield 'imessage national number' => [KnownMessenger::IMessage, '514 555-0123', null];
        yield 'imessage email without domain' => [KnownMessenger::IMessage, 'ada@icloud', null];
        yield 'imessage email without name' => [KnownMessenger::IMessage, '@icloud.com', null];
        yield 'imessage email with a long name' => [KnownMessenger::IMessage, str_repeat('a', 65).'@icloud.com', null];
        yield 'imessage email with the longest name' => [KnownMessenger::IMessage, str_repeat('a', 64).'@icloud.com', str_repeat('a', 64).'@icloud.com'];
        yield 'imessage email with a space' => [KnownMessenger::IMessage, 'ada love@icloud.com', null];
        yield 'imessage email with a one-letter extension' => [KnownMessenger::IMessage, 'ada@icloud.c', null];
        yield 'snapchat name' => [KnownMessenger::Snapchat, '@Ada.Love-1', 'ada.love-1'];
        yield 'snapchat link' => [KnownMessenger::Snapchat, 'https://www.snapchat.com/add/AdaLove', 'adalove'];
        yield 'snapchat link without scheme' => [KnownMessenger::Snapchat, 'snapchat.com/add/adalove', 'adalove'];
        yield 'snapchat link without name' => [KnownMessenger::Snapchat, 'https://www.snapchat.com/add', null];
        yield 'snapchat other page' => [KnownMessenger::Snapchat, 'https://www.snapchat.com/discover/adalove', null];
        yield 'snapchat foreign link' => [KnownMessenger::Snapchat, 'https://example.com/add/adalove', null];
        yield 'snapchat shortest' => [KnownMessenger::Snapchat, 'ada', 'ada'];
        yield 'snapchat short' => [KnownMessenger::Snapchat, 'ad', null];
        yield 'snapchat longest' => [KnownMessenger::Snapchat, 'a'.str_repeat('b', 14), 'a'.str_repeat('b', 14)];
        yield 'snapchat long' => [KnownMessenger::Snapchat, 'a'.str_repeat('b', 15), null];
        yield 'snapchat digit first' => [KnownMessenger::Snapchat, '1adalove', null];
        yield 'snapchat dot last' => [KnownMessenger::Snapchat, 'adalove.', null];
        yield 'session id' => [KnownMessenger::Session, '05ABABABABABABABABABABABABABABABABABABABABABABABABABABABABABABABAB', '05abababababababababababababababababababababababababababababababab'];
        yield 'session id of another kind' => [KnownMessenger::Session, '15abababababababababababababababababababababababababababababababab', null];
        yield 'session id short' => [KnownMessenger::Session, '05abababababababababababababababababababababababababababababababa', null];
        yield 'session id long' => [KnownMessenger::Session, '05ababababababababababababababababababababababababababababababababa', null];
        yield 'session id not hexadecimal' => [KnownMessenger::Session, '05agagagagagagagagagagagagagagagagagagagagagagagagagagagagagagagag', null];
        yield 'a trailing newline' => [KnownMessenger::Telegram, "adalove\n", 'adalove'];
        yield 'a newline inside' => [KnownMessenger::WeChat, "AdaLove\n_1", null];
    }

    #[DataProvider('handles')]
    public function testAServiceReadsItsHandles(KnownMessenger $type, string $input, ?string $handle): void
    {
        self::assertSame($handle, $type->normalize($input));
    }

    /**
     * @return iterable<string, array{KnownMessenger, string, string, ?string}>
     */
    public static function links(): iterable
    {
        yield 'whatsapp' => [KnownMessenger::WhatsApp, '+15145550123', '+1 514-555-0123', 'https://wa.me/15145550123'];
        yield 'telegram' => [KnownMessenger::Telegram, 'ada_love', '@ada_love', 'https://t.me/ada_love'];
        yield 'signal number' => [KnownMessenger::Signal, '+15145550123', '+1 514-555-0123', 'https://signal.me/#p/+15145550123'];
        yield 'signal user name' => [KnownMessenger::Signal, 'ada.01', 'ada.01', null];
        yield 'messenger' => [KnownMessenger::Messenger, 'ada.love', 'ada.love', 'https://m.me/ada.love'];
        yield 'matrix' => [KnownMessenger::Matrix, '@ada:matrix.org', '@ada:matrix.org', 'https://matrix.to/#/@ada:matrix.org'];
        yield 'xmpp' => [KnownMessenger::Xmpp, 'ada@jabber.org', 'ada@jabber.org', 'xmpp:ada@jabber.org'];
        yield 'threema' => [KnownMessenger::Threema, 'ABCD1234', 'ABCD1234', 'https://threema.id/ABCD1234'];
        yield 'viber' => [KnownMessenger::Viber, '+15145550123', '+1 514-555-0123', 'viber://chat?number=%2B15145550123'];
        yield 'line' => [KnownMessenger::Line, 'adalove', 'adalove', 'https://line.me/ti/p/~adalove'];
        yield 'line official account' => [KnownMessenger::Line, '@acme.shop', '@acme.shop', 'https://line.me/R/ti/p/@acme.shop'];
        yield 'xmpp with characters a node encodes' => [KnownMessenger::Xmpp, 'ada.lové%@example.org', 'ada.lové%@example.org', 'xmpp:ada.lov%C3%A9%25@example.org'];
        yield 'xmpp kept without its domain' => [KnownMessenger::Xmpp, 'ada', 'ada', 'xmpp:ada'];
        yield 'xmpp with characters a node keeps' => [KnownMessenger::Xmpp, 'ada!$()*+,;=@example.org', 'ada!$()*+,;=@example.org', 'xmpp:ada!$()*+,;=@example.org'];
        yield 'discord' => [KnownMessenger::Discord, 'ada.love', '@ada.love', null];
        yield 'wechat' => [KnownMessenger::WeChat, 'AdaLove_1', 'AdaLove_1', null];
        yield 'imessage' => [KnownMessenger::IMessage, '+15145550123', '+1 514-555-0123', null];
        yield 'imessage email' => [KnownMessenger::IMessage, 'ada@example.com', 'ada@example.com', null];
        yield 'a number no plan reads' => [KnownMessenger::Viber, '+0145550123', '+0145550123', 'viber://chat?number=%2B0145550123'];
        yield 'snapchat' => [KnownMessenger::Snapchat, 'adalove', 'adalove', 'https://www.snapchat.com/add/adalove'];
        yield 'session' => [KnownMessenger::Session, '05abababababababababababababababababababababababababababababababab', '05abababababababababababababababababababababababababababababababab', null];
    }

    #[DataProvider('links')]
    public function testAHandleIsShownAndLinked(KnownMessenger $type, string $handle, string $display, ?string $link): void
    {
        self::assertSame($display, $type->display($handle));
        self::assertSame($link, $type->link($handle));
    }

    public function testEveryServiceHasItsName(): void
    {
        self::assertSame(
            ['WhatsApp', 'Telegram', 'Signal', 'Messenger', 'Matrix', 'XMPP', 'Threema', 'Viber', 'Line', 'Discord', 'WeChat', 'iMessage', 'Snapchat', 'Session'],
            array_map(static fn (KnownMessenger $type): string => $type->label(), KnownMessenger::cases()),
        );
        self::assertSame('whatsapp', KnownMessenger::WhatsApp->value);
    }

    /**
     * A number is read by phil/phone: internationally, in its country's plan, without an extension.
     */
    public function testAPhoneNumberIsInItsInternationalForm(): void
    {
        self::assertSame('+15145550123', KnownMessenger::WhatsApp->normalize(' +1 514-555-0123 '));
        self::assertSame('+15145550123', KnownMessenger::Viber->normalize('+1 (514) 555.0123'));
        self::assertNull(KnownMessenger::WhatsApp->normalize('15145550123'));
        self::assertNull(KnownMessenger::WhatsApp->normalize("+15145550123\n0"));
        self::assertNull(KnownMessenger::IMessage->normalize('x+15145550123'));
        self::assertNull(KnownMessenger::IMessage->normalize('514-555-0123'));
    }
}
