<?php

declare(strict_types=1);

namespace Phil\QrCodeBundle\Tests\Unit\Application;

use DateTimeImmutable;
use DateTimeZone;
use Phil\QrCode\Content\Email;
use Phil\QrCode\Content\Event;
use Phil\QrCode\Content\Geo;
use Phil\QrCode\Content\MeCard;
use Phil\QrCode\Content\Phone;
use Phil\QrCode\Content\Sms;
use Phil\QrCode\Content\Url;
use Phil\QrCode\Content\VCard;
use Phil\QrCode\Content\VCardVersion;
use Phil\QrCode\Content\Wifi;
use Phil\QrCode\Content\WifiSecurity;
use Phil\QrCode\Exception\InvalidContent;
use Phil\QrCodeBundle\Application\ContentFactory;
use Phil\QrCodeBundle\Application\Fields;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(ContentFactory::class)]
#[CoversClass(Fields::class)]
final class ContentFactoryTest extends TestCase
{
    public function testBuildsEachContent(): void
    {
        self::assertEquals(new Url('https://example.com'), ContentFactory::fromArray(['type' => 'url', 'url' => 'https://example.com']));
        self::assertEquals(new Email(['a@example.com'], 'S', 'B', ['c@example.com'], ['d@example.com', 'e@example.com']), ContentFactory::fromArray(['type' => 'email', 'to' => 'a@example.com', 'subject' => 'S', 'body' => 'B', 'cc' => ['c@example.com'], 'bcc' => ['d@example.com', 'e@example.com']]));
        self::assertEquals(new Phone('+1555'), ContentFactory::fromArray(['type' => 'phone', 'number' => '+1555']));
        self::assertEquals(new Sms('+1555', 'Hi'), ContentFactory::fromArray(['type' => 'sms', 'number' => '+1555', 'message' => 'Hi']));
        self::assertEquals(new Geo(46.8, -71, 100.0, 5.0, 'Q'), ContentFactory::fromArray(['type' => 'geo', 'latitude' => 46.8, 'longitude' => -71, 'altitude' => 100, 'uncertainty' => 5.0, 'name' => 'Q']));
        self::assertEquals(new Geo(1, 2), ContentFactory::fromArray(['type' => 'geo', 'latitude' => 1, 'longitude' => 2]));
        self::assertEquals(new Geo(0, 0), ContentFactory::fromArray(['type' => 'geo']));
        self::assertEquals(new Wifi('Net', 'password', WifiSecurity::Wpa), ContentFactory::fromArray(['type' => 'wifi', 'ssid' => 'Net', 'password' => 'password']));
        self::assertEquals(new Wifi('Net', '', WifiSecurity::Open, true), ContentFactory::fromArray(['type' => 'wifi', 'ssid' => 'Net', 'security' => 'nopass', 'hidden' => true]));
        self::assertEquals(new MeCard('Jane', ['+1555'], ['j@example.com'], 'https://example.com', 'A', 'N', new DateTimeImmutable('1990-03-04')), ContentFactory::fromArray(['type' => 'mecard', 'name' => 'Jane', 'phones' => '+1555', 'emails' => ['j@example.com'], 'url' => 'https://example.com', 'address' => 'A', 'note' => 'N', 'birthday' => '1990-03-04']));
        self::assertEquals(new MeCard('Jane'), ContentFactory::fromArray(['type' => 'mecard', 'name' => 'Jane']));
    }

    public function testBuildsAVCard(): void
    {
        $card = ContentFactory::fromArray([
            'type' => 'vcard',
            'name' => 'Jane Doe',
            'family_name' => 'Doe',
            'given_name' => 'Jane',
            'organization' => 'ACME',
            'title' => 'Boss',
            'phones' => ['+1555'],
            'emails' => ['j@example.com'],
            'urls' => ['https://example.com'],
            'address' => ['street' => '1 Main', 'locality' => 'Québec', 'region' => 'QC', 'postal_code' => 'G1A', 'country' => 'Canada'],
            'birthday' => '1990-03-04',
            'note' => 'N',
            'version' => '4.0',
        ]);

        self::assertInstanceOf(VCard::class, $card);
        self::assertSame(VCardVersion::V4, $card->version);
        self::assertSame(['Jane Doe', 'Doe', 'Jane', 'ACME', 'Boss', ['+1555'], ['j@example.com'], ['https://example.com'], 'N'], [$card->fullName, $card->familyName, $card->givenName, $card->organization, $card->title, $card->phones, $card->emails, $card->urls, $card->note]);
        self::assertSame(['1 Main', 'Québec', 'QC', 'G1A', 'Canada'], [$card->address?->street, $card->address?->locality, $card->address?->region, $card->address?->postalCode, $card->address?->country]);
        self::assertSame('1990-03-04', $card->birthday?->format('Y-m-d'));

        $plain = ContentFactory::fromArray(['type' => 'vcard', 'name' => 'Jane']);
        self::assertInstanceOf(VCard::class, $plain);
        self::assertSame(VCardVersion::V3, $plain->version);
        self::assertNull($plain->address);
        self::assertNull($plain->birthday);
    }

    public function testBuildsAnEvent(): void
    {
        $event = ContentFactory::fromArray(['type' => 'event', 'summary' => 'Game', 'start' => '2026-11-08T19:00:00-05:00', 'end' => '2026-11-08T23:00:00-05:00', 'location' => 'Room', 'description' => 'D', 'url' => 'https://example.com', 'all_day' => false, 'uid' => 'u']);

        self::assertInstanceOf(Event::class, $event);
        self::assertSame(['Game', 'Room', 'D', 'https://example.com', 'u', false], [$event->summary, $event->location, $event->description, $event->url, $event->uid, $event->allDay]);
        self::assertSame('2026-11-09T00:00:00+00:00', DateTimeImmutable::createFromInterface($event->start)->setTimezone(new DateTimeZone('UTC'))->format('c'));
        self::assertNotNull($event->end);
        self::assertSame('2026-11-09T04:00:00+00:00', DateTimeImmutable::createFromInterface($event->end)->setTimezone(new DateTimeZone('UTC'))->format('c'));

        $day = ContentFactory::fromArray(['type' => 'event', 'summary' => 'Con', 'start' => '2026-11-08', 'all_day' => true]);
        self::assertInstanceOf(Event::class, $day);
        self::assertTrue($day->allDay);
        self::assertNull($day->end);
    }

    /**
     * @return iterable<string, array{array<array-key, mixed>, string}>
     */
    public static function refused(): iterable
    {
        yield 'no type' => [['url' => 'https://example.com'], 'The type of the content must be one of url, email, phone, sms, geo, wifi, mecard, vcard, event.'];
        yield 'a type not a text' => [['type' => 3], 'The type of the content'];
        yield 'an unknown type' => [['type' => 'fax'], 'The type of the content'];
        yield 'an unknown key' => [['type' => 'url', 'url' => 'https://example.com', 'href' => 'x', 0 => 'y'], 'The content of type url must be made of the keys url; not href, 0.'];
        yield 'a text of the wrong type' => [['type' => 'url', 'url' => 3], 'The url must be a text.'];
        yield 'a list of the wrong type' => [['type' => 'email', 'to' => [3]], 'The to must be a text or a list of texts.'];
        yield 'a map in place of a list' => [['type' => 'email', 'to' => ['a' => 'a@example.com']], 'The to must be a text or a list of texts.'];
        yield 'a number not given as one' => [['type' => 'geo', 'latitude' => '46'], 'The latitude must be a number.'];
        yield 'a boolean not given as one' => [['type' => 'wifi', 'ssid' => 'N', 'hidden' => 'yes'], 'The hidden must be true or false.'];
        yield 'an unknown security' => [['type' => 'wifi', 'ssid' => 'N', 'security' => 'WPA3'], 'The security must be WPA, WEP or nopass.'];
        yield 'an unknown version' => [['type' => 'vcard', 'name' => 'J', 'version' => '2.1'], 'The version of the vCard must be 3.0 or 4.0.'];
        yield 'an address with an unknown key' => [['type' => 'vcard', 'name' => 'J', 'address' => ['city' => 'Q']], 'The address must be made of the keys street, locality, region, postal_code, country.'];
        yield 'an address not an array' => [['type' => 'vcard', 'name' => 'J', 'address' => 'Q'], 'The address must be made of the keys'];
        yield 'a date that is none' => [['type' => 'mecard', 'name' => 'J', 'birthday' => 'tomorrowish'], 'The birthday must be a date, as 2026-11-08 or 2026-11-08T19:00:00-05:00.'];
        yield 'an event without a start' => [['type' => 'event', 'summary' => 'S'], 'The start of the event must be a date.'];
        yield 'an end that is no date' => [['type' => 'event', 'summary' => 'S', 'start' => '2026-11-08', 'end' => 'not a date'], 'The end of the event must be a date'];
    }

    /**
     * @param array<array-key, mixed> $data
     */
    #[DataProvider('refused')]
    public function testRefusesWhatIsNotAContent(array $data, string $message): void
    {
        $this->expectException(InvalidContent::class);
        $this->expectExceptionMessageIsOrContains($message);

        ContentFactory::fromArray($data);
    }

    public function testListsTheKeysOfEachType(): void
    {
        self::assertSame(['url', 'email', 'phone', 'sms', 'geo', 'wifi', 'mecard', 'vcard', 'event'], array_keys(ContentFactory::KEYS));
    }
}
