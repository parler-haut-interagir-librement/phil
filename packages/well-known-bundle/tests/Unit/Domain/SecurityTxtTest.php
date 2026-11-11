<?php

declare(strict_types=1);

namespace Phil\WellKnownBundle\Tests\Unit\Domain;

use DateTimeImmutable;
use Phil\WellKnownBundle\Domain\Exception\InvalidValue;
use Phil\WellKnownBundle\Domain\ValueObject\SecurityTxt;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(SecurityTxt::class)]
#[CoversClass(InvalidValue::class)]
final class SecurityTxtTest extends TestCase
{
    public function testEveryField(): void
    {
        $file = new SecurityTxt(
            ['mailto:security@example.com', 'tel:+1-201-555-0123', 'HTTPS://example.com/report'],
            new DateTimeImmutable('2027-06-30T20:00:00-04:00'),
            ['https://example.com/key.txt', 'dns:5d2d3ceb7abe552344276d47d36a8175b7aeb250._openpgpkey.example.com?type=OPENPGPKEY', 'openpgp4fpr:5f2de5521c63a801ab59ccb603d49de44b29100f'],
            ['https://example.com/thanks'],
            ['en', 'fr-CA', 'zh-Hant-TW'],
            ['https://example.com/.well-known/security.txt'],
            ['https://example.com/policy'],
            ['https://example.com/jobs'],
        );

        self::assertSame(
            "Contact: mailto:security@example.com\nContact: tel:+1-201-555-0123\nContact: HTTPS://example.com/report\nExpires: 2027-07-01T00:00:00Z\n"
            ."Encryption: https://example.com/key.txt\nEncryption: dns:5d2d3ceb7abe552344276d47d36a8175b7aeb250._openpgpkey.example.com?type=OPENPGPKEY\nEncryption: openpgp4fpr:5f2de5521c63a801ab59ccb603d49de44b29100f\n"
            ."Acknowledgments: https://example.com/thanks\nPreferred-Languages: en, fr-CA, zh-Hant-TW\nCanonical: https://example.com/.well-known/security.txt\n"
            ."Policy: https://example.com/policy\nHiring: https://example.com/jobs\n",
            $file->toString(),
        );
        self::assertSame(['en', 'fr-CA', 'zh-Hant-TW'], $file->preferredLanguages);
        self::assertSame(['https://example.com/thanks'], $file->acknowledgments);
        self::assertSame(['https://example.com/.well-known/security.txt'], $file->canonical);
        self::assertSame(['https://example.com/policy'], $file->policy);
        self::assertSame(['https://example.com/jobs'], $file->hiring);
        self::assertCount(3, $file->encryption);
        self::assertCount(3, $file->contact);
    }

    public function testTheRequiredFieldsAlone(): void
    {
        $file = new SecurityTxt(['mailto:a@example.com'], new DateTimeImmutable('2027-01-01T00:00:00Z'));

        self::assertSame("Contact: mailto:a@example.com\nExpires: 2027-01-01T00:00:00Z\n", $file->toString());
    }

    /**
     * @return iterable<string, array{array<string, list<string>>, string}>
     */
    public static function badFields(): iterable
    {
        yield 'no contact' => [['contact' => []], 'at least one Contact'];
        yield 'a bare address' => [['contact' => ['security@example.com']], 'Contact takes mailto:, tel:, https:// URIs without spaces, 2048 characters at most; "security@example.com" is not one.'];
        yield 'an http contact' => [['contact' => ['http://example.com']], 'Contact takes'];
        yield 'a scheme alone' => [['contact' => ['mailto:']], 'Contact takes'];
        yield 'a space' => [['contact' => ['https://example.com/a b']], 'Contact takes'];
        yield 'a new line' => [['contact' => ["https://example.com/\nExpires: 1999"]], 'Contact takes'];
        yield 'a delete' => [['contact' => ["https://example.com/\x7F"]], 'Contact takes'];
        yield 'a tab' => [['contact' => ["https://example.com/\t"]], 'Contact takes'];
        yield 'too long' => [['contact' => ['https://'.str_repeat('a', 2041)]], 'Contact takes'];
        yield 'an http key' => [['encryption' => ['http://example.com/key']], 'Encryption takes https://, dns:, openpgp4fpr: URIs'];
        yield 'a mailto acknowledgment' => [['acknowledgments' => ['mailto:a@example.com']], 'Acknowledgments takes https:// URIs'];
        yield 'an http canonical' => [['canonical' => ['http://example.com']], 'Canonical takes'];
        yield 'an http policy' => [['policy' => ['http://example.com']], 'Policy takes'];
        yield 'an http hiring' => [['hiring' => ['http://example.com']], 'Hiring takes'];
        yield 'a bad language' => [['languages' => ['en_US']], 'Preferred-Languages takes language tags such as "en" or "fr-CA"; "en_US" is not one.'];
        yield 'a language with a new line' => [['languages' => ["en\n"]], 'Preferred-Languages'];
        yield 'a language with a prefix' => [['languages' => ['1en']], 'Preferred-Languages'];
        yield 'a language with a suffix' => [['languages' => ['en-']], 'Preferred-Languages'];
    }

    /**
     * @param array<string, list<string>> $fields
     */
    #[DataProvider('badFields')]
    public function testABadFieldIsRefused(array $fields, string $message): void
    {
        $this->expectException(InvalidValue::class);
        $this->expectExceptionMessageIsOrContains($message);

        new SecurityTxt(
            $fields['contact'] ?? ['mailto:a@example.com'],
            new DateTimeImmutable('2027-01-01'),
            $fields['encryption'] ?? [],
            $fields['acknowledgments'] ?? [],
            $fields['languages'] ?? [],
            $fields['canonical'] ?? [],
            $fields['policy'] ?? [],
            $fields['hiring'] ?? [],
        );
    }

    public function testTheLongestValue(): void
    {
        $contact = 'https://'.str_repeat('a', 2040);

        self::assertSame([$contact], new SecurityTxt([$contact], new DateTimeImmutable())->contact);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function dates(): iterable
    {
        yield 'a day' => ['2027-06-30', '2027-06-30T00:00:00+00:00'];
        yield 'a time' => ['2027-06-30T12:30', '2027-06-30T12:30:00+00:00'];
        yield 'seconds and Z' => ['2027-06-30T12:30:15Z', '2027-06-30T12:30:15+00:00'];
        yield 'fractions' => ['2027-06-30T12:30:15.000Z', '2027-06-30T12:30:15+00:00'];
        yield 'an offset' => ['2027-06-30 23:00:00-05:00', '2027-06-30T23:00:00-05:00'];
    }

    #[DataProvider('dates')]
    public function testExpiresIsADate(string $date, string $expected): void
    {
        self::assertSame($expected, SecurityTxt::expiresAt($date)->format(\DATE_ATOM));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function badDates(): iterable
    {
        yield 'relative' => ['+1 year', 'Expires takes a date such as "2027-06-30T00:00:00Z"; "+1 year" is not one.'];
        yield 'words' => ['next monday', 'Expires takes a date'];
        yield 'a trailing word' => ['2027-06-30 tomorrow', 'Expires takes a date'];
        yield 'a leading word' => ['tomorrow 2027-06-30', 'Expires takes a date'];
        yield 'a new line' => ["2027-06-30\n", 'Expires takes a date'];
        yield 'no month 13' => ['2027-13-45', 'Expires names a day the calendar does not have: "2027-13-45".'];
        yield 'no February 30' => ['2027-02-30', 'Expires names a day the calendar does not have: "2027-02-30".'];
        yield 'no hour 25' => ['2027-01-01T25:00', 'the calendar does not have'];
    }

    #[DataProvider('badDates')]
    public function testABadDateIsRefused(string $date, string $message): void
    {
        $this->expectException(InvalidValue::class);
        $this->expectExceptionMessageIsOrContains($message);

        SecurityTxt::expiresAt($date);
    }
}
