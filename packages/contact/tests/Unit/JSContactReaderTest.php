<?php

declare(strict_types=1);

namespace Phil\Contact\Tests\Unit;

use Phil\Address\Address;
use Phil\Contact\ContactCard;
use Phil\Contact\Email;
use Phil\Contact\Exception\InvalidContact;
use Phil\Contact\Exception\UnreadableJSContact;
use Phil\Contact\JSContact\AddressReading;
use Phil\Contact\JSContact\NameReading;
use Phil\Contact\JSContact\Reading;
use Phil\Contact\JSContactCard;
use Phil\Contact\JSContactNotice;
use Phil\Contact\JSContactReader;
use Phil\Contact\JSContactWriter;
use Phil\Contact\NameOrder;
use Phil\Contact\PersonName;
use Phil\Contact\PhoneLine;
use Phil\Contact\PhoneRole;
use Phil\Contact\Tests\Unit\Fixture\ChatMessenger;
use Phil\Enums\Collection\EnumSet;
use Phil\InstantMessenger\Handle;
use Phil\InstantMessenger\KnownMessenger;
use Phil\Phone\PhoneNumber;
use Phil\Url\KnownUrlType;
use Phil\Url\TypedUrl;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use stdClass;

#[CoversClass(JSContactReader::class)]
#[CoversClass(Reading::class)]
#[CoversClass(NameReading::class)]
#[CoversClass(AddressReading::class)]
#[CoversClass(JSContactNotice::class)]
#[CoversClass(UnreadableJSContact::class)]
final class JSContactReaderTest extends TestCase
{
    public function testWhatTheWriterWritesIsReadBack(): void
    {
        $document = new JSContactCard(
            'urn:uuid:7',
            ContactCard::of(
                organization: 'France libre',
                title: 'Général',
                emails: [Email::of('charles@example.com')],
                phones: [new PhoneLine(PhoneRole::Voice, PhoneNumber::parse('+33 1 23 45 67 89')), new PhoneLine(PhoneRole::Mobile, PhoneNumber::parse('+1 514 555 0123 x4')), new PhoneLine(PhoneRole::Fax, PhoneNumber::parse('+15145550124')), new PhoneLine(PhoneRole::Text, PhoneNumber::parse('+15145550125')), new PhoneLine(PhoneRole::Pager, PhoneNumber::parse('+15145550126'))],
                urls: [TypedUrl::of(KnownUrlType::Website, 'https://example.com'), TypedUrl::of(KnownUrlType::Facebook, 'https://facebook.com/ada')],
                handles: [Handle::of(KnownMessenger::Telegram, 'adalove'), Handle::of(KnownMessenger::Discord, 'ada')],
                structuredName: PersonName::of('Charles', 'Gaulle', 'de', ['André', 'Joseph'], 'Lévesque', 'Gén.', 'II', 'PhD', 'fr'),
            ),
            [Address::of('FR', ['addressLine1' => '4 rue de Solférino', 'addressLine2' => 'Bâtiment B', 'addressLine3' => 'Porte 2', 'dependentLocality' => 'Faubourg', 'locality' => 'Paris', 'administrativeArea' => 'IDF', 'postalCode' => '75007', 'sortingCode' => 'CEDEX 07'])],
            ['notes' => new stdClass(), 'example.com:x' => [1, ['a' => null]]],
        );
        $json = JSContactWriter::toJson($document);
        $read = new JSContactReader()->read($json);

        self::assertSame([], $read->notices);
        self::assertSame($json, JSContactWriter::toJson($read));
        self::assertTrue($document->card->structuredName?->equals($read->card->structuredName ?? PersonName::of('Nobody')));
        self::assertSame(['urn:uuid:7', 'Charles André Joseph de Gaulle Lévesque II', 'France libre', 'Général'], [$read->uid, $read->card->name, $read->card->organization, $read->card->title]);
        self::assertTrue($document->addresses[0]->equals($read->addresses[0]));
        self::assertEquals(['notes' => new stdClass(), 'example.com:x' => [1, (object) ['a' => null]]], $read->unmapped);
    }

    public function testAnOrganisationAndANameFamilyFirstAreReadBack(): void
    {
        foreach ([
            ContactCard::of(organization: 'Engines'),
            ContactCard::of(structuredName: PersonName::of('泽东', '毛', culture: 'zh')),
            ContactCard::of(structuredName: PersonName::of('Ada', 'Lovelace', additional: ['Augusta'], order: NameOrder::FamilyFirst)),
            ContactCard::of(structuredName: PersonName::of('Jean', 'Ormesson', "d'", culture: 'fr')),
            ContactCard::of(structuredName: PersonName::of('Vincent', 'Gogh', 'van', culture: 'nl')),
        ] as $card) {
            $json = JSContactWriter::toJson(new JSContactCard('u', $card));
            $read = new JSContactReader()->read($json);

            self::assertSame([[], $json], [$read->notices, JSContactWriter::toJson($read)], $json);
        }
    }

    /**
     * @return iterable<string, array{array<string, mixed>, ?array<string, mixed>, array<string, string>}>
     */
    public static function names(): iterable
    {
        $name = static fn (array $components, array $more = []): array => ['name' => ['components' => array_map(static fn (array $component): array => ['kind' => $component[0], 'value' => $component[1]], $components), ...$more]];

        yield 'a particle before the sort form' => [$name([['given', 'Vincent'], ['surname', 'van Gogh']], ['sortAs' => ['surname' => 'Gogh']]) + ['language' => 'nl'], ['given' => 'Vincent', 'particle' => 'van', 'family' => 'Gogh', 'culture' => 'nl'], []];
        yield 'an elided particle' => [$name([['surname', 'd’Ormesson']], ['sortAs' => ['surname' => 'Ormesson']]) + ['language' => 'fr'], ['particle' => 'd’', 'family' => 'Ormesson', 'culture' => 'fr'], []];
        yield 'a sort form that is no end of the surname' => [$name([['surname', 'Smithson']], ['sortAs' => ['surname' => 'son']]), ['family' => 'Smithson'], ['/name/sortAs/surname' => 'the name is sorted as its culture sorts it']];
        yield 'a sort form the culture does not use' => [$name([['given', 'Martin'], ['surname', 'Van Buren']], ['sortAs' => ['surname' => 'Buren']]), ['given' => 'Martin', 'particle' => 'Van', 'family' => 'Buren'], ['/name/sortAs/surname' => 'the name is sorted as its culture sorts it']];
        yield 'a sort form equal to the surname' => [$name([['surname', 'Lovelace']], ['sortAs' => ['surname' => 'Lovelace', 'given' => 'Ada']]), ['family' => 'Lovelace'], ['/name/sortAs/given' => 'the card does not keep this property']];
        yield 'every part' => [$name([['title', 'Dr.'], ['given', 'Ada'], ['given2', 'Augusta'], ['given2', 'Byron'], ['surname', 'Lovelace'], ['surname2', 'King'], ['generation', 'II'], ['credential', 'PhD']]), ['honorific' => 'Dr.', 'given' => 'Ada', 'additional' => ['Augusta', 'Byron'], 'family' => 'Lovelace', 'secondFamily' => 'King', 'generation' => 'II', 'credential' => 'PhD'], []];
        yield 'a part twice, a separator, an unknown kind and a property of a component' => [['name' => ['components' => [['kind' => 'given', 'value' => 'Ada'], ['kind' => 'separator', 'value' => ', '], ['kind' => 'given', 'value' => 'Augusta'], ['kind' => 'nickname', 'value' => 'Ad'], ['kind' => 'surname', 'value' => 'Lovelace', 'phonetic' => 'lʌvleɪs']]]], ['given' => 'Ada', 'family' => 'Lovelace'], [
            '/name/components/1' => 'separators follow the convention of the name',
            '/name/components/2' => 'the name keeps one given',
            '/name/components/3' => '"nickname" is no kind of name component the card keeps',
            '/name/components/4/phonetic' => 'the card does not keep this property',
        ]];
        yield 'more additional names than a name keeps' => [$name([['given', 'A'], ...array_fill(0, 11, ['given2', 'B'])]), ['given' => 'A', 'additional' => array_fill(0, 10, 'B')], ['/name/components/11' => 'a name keeps 10 additional names']];
        yield 'family first in a culture written given first' => [$name([['surname', 'Lovelace'], ['given', 'Ada']], ['isOrdered' => true]), ['given' => 'Ada', 'family' => 'Lovelace', 'order' => 'family-first'], []];
        yield 'family first in a culture written so' => [$name([['surname', 'Bartók'], ['given', 'Béla']], ['isOrdered' => true]) + ['language' => 'hu'], ['given' => 'Béla', 'family' => 'Bartók', 'culture' => 'hu'], []];
        yield 'given first in a culture written family first' => [$name([['given', 'Béla'], ['surname', 'Bartók']], ['isOrdered' => true]) + ['language' => 'hu'], ['given' => 'Béla', 'family' => 'Bartók', 'culture' => 'hu', 'order' => 'given-first'], []];
        yield 'family first, unordered' => [$name([['surname', 'Lovelace'], ['given', 'Ada']], ['isOrdered' => false]), ['given' => 'Ada', 'family' => 'Lovelace'], []];
        yield 'a separator the culture does not use' => [$name([['given', 'Ada'], ['surname', 'Lovelace']], ['defaultSeparator' => '·']), ['given' => 'Ada', 'family' => 'Lovelace'], ['/name/defaultSeparator' => 'the name is separated as its culture writes it']];
        yield 'the separator of the culture' => [$name([['given', '泽东'], ['surname', '毛']], ['defaultSeparator' => '']) + ['language' => 'zh'], ['given' => '泽东', 'family' => '毛', 'culture' => 'zh'], []];
        yield 'a language that is no tag' => [$name([['given', 'Ada']]) + ['language' => 'english'], ['given' => 'Ada'], ['/language' => '"english" is not a language tag.']];
        yield 'components that make no name' => [$name([['title', 'Dr.']]) + ['language' => 'fr'], null, ['/name/components' => 'A person name has a given name or a family name.']];
        yield 'components that make no name without a language' => [$name([['credential', 'PhD']]), null, ['/name/components' => 'A person name has a given name or a family name.']];
        yield 'a property of the name the card does not keep' => [$name([['given', 'Ada']], ['phoneticSystem' => 'ipa', '@type' => 'Name']), ['given' => 'Ada'], ['/name/phoneticSystem' => 'the card does not keep this property']];
        yield 'a full name only, with hints' => [['name' => ['full' => 'Ada Lovelace', 'isOrdered' => true, 'defaultSeparator' => ' ', 'sortAs' => ['surname' => 'Lovelace']]], null, [
            '/name/isOrdered' => 'a name without components is written as it is',
            '/name/defaultSeparator' => 'a name without components is written as it is',
            '/name/sortAs/surname' => 'the card does not keep this property',
        ]];
        yield 'components of an organisation' => [$name([['given', 'Ada']], ['full' => 'Engines']) + ['kind' => 'org'], null, ['/name/components' => 'the card of an organisation names no person']];
    }

    /**
     * @param array<string, mixed>  $card
     * @param ?array<string, mixed> $parts
     * @param array<string, string> $notices
     */
    #[DataProvider('names')]
    public function testAName(array $card, ?array $parts, array $notices): void
    {
        $read = $this->read($card);
        $name = $read->card->structuredName;

        self::assertSame($notices, $this->notices($read));

        if (null === $parts) {
            self::assertNull($name);

            return;
        }

        self::assertNotNull($name);
        self::assertSame(
            [$parts['given'] ?? null, $parts['family'] ?? null, $parts['particle'] ?? null, $parts['additional'] ?? [], $parts['secondFamily'] ?? null, $parts['honorific'] ?? null, $parts['generation'] ?? null, $parts['credential'] ?? null, $parts['culture'] ?? null, $parts['order'] ?? null],
            [$name->given, $name->family, $name->particle, $name->additional, $name->secondFamily, $name->honorific, $name->generation, $name->credential, $name->culture, $name->order?->value],
        );
        self::assertSame($name->display(), $read->card->name);
    }

    public function testTheLanguageIsKeptWhenNoNameTakesIt(): void
    {
        self::assertSame(['language' => 'fr'], $this->read(['language' => 'fr', 'name' => ['full' => 'Ada']])->unmapped);
        self::assertSame(['language' => 'english'], $this->read(['language' => 'english', 'name' => ['components' => [['kind' => 'given', 'value' => 'Ada']]]])->unmapped);
        self::assertSame([], $this->read(['language' => 'fr', 'name' => ['components' => [['kind' => 'given', 'value' => 'Ada']]]])->unmapped);
    }

    /**
     * @return iterable<string, array{array<string, mixed>, ?string, ?string, array<string, string>}>
     */
    public static function kinds(): iterable
    {
        yield 'an organisation named in full' => [['kind' => 'org', 'name' => ['full' => 'Engines']], null, 'Engines', []];
        yield 'an organisation named twice the same' => [['kind' => 'org', 'name' => ['full' => 'Engines'], 'organizations' => ['o1' => ['name' => 'Engines']]], null, 'Engines', []];
        yield 'an organisation named twice otherwise' => [['kind' => 'org', 'name' => ['full' => 'Engines Inc.'], 'organizations' => ['o1' => ['name' => 'Engines']]], null, 'Engines', ['/name/full' => 'the card of an organisation keeps the name of its organisation']];
        yield 'an organisation of its organizations only' => [['kind' => 'org', 'organizations' => ['o1' => ['name' => 'Engines']]], null, 'Engines', []];
        yield 'a person of an organisation' => [['name' => ['full' => 'Ada'], 'organizations' => ['o1' => ['name' => 'Engines', 'units' => [['name' => 'R&D']]], 'o2' => ['@type' => 'Organization', 'name' => 'Looms']]], 'Ada', 'Engines', [
            '/organizations/o1/units' => 'the card does not keep this property',
            '/organizations/o2' => 'the card keeps the first of its organizations',
        ]];
        yield 'a group' => [['kind' => 'group', 'name' => ['full' => 'Friends']], 'Friends', null, ['/kind' => 'a card of kind "group" is read as the card of a person']];
        yield 'nothing' => [[], null, null, []];
    }

    /**
     * @param array<string, mixed>  $card
     * @param array<string, string> $notices
     */
    #[DataProvider('kinds')]
    public function testTheKindOfACard(array $card, ?string $name, ?string $organization, array $notices): void
    {
        $read = $this->read($card);

        self::assertSame([$name, $organization, $notices], [$read->card->name, $read->card->organization, $this->notices($read)]);
    }

    public function testTitles(): void
    {
        $read = $this->read(['titles' => ['t1' => ['name' => 'Chair', 'kind' => 'role'], 't2' => ['name' => 'Countess', 'kind' => 'title', 'organizationId' => 'o1'], 't3' => ['name' => 'Lady']]]);

        self::assertSame('Countess', $read->card->title);
        self::assertSame([
            '/titles/t1' => 'a role is not the title of the card',
            '/titles/t2/organizationId' => 'the card does not keep this property',
            '/titles/t3' => 'the card keeps the first of its titles',
        ], $this->notices($read));
        self::assertSame('Countess', $this->read(['titles' => ['t1' => ['name' => 'Countess']]])->card->title);
    }

    public function testEmails(): void
    {
        $read = $this->read(['emails' => ['e1' => ['address' => 'ada@example.com', 'contexts' => ['work' => true]], 'e2' => ['address' => 'ada']]]);

        self::assertSame(['ada@example.com'], array_map(strval(...), $read->card->emails));
        self::assertSame(['/emails/e1/contexts' => 'the card does not keep this property', '/emails/e2/address' => '"ada" is not an email address.'], $this->notices($read));
    }

    /**
     * @return iterable<string, array{array<string, mixed>, ?PhoneRole, array<string, string>}>
     */
    public static function phones(): iterable
    {
        yield 'no feature' => [['number' => 'tel:+1-514-555-0123'], PhoneRole::Voice, []];
        yield 'a mobile that calls and texts' => [['number' => '+15145550123', 'features' => ['voice' => true, 'text' => true, 'mobile' => true]], PhoneRole::Mobile, []];
        yield 'a fax that calls' => [['number' => '+15145550123', 'features' => ['voice' => true, 'fax' => true]], PhoneRole::Fax, ['/phones/p/features/voice' => 'the line keeps its role, fax']];
        yield 'a pager' => [['number' => '+15145550123', 'features' => ['pager' => true, 'text' => false]], PhoneRole::Pager, []];
        yield 'texts only' => [['number' => '+15145550123', 'features' => ['text' => true]], PhoneRole::Text, []];
        yield 'a voice line with video' => [['number' => '+15145550123', 'features' => ['voice' => true, 'video' => true, 'main-number' => true]], PhoneRole::Voice, ['/phones/p/features/video' => 'the line keeps its role, voice', '/phones/p/features/main-number' => 'the line keeps its role, voice']];
        yield 'a mobile with a textphone' => [['number' => '+15145550123', 'features' => ['mobile' => true, 'textphone' => true, 'a/b~c' => true]], PhoneRole::Mobile, ['/phones/p/features/textphone' => 'the line keeps its role, mobile', '/phones/p/features/a~1b~0c' => 'the line keeps its role, mobile']];
        yield 'a number no plan reads' => [['number' => 'call me', 'label' => 'home'], null, ['/phones/p/label' => 'the card does not keep this property', '/phones/p/number' => '"call me" is not a phone number.']];
    }

    /**
     * @param array<string, mixed>  $phone
     * @param array<string, string> $notices
     */
    #[DataProvider('phones')]
    public function testPhones(array $phone, ?PhoneRole $role, array $notices): void
    {
        $read = $this->read(['phones' => ['p' => $phone]]);

        self::assertSame([$role, $notices], [$read->card->phones[0]->role ?? null, $this->notices($read)]);
    }

    public function testANationalNumberIsReadInTheRegion(): void
    {
        $json = $this->json(['phones' => ['p' => ['number' => '(514) 555-0123']]]);

        self::assertSame('+15145550123', (string) new JSContactReader(region: 'CA')->read($json)->card->phones[0]->number);
        self::assertSame([], new JSContactReader()->read($json)->card->phones);
    }

    /**
     * @return iterable<string, array{array<string, mixed>, list<string>, list<string>, array<string, string>}>
     */
    public static function services(): iterable
    {
        yield 'a service named in capitals' => [['service' => 'TELEGRAM', 'user' => '@AdaLove'], ['Telegram:adalove'], [], []];
        yield 'a service named by its value' => [['service' => 'whatsapp', 'uri' => 'https://wa.me/15145550123'], ['WhatsApp:+15145550123'], [], []];
        yield 'a service the card does not know, with its URI' => [['service' => 'Mastodon', 'user' => '@ada@example.social', 'uri' => 'https://example.social/@ada'], [], ['Mastodon:https://example.social/@ada'], ['/onlineServices/s/service' => '"Mastodon" is kept as a web address', '/onlineServices/s/user' => '"@ada@example.social" is kept as a web address']];
        yield 'a URI alone' => [['uri' => 'https://facebook.com/ada'], [], ['Facebook:https://facebook.com/ada'], []];
        yield 'a service the card does not know, without URI' => [['service' => 'Gopher', 'user' => 'ada'], [], [], ['/onlineServices/s' => 'it is no accepted messaging service and has no URI']];
        yield 'a known service with nothing' => [['service' => 'Telegram'], [], [], ['/onlineServices/s' => 'it is no accepted messaging service and has no URI']];
        yield 'a handle its service refuses' => [['service' => 'Telegram', 'user' => '!'], [], [], ['/onlineServices/s' => '"!" is not a Telegram handle.']];
        yield 'a URI that is none' => [['uri' => 'nope', 'vCardType' => 'X-AIM'], [], [], ['/onlineServices/s/vCardType' => 'the card does not keep this property', '/onlineServices/s' => '"nope" is not an http or https address.']];
    }

    /**
     * @param array<string, mixed>  $service
     * @param list<string>          $handles
     * @param list<string>          $urls
     * @param array<string, string> $notices
     */
    #[DataProvider('services')]
    public function testOnlineServices(array $service, array $handles, array $urls, array $notices): void
    {
        $read = $this->read(['onlineServices' => ['s' => $service]]);

        self::assertSame($handles, array_map(static fn (Handle $handle): string => $handle->type->name.':'.$handle->value, $read->card->handles));
        self::assertSame($urls, array_map(static fn (TypedUrl $url): string => $url->type->name.':'.$url->url->toString(), $read->card->urls));
        self::assertSame($notices, $this->notices($read));
    }

    public function testTheAcceptedServicesAndKinds(): void
    {
        $reader = new JSContactReader(messengers: [EnumSet::of(KnownMessenger::class, KnownMessenger::Signal)], urlTypes: [EnumSet::of(KnownUrlType::class, KnownUrlType::Facebook)]);
        $read = $reader->read($this->json(['onlineServices' => ['s' => ['service' => 'Telegram', 'user' => 'adalove', 'uri' => 'https://facebook.com/ada'], 's2' => ['service' => 'Signal', 'user' => '+15145550123']], 'links' => ['l' => ['uri' => 'https://example.com/']]]));

        self::assertSame(['Facebook'], array_map(static fn (TypedUrl $url): string => $url->type->name, $read->card->urls));
        self::assertSame(['Signal'], array_map(static fn (Handle $handle): string => $handle->type->name, $read->card->handles));
        self::assertArrayHasKey('/links/l/uri', $this->notices($read));
    }

    public function testLinks(): void
    {
        $read = $this->read(['links' => ['l1' => ['uri' => 'https://example.com', 'kind' => 'contact'], 'l2' => ['uri' => 'mailto:ada@example.com']]]);

        self::assertSame(['https://example.com/'], array_map(static fn (TypedUrl $url): string => $url->url->toString(), $read->card->urls));
        self::assertSame(['/links/l1/kind', '/links/l2/uri'], array_keys($this->notices($read)));
        self::assertSame('the card does not keep this property', $this->notices($read)['/links/l1/kind']);
        self::assertSame(['https://example.com/', 'https://facebook.com/ada'], array_map(static fn (TypedUrl $url): string => $url->url->toString(), $this->read(['onlineServices' => ['s' => ['uri' => 'https://facebook.com/ada']], 'links' => ['l' => ['uri' => 'https://example.com']]])->card->urls));
    }

    /**
     * @return iterable<string, array{list<array{string, string}>, ?array<string, string>, array<string, string>}>
     */
    public static function addresses(): iterable
    {
        yield 'a number before its street' => [[['number', '123'], ['name', 'Main St'], ['apartment', 'Apt 4'], ['locality', 'Montréal'], ['region', 'QC'], ['postcode', 'H2X 1Y4']], ['addressLine1' => '123 Main St Apt 4', 'locality' => 'Montréal', 'administrativeArea' => 'QC', 'postalCode' => 'H2X 1Y4'], []];
        yield 'a number after its street, and a second street' => [[['name', 'Hauptstraße'], ['number', '5'], ['name', 'Hinterhaus'], ['floor', '2'], ['district', 'Mitte'], ['subdistrict', 'Nord'], ['district', 'Ost']], ['addressLine1' => 'Hauptstraße 5', 'addressLine2' => 'Hinterhaus 2', 'dependentLocality' => 'Mitte'], ['/addresses/a/components/5' => 'the address does not keep this subdistrict component', '/addresses/a/components/6' => 'the address does not keep this district component']];
        yield 'a building before the street names' => [[['building', 'Tour B'], ['name', 'Rue A'], ['name', 'Rue B'], ['name', 'Rue C'], ['name', 'Rue D']], ['addressLine1' => 'Tour B Rue A', 'addressLine2' => 'Rue B', 'addressLine3' => 'Rue C'], ['/addresses/a/components' => 'an address keeps three lines']];
        yield 'two postal codes and a third' => [[['postcode', '75007'], ['postcode', 'CEDEX 07'], ['postcode', '1'], ['locality', 'Paris'], ['locality', 'Lyon'], ['country', 'France'], ['separator', ', ']], ['locality' => 'Paris', 'postalCode' => '75007', 'sortingCode' => 'CEDEX 07'], [
            '/addresses/a/components/2' => 'the address does not keep this postcode component',
            '/addresses/a/components/4' => 'the address does not keep this locality component',
            '/addresses/a/components/5' => 'the address does not keep this country component',
            '/addresses/a/components/6' => 'the address does not keep this separator component',
        ]];
        yield 'a street of a box' => [[['postOfficeBox', 'CP 12'], ['direction', 'N'], ['block', '3'], ['room', '4'], ['landmark', 'Gare']], ['addressLine1' => 'CP 12 N 3 4 Gare'], []];
    }

    /**
     * @param list<array{string, string}> $components
     * @param ?array<string, string>      $fields
     * @param array<string, string>       $notices
     */
    #[DataProvider('addresses')]
    public function testAddresses(array $components, ?array $fields, array $notices): void
    {
        $read = $this->read(['addresses' => ['a' => ['countryCode' => 'FR', 'components' => array_map(static fn (array $component): array => ['kind' => $component[0], 'value' => $component[1]], $components)]]]);

        self::assertSame([$fields, $notices], [$read->addresses[0]->fields(), $this->notices($read)]);
        self::assertSame('FR', $read->addresses[0]->country);
    }

    public function testAnAddressThatIsNotKept(): void
    {
        $read = $this->read(['addresses' => [
            'a1' => ['full' => '4 rue de Solférino, Paris', 'coordinates' => 'geo:0,0'],
            'a2' => ['countryCode' => 'ZZ'],
            'a3' => ['countryCode' => 'CA', 'components' => [['kind' => 'name', 'value' => str_repeat('a', 256)]]],
            'a4' => ['countryCode' => 'ca'],
        ]]);

        self::assertSame(['CA'], array_map(static fn (Address $address): string => $address->country, $read->addresses));
        self::assertSame(['/addresses/a1/full', '/addresses/a1/coordinates', '/addresses/a1', '/addresses/a2', '/addresses/a3'], array_keys($this->notices($read)));
        self::assertSame('an address without its countryCode is not kept', $this->notices($read)['/addresses/a1']);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function unreadable(): iterable
    {
        $card = static fn (array $more): string => (string) json_encode(['@type' => 'Card', 'version' => '1.0', 'uid' => 'u', ...$more]);

        yield 'no JSON' => ['{', 'it is no JSON of 16 levels or fewer (Syntax error'];
        yield 'too deep' => [str_repeat('[', 17).str_repeat(']', 17), 'it is no JSON of 16 levels or fewer (Maximum stack depth exceeded'];
        yield 'no object' => ['[]', 'the card is not an object'];
        yield 'no Card' => [(string) json_encode(['@type' => 'Group', 'version' => '1.0', 'uid' => 'u']), 'it is no Card of version 1.0'];
        yield 'no type' => [(string) json_encode(['version' => '1.0', 'uid' => 'u']), 'it is no Card of version 1.0'];
        yield 'another version' => [(string) json_encode(['@type' => 'Card', 'version' => '2.0', 'uid' => 'u']), 'it is no Card of version 1.0'];
        yield 'no version' => [(string) json_encode(['@type' => 'Card', 'uid' => 'u']), 'it is no Card of version 1.0'];
        yield 'no uid' => [(string) json_encode(['@type' => 'Card', 'version' => '1.0']), 'has no uid'];
        yield 'a uid that is no text' => [$card(['uid' => 7]), '/uid is not a text'];
        yield 'an empty uid' => [$card(['uid' => '']), 'The uid of a JSContact card is a text of 1 to 1024 bytes.'];
        yield 'a kind that is no text' => [$card(['kind' => true]), '/kind is not a text'];
        yield 'a map that is no object' => [$card(['emails' => 'ada@example.com']), '/emails is not an object'];
        yield 'a map that is a list' => [$card(['emails' => [['address' => 'ada@example.com']]]), '/emails is not an object'];
        yield 'a key that is no Id' => [$card(['emails' => ['e 1' => ['address' => 'ada@example.com']]]), '/emails holds a key that is no Id'];
        yield 'a key too long' => [$card(['emails' => [str_repeat('e', 256) => ['address' => 'ada@example.com']]]), '/emails holds a key that is no Id'];
        yield 'an entry that is no object' => [$card(['emails' => ['e1' => 'ada@example.com']]), '/emails/e1 is not an object'];
        yield 'an entry of another type' => [$card(['emails' => ['e1' => ['@type' => 'Phone', 'address' => 'ada@example.com']]]), '/emails/e1 is not of @type EmailAddress'];
        yield 'an email without address' => [$card(['emails' => ['e1' => []]]), '/emails/e1 is not an object'];
        yield 'an email without its address' => [$card(['emails' => ['e1' => ['pref' => 1]]]), '/emails/e1 has no address'];
        yield 'a phone without number' => [$card(['phones' => ['p1' => ['features' => ['voice' => true]]]]), '/phones/p1 has no number'];
        yield 'features that are no object' => [$card(['phones' => ['p1' => ['number' => '+15145550123', 'features' => ['voice']]]]), '/phones/p1/features is not an object'];
        yield 'a feature that is no boolean' => [$card(['phones' => ['p1' => ['number' => '+15145550123', 'features' => ['voice' => 1]]]]), '/phones/p1/features holds a value that is not a boolean'];
        yield 'a link without URI' => [$card(['links' => ['l1' => ['kind' => 'contact']]]), '/links/l1 has no uri'];
        yield 'a title without name' => [$card(['titles' => ['t1' => ['kind' => 'title']]]), '/titles/t1 has no name'];
        yield 'a name that is no object' => [$card(['name' => 'Ada']), '/name is not an object'];
        yield 'a name of another type' => [$card(['name' => ['@type' => 'Card']]), '/name is not of @type Name'];
        yield 'components that are no list' => [$card(['name' => ['components' => ['a' => ['kind' => 'given', 'value' => 'Ada']]]]), '/name/components is not a list'];
        yield 'a component that is no object' => [$card(['name' => ['components' => ['Ada']]]), '/name/components/0 is not an object'];
        yield 'a component without value' => [$card(['name' => ['components' => [['kind' => 'given']]]]), '/name/components/0 has no value'];
        yield 'a component without kind' => [$card(['name' => ['components' => [['value' => 'Ada']]]]), '/name/components/0 has no kind'];
        yield 'an order that is no boolean' => [$card(['name' => ['full' => 'Ada', 'isOrdered' => 'yes']]), '/name/isOrdered is not a boolean'];
        yield 'a full name that is no text' => [$card(['name' => ['full' => ['Ada']]]), '/name/full is not a text'];
        yield 'a sort form that is no object' => [$card(['name' => ['full' => 'Ada', 'sortAs' => 'Ada']]), '/name/sortAs is not an object'];
        yield 'a full name too long' => [$card(['name' => ['full' => str_repeat('a', 256)]]), 'The name of a contact card is 255 characters or fewer.'];
        yield 'address components that are no list' => [$card(['addresses' => ['a1' => ['countryCode' => 'CA', 'components' => 'Main St']]]), '/addresses/a1/components is not a list'];
        yield 'an address component without value' => [$card(['addresses' => ['a1' => ['countryCode' => 'CA', 'components' => [['kind' => 'name']]]]]), '/addresses/a1/components/0 has no value'];
        yield 'an address component that is no object' => [$card(['addresses' => ['a1' => ['countryCode' => 'CA', 'components' => [[]]]]]), '/addresses/a1/components/0 is not an object'];
        yield 'too many entries' => [$card(['emails' => array_combine(array_map(static fn (int $i): string => 'e'.$i, range(1, 101)), array_fill(0, 101, ['address' => 'ada@example.com']))]), '/emails holds more than 100 entries'];
        yield 'too many components' => [$card(['name' => ['components' => array_fill(0, 101, ['kind' => 'given2', 'value' => 'A'])]]), '/name/components holds more than 100 entries'];
    }

    #[DataProvider('unreadable')]
    public function testWhatIsRefused(string $json, string $reason): void
    {
        $this->expectException(UnreadableJSContact::class);
        $this->expectExceptionMessageIsOrContains($reason);

        new JSContactReader()->read($json);
    }

    public function testTheBounds(): void
    {
        $json = $this->json(['emails' => ['e1' => ['address' => 'ada@example.com'], 'e2' => ['address' => 'ada@example.org']]]);

        self::assertCount(2, new JSContactReader(maxBytes: \strlen($json), maxEntries: 2)->read($json)->card->emails);
        self::assertCount(100, $this->read(['emails' => array_combine(array_map(static fn (int $i): string => 'e'.$i, range(1, 100)), array_fill(0, 100, ['address' => 'ada@example.com']))])->card->emails);
        self::assertSame('Ada', new JSContactReader(maxDepth: 4)->read($this->json(['name' => ['full' => 'Ada']]))->card->name);
        self::assertSame([65_536, 16, 100, null], [new JSContactReader()->maxBytes, new JSContactReader()->maxDepth, new JSContactReader()->maxEntries, new JSContactReader()->region]);

        foreach ([
            [new JSContactReader(maxBytes: \strlen($json) - 1), \sprintf('it is longer than %d bytes', \strlen($json) - 1)],
            [new JSContactReader(maxDepth: 3), 'it is no JSON of 3 levels or fewer'],
            [new JSContactReader(maxEntries: 1), '/emails holds more than 1 entries'],
        ] as [$reader, $reason]) {
            try {
                $reader->read($json);
                self::fail('A card beyond the bounds was read.');
            } catch (UnreadableJSContact $unreadableJSContact) {
                self::assertStringStartsWith('The JSContact card cannot be read: '.$reason, $unreadableJSContact->getMessage());
            }
        }
    }

    /**
     * @return iterable<string, array{int, int, int}>
     */
    public static function badBounds(): iterable
    {
        yield 'no byte' => [0, 16, 100];
        yield 'one level' => [1, 1, 100];
        yield 'no entry' => [1, 2, 0];
    }

    #[DataProvider('badBounds')]
    public function testBoundsThatReadNothing(int $bytes, int $depth, int $entries): void
    {
        $this->expectException(InvalidContact::class);
        $this->expectExceptionMessageIsOrContains('A JSContact reader reads at least one byte, two levels and one entry.');

        new JSContactReader($bytes, $depth, $entries);
    }

    public function testTheSmallestBounds(): void
    {
        self::assertSame([1, 2, 1], [new JSContactReader(1, 2, 1)->maxBytes, new JSContactReader(1, 2, 1)->maxDepth, new JSContactReader(1, 2, 1)->maxEntries]);
    }

    public function testANumericIdAndAnUnknownProperty(): void
    {
        $read = new JSContactReader()->read('{"@type":"Card","version":"1.0","uid":"u","emails":{"1":{"address":"ada@example.com"}},"7":true,"a/b":{}}');

        self::assertSame(['ada@example.com'], array_map(strval(...), $read->card->emails));
        self::assertEquals([7 => true, 'a/b' => new stdClass()], $read->unmapped);
        self::assertStringEndsWith('"7":true,"a/b":{}}', JSContactWriter::toJson($read));
    }

    public function testEveryAddressIsKept(): void
    {
        $read = $this->read(['addresses' => [
            'a' => ['countryCode' => 'FR', 'components' => [['kind' => 'locality', 'value' => 'Paris']]],
            'b' => ['countryCode' => 'CA', 'components' => [['kind' => 'locality', 'value' => 'Montréal']]],
        ]]);

        self::assertSame(['FR', 'CA'], array_map(static fn (Address $address): string => $address->country, $read->addresses));
    }

    public function testAnUnknownPropertyOfAnAddressComponentIsNoticed(): void
    {
        $read = $this->read(['addresses' => ['a' => ['countryCode' => 'FR', 'components' => [['kind' => 'locality', 'value' => 'Paris', 'phonetic' => 'pa']]]]]);

        self::assertSame(['/addresses/a/components/0/phonetic' => 'the card does not keep this property'], $this->notices($read));
    }

    public function testAStreetPartAfterANumberIsNotAStreetName(): void
    {
        $read = $this->read(['addresses' => ['a' => ['countryCode' => 'FR', 'components' => array_map(static fn (array $component): array => ['kind' => $component[0], 'value' => $component[1]], [['number', '1'], ['apartment', '2'], ['name', 'A'], ['name', 'B']])]]]);

        self::assertSame(['addressLine1' => '1 2 A', 'addressLine2' => 'B'], $read->addresses[0]->fields());
    }

    public function testAKindOfAnyLengthIsCutAtOneHundredCharactersInItsNotice(): void
    {
        $kind = str_repeat('é', 150);
        $cut = str_repeat('é', 100);
        $read = $this->read([
            'kind' => $kind,
            'name' => ['components' => [['kind' => 'given', 'value' => 'Ada'], ['kind' => $kind, 'value' => 'x']]],
            'addresses' => ['a' => ['countryCode' => 'FR', 'components' => [['kind' => $kind, 'value' => 'x']]]],
            'onlineServices' => ['s' => ['service' => $kind, 'user' => $kind, 'uri' => 'https://example.com']],
        ]);

        self::assertEqualsCanonicalizing([
            '/kind' => \sprintf('a card of kind "%s" is read as the card of a person', $cut),
            '/name/components/1' => \sprintf('"%s" is no kind of name component the card keeps', $cut),
            '/addresses/a/components/0' => \sprintf('the address does not keep this %s component', $cut),
            '/onlineServices/s/service' => \sprintf('"%s" is kept as a web address', $cut),
            '/onlineServices/s/user' => \sprintf('"%s" is kept as a web address', $cut),
        ], $this->notices($read));
    }

    public function testTheLanguageIsKeptWhenThereIsNoNameAtAll(): void
    {
        self::assertSame(['language' => 'fr'], $this->read(['language' => 'fr'])->unmapped);
        self::assertSame(['language' => 'fr'], $this->read(['language' => 'fr', 'name' => ['components' => [['kind' => 'title', 'value' => 'Dr.']]]])->unmapped);
    }

    public function testASortFormThatDoesNotEndTheSurnameSplitsNothing(): void
    {
        $read = $this->read(['name' => ['components' => [['kind' => 'given', 'value' => 'Vincent'], ['kind' => 'surname', 'value' => 'van Gogh']], 'sortAs' => ['surname' => 'Gogx']]]);

        self::assertSame([null, 'van Gogh'], [$read->card->structuredName?->particle, $read->card->structuredName?->family]);
        self::assertSame(['/name/sortAs/surname' => 'the name is sorted as its culture sorts it'], $this->notices($read));
    }

    public function testAParticleIsReadWithoutItsSpace(): void
    {
        $read = $this->read(['name' => ['components' => [['kind' => 'given', 'value' => 'Vincent'], ['kind' => 'surname', 'value' => 'van  Gogh']], 'sortAs' => ['surname' => 'Gogh']]]);

        self::assertSame(['van', 'Gogh'], [$read->card->structuredName?->particle, $read->card->structuredName?->family]);
    }

    public function testNumericPropertiesAreNoticedWithTheirKey(): void
    {
        $read = $this->read([
            'emails' => ['e' => ['address' => 'ada@example.com', '7' => true]],
            'phones' => ['p' => ['number' => '+15145550123', 'features' => ['8' => true, 'voice' => true]]],
        ]);

        self::assertSame([
            '/emails/e/7' => 'the card does not keep this property',
            '/phones/p/features/8' => 'the line keeps its role, voice',
        ], $this->notices($read));
    }

    public function testTheUserOfAServiceWinsOverItsUri(): void
    {
        $read = $this->read(['onlineServices' => ['s' => ['service' => 'Telegram', 'user' => 'adalove', 'uri' => 'https://t.me/other']]]);

        self::assertSame(['adalove'], array_map(static fn (Handle $handle): string => $handle->value, $read->card->handles));
    }

    public function testAServiceIsFoundByItsNameOrItsValueOnly(): void
    {
        $reader = new JSContactReader(messengers: [EnumSet::of(ChatMessenger::class, ChatMessenger::Mattermost)]);

        foreach (['mm', 'MM', 'Mattermost', 'mattermost'] as $service) {
            $read = $reader->read($this->json(['onlineServices' => ['s' => ['service' => $service, 'user' => 'ada']]]));

            self::assertSame(['Mattermost:ada'], array_map(static fn (Handle $handle): string => $handle->type->name.':'.$handle->value, $read->card->handles), $service);
        }

        self::assertSame([], $reader->read($this->json(['onlineServices' => ['s' => ['service' => 'm', 'user' => 'ada']]]))->card->handles);
    }

    public function testAReasonEndsWithOnePeriod(): void
    {
        try {
            $this->read(['name' => ['full' => str_repeat('a', 256)]]);
            self::fail('A name too long was read.');
        } catch (UnreadableJSContact $unreadablejsContact) {
            self::assertSame('The JSContact card cannot be read: The name of a contact card is 255 characters or fewer.', $unreadablejsContact->getMessage());
        }
    }

    /**
     * @param array<string, mixed> $card
     */
    private function read(array $card): JSContactCard
    {
        return new JSContactReader()->read($this->json($card));
    }

    /**
     * @param array<string, mixed> $card
     */
    private function json(array $card): string
    {
        return (string) json_encode(['@type' => 'Card', 'version' => '1.0', 'uid' => 'u', ...$card], \JSON_UNESCAPED_UNICODE);
    }

    /**
     * @return array<string, string>
     */
    private function notices(JSContactCard $card): array
    {
        return array_column(array_map(static fn (JSContactNotice $notice): array => [$notice->pointer, $notice->reason], $card->notices), 1, 0);
    }
}
