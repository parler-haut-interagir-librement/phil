<?php

declare(strict_types=1);

namespace Phil\CalendarImport\Tests\Unit\Source;

use DateTimeZone;
use Phil\CalendarImport\Exception\InvalidSource;
use Phil\CalendarImport\Source\CalendarSource;
use Phil\CalendarImport\Source\FetchValidators;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Throwable;

#[CoversClass(CalendarSource::class)]
#[CoversClass(FetchValidators::class)]
#[CoversClass(InvalidSource::class)]
final class ValuesTest extends TestCase
{
    public function testASourceIsReadByItsAddressAndWebcalIsHttps(): void
    {
        $source = CalendarSource::of('club', ' WEBCAL://Example.org/events.ics');
        self::assertSame('club', $source->id);
        self::assertSame('https://example.org/events.ics', $source->url->toString());
        self::assertSame('UTC', $source->zone->getName());

        self::assertSame('https://example.org/a', CalendarSource::of('a', 'webcals://example.org/a')->url->toString());
        self::assertSame('http://example.org/a', CalendarSource::of('a', 'http://example.org/a')->url->toString());
        self::assertSame('Europe/Paris', CalendarSource::of('a', 'https://example.org/', 'Europe/Paris')->zone->getName());
        $zone = new DateTimeZone('Asia/Tokyo');
        self::assertSame($zone, CalendarSource::of('a', 'https://example.org/', $zone)->zone);
        self::assertSame(str_repeat('i', 255), CalendarSource::of(str_repeat('i', 255), 'https://example.org/')->id);
        self::assertSame("a\u{00A0}b", CalendarSource::of("a\u{00A0}b", 'https://example.org/')->id);
    }

    /**
     * @return iterable<string, array{string, string, string, string}>
     */
    public static function invalid(): iterable
    {
        yield 'an empty identifier' => ['', 'https://example.org/', 'UTC', 'The identifier of a source is 1 to 255 bytes, on one line.'];
        yield 'an identifier too long' => [str_repeat('i', 256), 'https://example.org/', 'UTC', 'The identifier of a source is 1 to 255 bytes, on one line.'];
        yield 'an identifier on two lines' => ["a\nb", 'https://example.org/', 'UTC', 'The identifier of a source is 1 to 255 bytes, on one line.'];
        yield 'an identifier with a delete' => ["a\x7F", 'https://example.org/', 'UTC', 'The identifier of a source is 1 to 255 bytes, on one line.'];
        yield 'a private address' => ['a', 'https://10.0.0.1/a.ics', 'UTC', 'The address of the source "a" is not a public web address.'];
        yield 'localhost' => ['a', 'https://localhost/a.ics', 'UTC', 'The address of the source "a" is not a public web address.'];
        yield 'another scheme' => ['a', 'ftp://example.org/a.ics', 'UTC', 'The address of the source "a" is not a public web address.'];
        yield 'webcal not at the start' => ['a', 'x webcal://example.org/a.ics', 'UTC', 'The address of the source "a" is not a public web address.'];
        yield 'a zone PHP does not know' => ['a', 'https://example.org/', 'Mars/Olympus', 'The zone of the source "a" is not one PHP knows.'];
    }

    #[DataProvider('invalid')]
    public function testASourceThatCannotBeReadFromIsRefused(string $id, string $address, string $zone, string $message): void
    {
        try {
            CalendarSource::of($id, $address, $zone);
            self::fail('The source is refused.');
        } catch (InvalidSource $invalidSource) {
            self::assertSame($message, $invalidSource->getMessage());
            self::assertSame('The identifier of a source is 1 to 255 bytes, on one line.' === $message, !$invalidSource->getPrevious() instanceof Throwable);
        }
    }

    public function testValidatorsAreKeptWhenTheyCanBeSentBack(): void
    {
        $validators = new FetchValidators(' "v1" ', 'Sat, 21 Nov 2026 10:00:00 GMT');
        self::assertSame('"v1"', $validators->etag);
        self::assertFalse($validators->isEmpty());
        self::assertSame(['If-None-Match' => '"v1"', 'If-Modified-Since' => 'Sat, 21 Nov 2026 10:00:00 GMT'], $validators->headers());

        self::assertSame(['If-Modified-Since' => 'x'], new FetchValidators(null, 'x')->headers());
        self::assertSame(['If-None-Match' => 'x'], new FetchValidators('x')->headers());
        self::assertTrue(new FetchValidators()->isEmpty());
        self::assertFalse(new FetchValidators(null, 'x')->isEmpty());
        self::assertSame([], new FetchValidators()->headers());
        self::assertSame(str_repeat('e', 1024), new FetchValidators(str_repeat('e', 1024))->etag);

        foreach (['', '  ', str_repeat('e', 1025), "a\r\nX-Injected: 1", "a\x7F"] as $refused) {
            self::assertNull(new FetchValidators($refused)->etag);
            self::assertNull(new FetchValidators(null, $refused)->lastModified);
        }
    }
}
