<?php

declare(strict_types=1);

namespace Phil\LegalNotices\Tests\Unit;

use Phil\LegalNotices\DocumentType;
use Phil\LegalNotices\Exception\InvalidLegalNotice;
use Phil\LegalNotices\Field;
use Phil\LegalNotices\SiteIdentity;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(SiteIdentity::class)]
#[CoversClass(Field::class)]
#[CoversClass(DocumentType::class)]
final class SiteIdentityTest extends TestCase
{
    public function testTheValuesAreKeptTrimmed(): void
    {
        $identity = new SiteIdentity([
            'publisher_name' => '  Example Inc. ',
            'publisher_address' => "1 Main Street\r\nMontréal\rCanada",
            'contact_email' => 'privacy@example.com',
            'host_name' => '',
            'dpo_name' => null,
        ]);

        self::assertSame('Example Inc.', $identity->get(Field::PublisherName));
        self::assertSame("1 Main Street\nMontréal\nCanada", $identity->get(Field::PublisherAddress));
        self::assertNull($identity->get(Field::HostName));
        self::assertNull($identity->get(Field::DpoName));
        self::assertSame(['publisher_name' => 'Example Inc.', 'publisher_address' => "1 Main Street\nMontréal\nCanada", 'contact_email' => 'privacy@example.com'], $identity->values());
        self::assertSame([], new SiteIdentity()->values());
        self::assertSame(str_repeat('a', 1000), new SiteIdentity(['site_name' => str_repeat('a', 1000)])->get(Field::SiteName));
    }

    public function testTheFieldsADocumentLacks(): void
    {
        $identity = new SiteIdentity(['publisher_name' => 'Example', 'host_name' => 'Host', 'dpo_name' => 'Jo']);

        self::assertSame([Field::PublisherAddress, Field::PublicationDirector, Field::ContactEmail, Field::HostAddress, Field::HostContact], $identity->missing(DocumentType::LegalNotice));
        self::assertSame([Field::ContactEmail, Field::DpoContact], $identity->missing(DocumentType::PrivacyPolicy));
        self::assertSame([Field::PublisherName, Field::ContactEmail, Field::DpoName, Field::DpoContact], new SiteIdentity()->missing(DocumentType::PrivacyPolicy));
        self::assertSame(DocumentType::LegalNotice->requiredFields(), new SiteIdentity()->missing(DocumentType::LegalNotice));
        self::assertSame([Field::PublisherName, Field::PublisherAddress, Field::PublicationDirector, Field::ContactEmail, Field::HostName, Field::HostAddress, Field::HostContact], DocumentType::LegalNotice->requiredFields());
    }

    public function testOnlyTheAddressesHoldSeveralLines(): void
    {
        $multiline = array_values(array_filter(Field::cases(), static fn (Field $field): bool => $field->isMultiline()));

        self::assertSame([Field::PublisherAddress, Field::HostAddress], $multiline);
    }

    /**
     * @return iterable<string, array{array<array-key, mixed>, string, string}>
     */
    public static function badIdentities(): iterable
    {
        yield 'an unknown field' => [['owner' => 'x'], 'unknown_field', '"owner" is not a field of the identity.'];
        yield 'a numbered field' => [['x'], 'unknown_field', '"0" is not a field of the identity.'];
        yield 'not text' => [['site_name' => 3], 'invalid_field', 'The field "site_name" is text.'];
        yield 'too long' => [['site_name' => str_repeat('a', 1001)], 'invalid_field', 'The field "site_name" is UTF-8 text of 1000 bytes at most, without control characters, on one line.'];
        yield 'not UTF-8' => [['site_name' => "\xC3"], 'invalid_field', 'The field "site_name" is UTF-8 text of 1000 bytes at most, without control characters, on one line.'];
        yield 'two lines' => [['host_name' => "a\nb"], 'invalid_field', 'The field "host_name" is UTF-8 text of 1000 bytes at most, without control characters, on one line.'];
        yield 'a tab' => [['host_name' => "a\tb"], 'invalid_field', 'The field "host_name" is UTF-8 text of 1000 bytes at most, without control characters, on one line.'];
        yield 'a delete' => [['host_name' => "a\x7Fb"], 'invalid_field', 'The field "host_name" is UTF-8 text of 1000 bytes at most, without control characters, on one line.'];
        yield 'a control in an address' => [['host_address' => "a\x0Bb"], 'invalid_field', 'The field "host_address" is UTF-8 text of 1000 bytes at most, without control characters.'];
        yield 'a tab in an address' => [['host_address' => "a\tb"], 'invalid_field', 'The field "host_address" is UTF-8 text of 1000 bytes at most, without control characters.'];
        yield 'a null byte in an address' => [['host_address' => "a\x00b"], 'invalid_field', 'The field "host_address" is UTF-8 text of 1000 bytes at most, without control characters.'];
        yield 'a delete in an address' => [['host_address' => "a\x7Fb"], 'invalid_field', 'The field "host_address" is UTF-8 text of 1000 bytes at most, without control characters.'];
        yield 'an address of the publisher too long' => [['publisher_address' => str_repeat('a', 1001)], 'invalid_field', 'The field "publisher_address" is UTF-8 text of 1000 bytes at most, without control characters.'];
        yield 'not an e-mail address' => [['contact_email' => 'privacy'], 'invalid_email', 'The field "contact_email" is an e-mail address: "privacy" is not.'];
    }

    /**
     * @param array<array-key, mixed> $values
     */
    #[DataProvider('badIdentities')]
    public function testABadIdentityIsRefused(array $values, string $reason, string $message): void
    {
        try {
            new SiteIdentity($values);
            self::fail('The identity was accepted.');
        } catch (InvalidLegalNotice $invalidLegalNotice) {
            self::assertSame([$reason, $message], [$invalidLegalNotice->reason, $invalidLegalNotice->getMessage()]);
        }
    }

    public function testLongNamesAreCutInTheMessages(): void
    {
        try {
            new SiteIdentity([str_repeat('x', 90) => 'a']);
            self::fail('The identity was accepted.');
        } catch (InvalidLegalNotice $invalidLegalNotice) {
            self::assertSame('"'.str_repeat('x', 80).'" is not a field of the identity.', $invalidLegalNotice->getMessage());
        }

        $this->expectExceptionMessageIsOrContains('The field "contact_email" is an e-mail address: "'.str_repeat('x', 80).'" is not.');

        new SiteIdentity(['contact_email' => str_repeat('x', 90)]);
    }
}
