<?php

declare(strict_types=1);

namespace Phil\Contact\Tests\Unit;

use Phil\Contact\ContactCard;
use Phil\Contact\Email;
use Phil\Contact\PersonName;
use Phil\Contact\PhoneLine;
use Phil\Contact\PhoneRole;
use Phil\Contact\VCard;
use Phil\InstantMessenger\Handle;
use Phil\InstantMessenger\KnownMessenger;
use Phil\Phone\PhoneNumber;
use Phil\Url\KnownUrlType;
use Phil\Url\TypedUrl;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(VCard::class)]
final class VCardTest extends TestCase
{
    public function testAPersonIsWrittenAsVCard4(): void
    {
        $card = ContactCard::of(
            'Ada Lovelace',
            'Engines; Looms, and more',
            "Countess\\of\nLovelace",
            [Email::of('ada@example.com'), Email::of('"a,da"@example.com')],
            [new PhoneLine(PhoneRole::Mobile, PhoneNumber::parse('+1 514 555 0123')), new PhoneLine(PhoneRole::Fax, PhoneNumber::parse('+33 1 23 45 67 89 x2'))],
            [TypedUrl::of(KnownUrlType::Website, 'https://example.com')],
            [Handle::of(KnownMessenger::Xmpp, 'ada@jabber.org'), Handle::of(KnownMessenger::Discord, 'ada'), Handle::of(KnownMessenger::Telegram, 'adalove')],
        );

        self::assertSame(implode("\r\n", [
            'BEGIN:VCARD',
            'VERSION:4.0',
            'KIND:individual',
            'FN:Ada Lovelace',
            'ORG:Engines\; Looms\, and more',
            'TITLE:Countess\\\\of\nLovelace',
            'EMAIL:ada@example.com',
            'EMAIL:"a\,da"@example.com',
            'TEL;VALUE=uri;TYPE=cell:tel:+1-514-555-0123',
            'TEL;VALUE=uri;TYPE=fax:tel:+33-1-23-45-67-89;ext=2',
            'URL:https://example.com/',
            'IMPP:xmpp:ada@jabber.org',
            'IMPP:https://t.me/adalove',
            'END:VCARD',
        ])."\r\n", VCard::of($card));
        self::assertSame('text/vcard', VCard::MEDIA_TYPE);
    }

    public function testAStructuredNameIsN(): void
    {
        $lines = static fn (PersonName $name): string => explode("\r\n", str_replace("\r\n ", '', VCard::of(ContactCard::of(structuredName: $name))))[4];

        self::assertSame('N:Lovelace;Ada;Augusta,Byron;Dr.;PhD', $lines(PersonName::of('Ada', 'Lovelace', additional: ['Augusta', 'Byron'], honorific: 'Dr.', credential: 'PhD')));
        self::assertSame('N;LANGUAGE=fr;SORT-AS="Gaulle,Charles":de Gaulle;Charles;;;', $lines(PersonName::of('Charles', 'Gaulle', 'de', culture: 'fr')));
        self::assertSame('N;LANGUAGE=nl-BE:Van Rompuy;Herman;;;', $lines(PersonName::of('Herman', 'Rompuy', 'Van', culture: 'nl-BE')));
        self::assertSame('N;LANGUAGE=es:García,Márquez;Gabriel;;;;Márquez;', $lines(PersonName::of('Gabriel', 'García', secondFamily: 'Márquez', culture: 'es')));
        self::assertSame('N:King;Martin;Luther;;Jr.;;Jr.', $lines(PersonName::of('Martin', 'King', additional: ['Luther'], generation: 'Jr.')));
        self::assertSame('N;LANGUAGE=nl;SORT-AS="Gogh,":van Gogh;;;;', $lines(PersonName::of(family: 'Gogh', particle: 'van', culture: 'nl')));
        self::assertSame('N;LANGUAGE=de;SORT-AS="Leyen  ^\'X^\' ^^,Ur sula":von der Leyen\\, "X" ^;Ur\\,sula;;;', $lines(PersonName::of('Ur,sula', 'Leyen, "X" ^', 'von der', culture: 'de')));
    }

    public function testAnOrganisationIsOfItsKind(): void
    {
        self::assertSame("BEGIN:VCARD\r\nVERSION:4.0\r\nKIND:org\r\nFN:Engines\r\nORG:Engines\r\nEND:VCARD\r\n", VCard::of(ContactCard::of(organization: 'Engines')));
        self::assertSame("BEGIN:VCARD\r\nVERSION:4.0\r\nKIND:individual\r\nFN:\r\nTITLE:Countess\r\nEND:VCARD\r\n", VCard::of(ContactCard::of(title: 'Countess')));
    }

    public function testALongLineIsFoldedBetweenCharacters(): void
    {
        $lines = explode("\r\n", VCard::of(ContactCard::of(str_repeat('a', 71).'é'.str_repeat('b', 80))));

        self::assertSame(['FN:'.str_repeat('a', 71), ' é'.str_repeat('b', 72), ' bbbbbbbb', 'END:VCARD'], \array_slice($lines, 3, 4));

        $lines = explode("\r\n", VCard::of(ContactCard::of(str_repeat('a', 72).str_repeat('b', 73).'é')));

        self::assertSame(['FN:'.str_repeat('a', 72), ' '.str_repeat('b', 73), ' é', 'END:VCARD'], \array_slice($lines, 3, 4));
        self::assertSame('FN:'.str_repeat('a', 72), explode("\r\n", VCard::of(ContactCard::of(str_repeat('a', 72))))[3]);
        self::assertSame(['FN:'.str_repeat('a', 72), ' a'], \array_slice(explode("\r\n", VCard::of(ContactCard::of(str_repeat('a', 73)))), 3, 2));
    }

    public function testCarriageReturnsAndNewlinesAreEscapedOnce(): void
    {
        self::assertStringContainsString("\r\nTITLE:a\\nb\\nc\r\n", VCard::of(ContactCard::of(title: "a\r\nb\nc")));
    }

    public function testALoneCarriageReturnCannotStartAProperty(): void
    {
        $vcard = VCard::of(ContactCard::of("Ada\rTEL:+1555"));

        self::assertStringContainsString("\r\nFN:Ada\\nTEL:+1555\r\n", $vcard);
        self::assertSame(substr_count($vcard, "\r\n"), substr_count($vcard, "\r"));
    }
}
