<?php

declare(strict_types=1);

namespace Phil\EventWeather\Tests\Unit\Cap;

use Phil\EventWeather\AlertSeverity;
use Phil\EventWeather\Cap\CapReader;
use Phil\EventWeather\Exception\InvalidCapDocument;
use Phil\EventWeather\WeatherAlert;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Throwable;

#[CoversClass(CapReader::class)]
final class CapReaderTest extends TestCase
{
    private const string FIXTURE = __DIR__.'/../../Fixtures/cap-alert.xml';

    public function testItReadsEachInfoBlock(): void
    {
        $alerts = new CapReader()->read((string) file_get_contents(self::FIXTURE));

        self::assertCount(2, $alerts);
        [$english, $french] = $alerts;

        self::assertSame('urn:oid:2.49.0.1.124.1234567890.2026|en-CA', $english->id);
        self::assertSame(['freezing rain', AlertSeverity::Moderate, 'Environment Canada', 'freezing rain warning in effect'], [$english->event, $english->severity, $english->sender, $english->headline]);
        self::assertSame(['Freezing rain is expected this afternoon & evening.', 'Take extra care when walking or driving.', 'Montréal; Laval', 'en-CA'], [$english->description, $english->instruction, $english->area, $english->language]);
        self::assertSame(['2026-11-13T14:00:00+00:00', '2026-11-13T20:00:00+00:00'], [$english->effective?->format(\DATE_ATOM), $english->expires?->format(\DATE_ATOM)]);
        self::assertSame('https://weather.gc.ca/warnings/index_e.html', $english->web?->toString());

        self::assertSame(['pluie verglaçante', 'cap-pac@canada.ca', null, null, 'fr-CA'], [$french->event, $french->sender, $french->web, $french->description, $french->language]);
        self::assertSame('2026-11-13T10:05:00+00:00', $french->effective?->format(\DATE_ATOM));
    }

    public function testItReadsOneLanguage(): void
    {
        $xml = (string) file_get_contents(self::FIXTURE);

        self::assertSame(['fr-CA'], array_map(static fn (WeatherAlert $alert): ?string => $alert->language, new CapReader('fr')->read($xml)));
        self::assertSame(['en-CA'], array_map(static fn (WeatherAlert $alert): ?string => $alert->language, new CapReader('EN-ca')->read($xml)));
        self::assertSame([], new CapReader('f')->read($xml));
        self::assertSame([], new CapReader('de')->read($xml));
    }

    public function testAnInfoWithoutLanguageIsInAmericanEnglish(): void
    {
        $alerts = new CapReader('en')->read(self::alert('<info><event>wind</event><severity>Extreme</severity><effective>2026-11-13T07:00:00-05:00</effective><area><areaDesc> </areaDesc></area></info>'));

        self::assertSame(['en-US', AlertSeverity::Extreme, '2026-11-13T12:00:00+00:00', null], [$alerts[0]->language, $alerts[0]->severity, $alerts[0]->effective?->format(\DATE_ATOM), $alerts[0]->area]);
    }

    public function testAnUnknownSeverityIsUnknown(): void
    {
        self::assertSame(AlertSeverity::Unknown, new CapReader()->read(self::alert('<info><event>wind</event><severity>Huge</severity></info>'))[0]->severity);
        self::assertSame(AlertSeverity::Unknown, new CapReader()->read(self::alert('<info><event>wind</event></info>'))[0]->severity);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function silent(): iterable
    {
        yield 'test' => ['Test', 'Alert'];
        yield 'exercise' => ['Exercise', 'Alert'];
        yield 'cancellation' => ['Actual', 'Cancel'];
    }

    #[DataProvider('silent')]
    public function testOnlyAnActualAlertGivesAlerts(string $status, string $type): void
    {
        self::assertSame([], new CapReader()->read(self::alert('<info><event>wind</event></info>', $status, $type)));
    }

    public function testAnUpdateIsAnAlert(): void
    {
        self::assertCount(1, new CapReader()->read(self::alert('<info><event>wind</event></info>', 'Actual', 'Update')));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function invalid(): iterable
    {
        yield 'not XML' => ['<alert', 'The document is not XML.'];
        yield 'other root' => ['<feed xmlns="urn:oasis:names:tc:emergency:cap:1.2"/>', 'The document is not a CAP 1.2 alert.'];
        yield 'other version' => ['<alert xmlns="urn:oasis:names:tc:emergency:cap:1.1"/>', 'The document is not a CAP 1.2 alert.'];
        yield 'document type' => ['<!DOCTYPE alert [<!ENTITY x "y">]><alert xmlns="urn:oasis:names:tc:emergency:cap:1.2"/>', 'A CAP alert carries no document type.'];
        yield 'no identifier' => ['<alert xmlns="urn:oasis:names:tc:emergency:cap:1.2"><sender>s</sender></alert>', 'A CAP alert names its "identifier".'];
        yield 'empty sender' => ['<alert xmlns="urn:oasis:names:tc:emergency:cap:1.2"><identifier>i</identifier><sender> </sender></alert>', 'A CAP alert names its "sender".'];
        yield 'bad sent' => ['<alert xmlns="urn:oasis:names:tc:emergency:cap:1.2"><identifier>i</identifier><sender>s</sender><sent>yesterday</sent></alert>', 'The "sent" of a CAP alert is an instant with its offset.'];
        yield 'no status' => ['<alert xmlns="urn:oasis:names:tc:emergency:cap:1.2"><identifier>i</identifier><sender>s</sender><sent>2026-11-13T05:00:00-05:00</sent><msgType>Alert</msgType></alert>', 'A CAP alert names its "status".'];
        yield 'no type' => ['<alert xmlns="urn:oasis:names:tc:emergency:cap:1.2"><identifier>i</identifier><sender>s</sender><sent>2026-11-13T05:00:00-05:00</sent><status>Actual</status></alert>', 'A CAP alert names its "msgType".'];
        yield 'no event' => [self::alert('<info><severity>Minor</severity></info>'), 'Each info block of a CAP alert names its "event".'];
        yield 'too many infos' => [self::alert(str_repeat('<info><event>e</event></info>', 3)), 'A CAP alert holds more than 2 info blocks.'];
        yield 'another encoding' => ['<?xml version="1.0" encoding="UTF-7"?><alert xmlns="urn:oasis:names:tc:emergency:cap:1.2"/>', 'A CAP alert is written in UTF-8.'];
        yield 'invalid UTF-8' => ["<alert xmlns=\"urn:oasis:names:tc:emergency:cap:1.2\"><identifier>\xA9</identifier></alert>", 'A CAP alert is written in UTF-8.'];
        yield 'control character' => ["<\x00\x00\x00m<<", 'A CAP alert is written in UTF-8.'];
        yield 'too large' => [self::alert(str_repeat(' ', 2_000)), 'A CAP alert is 2000 bytes at most.'];
    }

    #[DataProvider('invalid')]
    public function testItRefusesWhatIsNotACapAlert(string $xml, string $message): void
    {
        $this->expectException(InvalidCapDocument::class);
        $this->expectExceptionMessageIsOrContains($message);

        new CapReader(maxBytes: 2_000, maxInfos: 2)->read($xml);
    }

    public function testElementsOfAnotherNamespaceAreIgnored(): void
    {
        $alerts = new CapReader()->read(self::alert('<x:info xmlns:x="urn:other"><x:event>no</x:event></x:info><info><event>yes</event><x:headline xmlns:x="urn:other">no</x:headline></info>'));

        self::assertSame([['yes', null]], array_map(static fn (WeatherAlert $alert): array => [$alert->event, $alert->headline], $alerts));
    }

    public function testUtf8MayBeDeclared(): void
    {
        self::assertCount(1, new CapReader()->read('<?xml version="1.0" encoding="utf-8"?>'.self::alert('<info><event>e</event></info>')));
        self::assertCount(1, new CapReader()->read("<?xml version='1.0'?>".self::alert('<info><event>e</event></info>')));
    }

    public function testTheLimitsAreInclusive(): void
    {
        $xml = self::alert('<info><event>a</event></info><info><event>b</event></info>');

        self::assertCount(2, new CapReader(maxBytes: \strlen($xml), maxInfos: 2)->read($xml));
    }

    public function testTheDefaultLimitsAreAMegabyteAndSixtyFourInfos(): void
    {
        $xml = self::alert('<info><event>e</event></info>');
        $padded = $xml.str_repeat(' ', 1_048_576 - \strlen($xml));

        self::assertCount(1, new CapReader()->read($padded));
        self::assertCount(64, new CapReader()->read(self::alert(str_repeat('<info><event>e</event></info>', 64))));

        foreach ([
            [$padded.' ', 'A CAP alert is 1048576 bytes at most.'],
            [self::alert(str_repeat('<info><event>e</event></info>', 65)), 'A CAP alert holds more than 64 info blocks.'],
        ] as [$document, $message]) {
            try {
                new CapReader()->read($document);
                self::fail('Read: '.$message);
            } catch (InvalidCapDocument $exception) {
                self::assertSame($message, $exception->getMessage());
            }
        }
    }

    public function testADocumentTypeIsRefusedInAnyCase(): void
    {
        $this->expectException(InvalidCapDocument::class);
        $this->expectExceptionMessageIsOrContains('A CAP alert carries no document type.');

        new CapReader()->read('<!doctype alert><alert xmlns="urn:oasis:names:tc:emergency:cap:1.2"/>');
    }

    public function testAnUnparsableDocumentKeepsItsCause(): void
    {
        try {
            new CapReader()->read('<alert');
            self::fail('Read.');
        } catch (InvalidCapDocument $invalidCapDocument) {
            self::assertSame(0, $invalidCapDocument->getCode());
            self::assertInstanceOf(Throwable::class, $invalidCapDocument->getPrevious());
        }
    }

    public function testTextIsTrimmed(): void
    {
        $alerts = new CapReader()->read(self::alert("<info><event>\n  wind  \n</event><headline> a headline </headline></info>"));

        self::assertSame(['wind', 'a headline'], [$alerts[0]->event, $alerts[0]->headline]);
    }

    private static function alert(string $infos, string $status = 'Actual', string $type = 'Alert'): string
    {
        return '<alert xmlns="urn:oasis:names:tc:emergency:cap:1.2"><identifier>i</identifier><sender>s</sender><sent>2026-11-13T05:00:00-05:00</sent>'
            .'<status>'.$status.'</status><msgType>'.$type.'</msgType><scope>Public</scope>'.$infos.'</alert>';
    }
}
