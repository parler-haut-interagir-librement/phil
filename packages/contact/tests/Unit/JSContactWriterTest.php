<?php

declare(strict_types=1);

namespace Phil\Contact\Tests\Unit;

use Phil\Address\Address;
use Phil\Contact\ContactCard;
use Phil\Contact\Email;
use Phil\Contact\Exception\InvalidContact;
use Phil\Contact\JSContactCard;
use Phil\Contact\JSContactWriter;
use Phil\Contact\NameOrder;
use Phil\Contact\PersonName;
use Phil\Contact\PhoneLine;
use Phil\Contact\PhoneRole;
use Phil\InstantMessenger\Handle;
use Phil\InstantMessenger\KnownMessenger;
use Phil\Phone\PhoneNumber;
use Phil\Url\KnownUrlType;
use Phil\Url\TypedUrl;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use stdClass;

#[CoversClass(JSContactWriter::class)]
#[CoversClass(JSContactCard::class)]
final class JSContactWriterTest extends TestCase
{
    public function testAPersonIsWrittenAsJSContact(): void
    {
        $card = ContactCard::of(
            organization: 'France libre',
            title: 'Général',
            emails: [Email::of('charles@example.com')],
            phones: [
                new PhoneLine(PhoneRole::Voice, PhoneNumber::parse('+33 1 23 45 67 89')),
                new PhoneLine(PhoneRole::Mobile, PhoneNumber::parse('+1 514 555 0123 x4')),
                new PhoneLine(PhoneRole::Fax, PhoneNumber::parse('+15145550124')),
                new PhoneLine(PhoneRole::Text, PhoneNumber::parse('+15145550125')),
                new PhoneLine(PhoneRole::Pager, PhoneNumber::parse('+15145550126')),
            ],
            urls: [TypedUrl::of(KnownUrlType::Website, 'https://example.com')],
            handles: [Handle::of(KnownMessenger::Telegram, 'adalove'), Handle::of(KnownMessenger::Discord, 'ada')],
            structuredName: PersonName::of('Charles', 'Gaulle', 'de', ['André'], honorific: 'Gén.', generation: 'II', credential: 'PhD', culture: 'fr'),
        );
        $notes = new stdClass();
        $document = new JSContactCard('urn:uuid:7', $card, [Address::of('FR', ['addressLine1' => '4 rue de Solférino', 'addressLine2' => 'Bâtiment B', 'givenName' => 'Charles', 'locality' => 'Paris', 'dependentLocality' => 'Faubourg', 'administrativeArea' => 'IDF', 'postalCode' => '75007', 'sortingCode' => 'CEDEX 07'])], ['notes' => $notes, 'example.com:x' => [1]]);

        self::assertSame([
            '@type' => 'Card',
            'version' => '1.0',
            'uid' => 'urn:uuid:7',
            'kind' => 'individual',
            'language' => 'fr',
            'name' => [
                '@type' => 'Name',
                'components' => [
                    ['kind' => 'title', 'value' => 'Gén.'],
                    ['kind' => 'given', 'value' => 'Charles'],
                    ['kind' => 'given2', 'value' => 'André'],
                    ['kind' => 'surname', 'value' => 'de Gaulle'],
                    ['kind' => 'generation', 'value' => 'II'],
                    ['kind' => 'credential', 'value' => 'PhD'],
                ],
                'isOrdered' => true,
                'defaultSeparator' => ' ',
                'full' => 'Charles André de Gaulle II',
                'sortAs' => ['surname' => 'Gaulle'],
            ],
            'organizations' => ['o1' => ['@type' => 'Organization', 'name' => 'France libre']],
            'titles' => ['t1' => ['@type' => 'Title', 'name' => 'Général']],
            'emails' => ['e1' => ['@type' => 'EmailAddress', 'address' => 'charles@example.com']],
            'phones' => [
                'p1' => ['@type' => 'Phone', 'number' => 'tel:+33-1-23-45-67-89', 'features' => ['voice' => true]],
                'p2' => ['@type' => 'Phone', 'number' => 'tel:+1-514-555-0123;ext=4', 'features' => ['mobile' => true]],
                'p3' => ['@type' => 'Phone', 'number' => 'tel:+1-514-555-0124', 'features' => ['fax' => true]],
                'p4' => ['@type' => 'Phone', 'number' => 'tel:+1-514-555-0125', 'features' => ['text' => true]],
                'p5' => ['@type' => 'Phone', 'number' => 'tel:+1-514-555-0126', 'features' => ['pager' => true]],
            ],
            'onlineServices' => [
                's1' => ['@type' => 'OnlineService', 'service' => 'Telegram', 'user' => 'adalove', 'uri' => 'https://t.me/adalove'],
                's2' => ['@type' => 'OnlineService', 'service' => 'Discord', 'user' => 'ada'],
            ],
            'links' => ['l1' => ['@type' => 'Link', 'uri' => 'https://example.com/']],
            'addresses' => ['a1' => ['@type' => 'Address', 'components' => [
                ['kind' => 'name', 'value' => '4 rue de Solférino'],
                ['kind' => 'name', 'value' => 'Bâtiment B'],
                ['kind' => 'district', 'value' => 'Faubourg'],
                ['kind' => 'locality', 'value' => 'Paris'],
                ['kind' => 'region', 'value' => 'IDF'],
                ['kind' => 'postcode', 'value' => '75007'],
                ['kind' => 'postcode', 'value' => 'CEDEX 07'],
            ], 'countryCode' => 'FR']],
            'notes' => $notes,
            'example.com:x' => [1],
        ], JSContactWriter::toArray($document));
        self::assertStringEndsWith(',"notes":{},"example.com:x":[1]}', JSContactWriter::toJson($document));
        self::assertStringContainsString('"address":"charles@example.com"', JSContactWriter::toJson($document));
        self::assertStringContainsString('"value":"Gén."', JSContactWriter::toJson($document));
    }

    /**
     * @return iterable<string, array{ContactCard, array<string, mixed>}>
     */
    public static function cards(): iterable
    {
        yield 'an organisation' => [ContactCard::of(organization: 'Engines'), ['kind' => 'org', 'name' => ['@type' => 'Name', 'full' => 'Engines'], 'organizations' => ['o1' => ['@type' => 'Organization', 'name' => 'Engines']]]];
        yield 'a name in full' => [ContactCard::of('Ada Lovelace'), ['kind' => 'individual', 'name' => ['@type' => 'Name', 'full' => 'Ada Lovelace']]];
        yield 'nothing but a title' => [ContactCard::of(title: 'Countess'), ['kind' => 'individual', 'titles' => ['t1' => ['@type' => 'Title', 'name' => 'Countess']]]];
        yield 'a name written family first, in its characters' => [ContactCard::of(structuredName: PersonName::of('泽东', '毛', culture: 'zh')), ['kind' => 'individual', 'language' => 'zh', 'name' => ['@type' => 'Name', 'components' => [['kind' => 'surname', 'value' => '毛'], ['kind' => 'given', 'value' => '泽东']], 'isOrdered' => true, 'defaultSeparator' => '', 'full' => '毛泽东']]];
        yield 'a name written family first, with additional names and a second family name' => [ContactCard::of(structuredName: PersonName::of('Giáp', 'Võ', additional: ['Nguyên', 'Văn'], secondFamily: 'Lê', order: NameOrder::FamilyFirst)), ['kind' => 'individual', 'name' => ['@type' => 'Name', 'components' => [['kind' => 'surname', 'value' => 'Võ'], ['kind' => 'surname2', 'value' => 'Lê'], ['kind' => 'given2', 'value' => 'Nguyên'], ['kind' => 'given2', 'value' => 'Văn'], ['kind' => 'given', 'value' => 'Giáp']], 'isOrdered' => true, 'defaultSeparator' => ' ', 'full' => 'Võ Lê Nguyên Văn Giáp']]];
        yield 'a name with a full form of its own' => [ContactCard::of('Le Général', structuredName: PersonName::of('Charles', culture: 'fr')), ['kind' => 'individual', 'language' => 'fr', 'name' => ['@type' => 'Name', 'components' => [['kind' => 'given', 'value' => 'Charles']], 'isOrdered' => true, 'defaultSeparator' => ' ', 'full' => 'Le Général']]];
    }

    /**
     * @param array<string, mixed> $expected
     */
    #[DataProvider('cards')]
    public function testWhatACardHasIsWritten(ContactCard $card, array $expected): void
    {
        self::assertSame(['@type' => 'Card', 'version' => '1.0', 'uid' => 'u', ...$expected], JSContactWriter::toArray(new JSContactCard('u', $card)));
    }

    public function testTheLanguageOfTheNameWinsOverTheOneKept(): void
    {
        self::assertSame('de', JSContactWriter::toArray(new JSContactCard('u', ContactCard::of(structuredName: PersonName::of('Ada', culture: 'de')), unmapped: ['language' => 'en']))['language']);
        self::assertSame('en', JSContactWriter::toArray(new JSContactCard('u', ContactCard::of('Ada'), unmapped: ['language' => 'en']))['language']);
    }

    /**
     * @return iterable<string, array{string, array<string, mixed>, string}>
     */
    public static function refusals(): iterable
    {
        yield 'an empty uid' => [' ', [], 'The uid of a JSContact card is a text of 1 to 1024 bytes.'];
        yield 'a uid too long' => [str_repeat('u', 1025), [], 'The uid of a JSContact card is a text of 1 to 1024 bytes.'];
        yield 'a mapped property kept' => ['u', ['phones' => []], 'The property "phones" of a JSContact card is written from the card.'];
        yield 'the type kept' => ['u', ['@type' => 'Card'], 'The property "@type" of a JSContact card is written from the card.'];
    }

    /**
     * @param array<string, mixed> $unmapped
     */
    #[DataProvider('refusals')]
    public function testARefusedDocument(string $uid, array $unmapped, string $message): void
    {
        $this->expectException(InvalidContact::class);
        $this->expectExceptionMessageIsOrContains($message);

        new JSContactCard($uid, ContactCard::of('Ada'), unmapped: $unmapped);
    }

    public function testTheLimitsOfTheUid(): void
    {
        self::assertSame(1024, \strlen(new JSContactCard(str_repeat('u', 1024), ContactCard::of('Ada'))->uid));
        self::assertSame('application/jscontact+json', JSContactCard::MEDIA_TYPE);
    }

    public function testAnAddressOfThreeLinesIsWrittenOnThreeLines(): void
    {
        $address = Address::of('FR', ['addressLine1' => 'a', 'addressLine2' => 'b', 'addressLine3' => 'c', 'locality' => 'Paris']);
        $card = JSContactWriter::toArray(new JSContactCard('u', ContactCard::of('Ada'), [$address]));

        self::assertSame(['a1' => ['@type' => 'Address', 'components' => [['kind' => 'name', 'value' => 'a'], ['kind' => 'name', 'value' => 'b'], ['kind' => 'name', 'value' => 'c'], ['kind' => 'locality', 'value' => 'Paris']], 'countryCode' => 'FR']], $card['addresses']);
    }

    public function testTheJsonKeepsItsSlashesAndItsCharacters(): void
    {
        $json = JSContactWriter::toJson(new JSContactCard('u', ContactCard::of('Ada', urls: [TypedUrl::of(KnownUrlType::Website, 'https://example.com')], structuredName: PersonName::of('Zoë'))));

        self::assertStringContainsString('"uri":"https://example.com/"', $json);
        self::assertStringContainsString('"value":"Zoë"', $json);
    }
}
