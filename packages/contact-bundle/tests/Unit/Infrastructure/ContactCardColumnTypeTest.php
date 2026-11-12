<?php

declare(strict_types=1);

namespace Phil\ContactBundle\Tests\Unit\Infrastructure;

use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Types\Exception\InvalidType;
use Doctrine\DBAL\Types\Exception\ValueNotConvertible;
use JsonException;
use Phil\Contact\ContactCard;
use Phil\Contact\Email;
use Phil\Contact\Exception\UnreadableContact;
use Phil\Contact\PersonName;
use Phil\ContactBundle\Infrastructure\Doctrine\ContactCardColumnType;
use Phil\Url\KnownUrlType;
use Phil\Url\TypedUrl;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ContactCardColumnType::class)]
final class ContactCardColumnTypeTest extends TestCase
{
    public function testACardIsJson(): void
    {
        $type = new ContactCardColumnType();
        $platform = new PostgreSQLPlatform();
        $json = '{"name":"Zoé","organization":null,"title":null,"emails":["zoe@example.com"],"phones":[],"urls":[{"type":"Phil\\\\Url\\\\KnownUrlType::Website","url":"https://example.com/"}],"handles":[],"structuredName":null}';

        self::assertSame('phil_contact', ContactCardColumnType::NAME);
        self::assertSame('JSON', $type->getSQLDeclaration([], $platform));
        self::assertSame($json, $type->convertToDatabaseValue(ContactCard::of('Zoé', emails: [Email::of('zoe@example.com')], urls: [TypedUrl::of(KnownUrlType::Website, 'https://example.com')]), $platform));
        self::assertNull($type->convertToDatabaseValue(null, $platform));
        self::assertSame('Zoé', $type->convertToPHPValue('{"name":"Zoé"}', $platform)?->name);
        self::assertNull($type->convertToPHPValue(null, $platform));
        self::assertSame('Ada', $type->convertToPHPValue('{"name":"Ada","x":[[[[[[1]]]]]]}', $platform)?->name);
    }

    public function testAStructuredNameIsKeptAndACardStoredWithoutOneIsReadBack(): void
    {
        $type = new ContactCardColumnType();
        $platform = new PostgreSQLPlatform();
        $card = ContactCard::of(structuredName: PersonName::of('Charles', 'Gaulle', 'de', ['André'], culture: 'fr'));
        $stored = $type->convertToDatabaseValue($card, $platform);

        self::assertStringContainsString('"structuredName":{"given":"Charles","family":"Gaulle","particle":"de","additional":["André"],', (string) $stored);
        self::assertSame('Gaulle, Charles André de', $type->convertToPHPValue($stored, $platform)?->structuredName?->sortKey());
        self::assertNull($type->convertToPHPValue('{"name":"Ada","emails":[]}', $platform)?->structuredName);
    }

    public function testOtherValuesAreRefused(): void
    {
        $type = new ContactCardColumnType();
        $platform = new PostgreSQLPlatform();

        try {
            $type->convertToDatabaseValue(['name' => 'Ada'], $platform);
            self::fail('An array was written.');
        } catch (InvalidType $invalidType) {
            self::assertStringContainsString('null, Phil\\Contact\\ContactCard', $invalidType->getMessage());
        }

        try {
            $type->convertToPHPValue(42, $platform);
            self::fail('An integer was read.');
        } catch (ValueNotConvertible) {
        }

        try {
            $type->convertToPHPValue('{"name":', $platform);
            self::fail('Broken JSON was read.');
        } catch (ValueNotConvertible $valueNotConvertible) {
            self::assertInstanceOf(JsonException::class, $valueNotConvertible->getPrevious());
        }

        try {
            $type->convertToPHPValue('{"x":[[[[[[[1]]]]]]]}', $platform);
            self::fail('JSON too deep was read.');
        } catch (ValueNotConvertible $valueNotConvertible) {
            self::assertInstanceOf(JsonException::class, $valueNotConvertible->getPrevious());
        }

        try {
            $type->convertToPHPValue('"Ada"', $platform);
            self::fail('A text was read as a card.');
        } catch (ValueNotConvertible $valueNotConvertible) {
            self::assertStringContainsString('The stored contact card cannot be read: it is not an array.', $valueNotConvertible->getMessage());
            self::assertInstanceOf(UnreadableContact::class, $valueNotConvertible->getPrevious());
        }
    }
}
