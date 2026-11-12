<?php

declare(strict_types=1);

namespace Phil\Contact\Tests\Unit;

use Phil\Contact\ContactCard;
use Phil\Contact\ContactCardArray;
use Phil\Contact\Email;
use Phil\Contact\Exception\UnreadableContact;
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

#[CoversClass(ContactCardArray::class)]
#[CoversClass(UnreadableContact::class)]
final class ContactCardArrayTest extends TestCase
{
    private const array STORED = [
        'name' => 'Ada Lovelace',
        'organization' => 'Analytical Engines',
        'title' => null,
        'emails' => ['ada@example.com'],
        'phones' => [['role' => 'mobile', 'number' => '+15145550123;ext=12']],
        'urls' => [['type' => KnownUrlType::class.'::Website', 'url' => 'https://example.com/']],
        'handles' => [['type' => KnownMessenger::class.'::Telegram', 'handle' => 'adalove']],
        'structuredName' => null,
    ];

    private const array NAME = ['given' => 'Charles', 'family' => 'Gaulle', 'particle' => 'de', 'additional' => ['André', 'Joseph'], 'secondFamily' => null, 'honorific' => 'Général', 'generation' => null, 'credential' => null, 'culture' => 'fr', 'order' => 'family-first'];

    public function testACardIsAnArrayOfTexts(): void
    {
        $card = ContactCard::of(
            'Ada Lovelace',
            'Analytical Engines',
            emails: [Email::of('ada@example.com')],
            phones: [new PhoneLine(PhoneRole::Mobile, PhoneNumber::parse('+1 514 555 0123 x12'))],
            urls: [TypedUrl::of(KnownUrlType::Website, 'https://example.com')],
            handles: [Handle::of(KnownMessenger::Telegram, '@AdaLove')],
        );

        self::assertSame(self::STORED, ContactCardArray::from($card));
        self::assertSame(self::STORED, ContactCardArray::from(ContactCardArray::to(self::STORED)));
    }

    public function testMissingKeysAreEmpty(): void
    {
        $card = ContactCardArray::to(['name' => 'Ada']);

        self::assertSame(['Ada', null, null, [], [], [], [], null], [$card->name, $card->organization, $card->title, $card->emails, $card->phones, $card->urls, $card->handles, $card->structuredName]);
    }

    public function testAStructuredNameWithoutOrderIsKeptWithoutIt(): void
    {
        $stored = ContactCardArray::from(ContactCard::of('Ada', structuredName: PersonName::of('Ada', 'Lovelace')));

        self::assertSame(['given' => 'Ada', 'family' => 'Lovelace', 'particle' => null, 'additional' => [], 'secondFamily' => null, 'honorific' => null, 'generation' => null, 'credential' => null, 'culture' => null, 'order' => null], $stored['structuredName']);
    }

    public function testAStructuredNameIsKeptInItsParts(): void
    {
        $name = PersonName::of('Charles', 'Gaulle', 'de', ['André', 'Joseph'], honorific: 'Général', culture: 'fr', order: NameOrder::FamilyFirst);
        $stored = ContactCardArray::from(ContactCard::of('Charles de Gaulle', structuredName: $name));

        self::assertSame(self::NAME, $stored['structuredName']);
        self::assertTrue($name->equals(ContactCardArray::to($stored)->structuredName ?? PersonName::of('Nobody')));
        self::assertSame(['Ada', [], null], [ContactCardArray::to(['structuredName' => ['given' => 'Ada']])->structuredName?->given, ContactCardArray::to(['structuredName' => ['given' => 'Ada']])->structuredName?->additional, ContactCardArray::to(['structuredName' => ['given' => 'Ada', 'order' => null]])->structuredName?->order]);
        self::assertSame('Ada', ContactCardArray::to(['structuredName' => ['given' => 'Ada']])->name);
    }

    /**
     * @return iterable<string, array{mixed, string}>
     */
    public static function unreadable(): iterable
    {
        yield 'not an array' => ['Ada', 'it is not an array'];
        yield 'a name that is no text' => [['name' => 42], 'the name is not a text'];
        yield 'an organisation that is no text' => [['organization' => []], 'the organization is not a text'];
        yield 'a title that is no text' => [['title' => false], 'the title is not a text'];
        yield 'emails that are no list' => [['emails' => 'ada@example.com'], 'the emails are not a list'];
        yield 'emails with keys' => [['emails' => ['a' => 'ada@example.com']], 'the emails are not a list'];
        yield 'an email that is no text' => [['emails' => [42]], 'an entry of the emails is neither a text nor an object'];
        yield 'an email that is none' => [['emails' => ['ada']], '"ada" is not an email address.'];
        yield 'a phone without number' => [['phones' => [['role' => 'voice']]], 'an entry has no text "number"'];
        yield 'a phone of no role' => [['phones' => [['role' => 'office', 'number' => '+15145550123']]], 'a phone line has no known role'];
        yield 'a phone without its country code' => [['phones' => [['role' => 'voice', 'number' => '5145550123']]], '"5145550123" is written without its country code, and no region is given'];
        yield 'an address of no kind' => [['urls' => [['type' => KnownUrlType::class.'::Nothing', 'url' => 'https://example.com/']]], '"'.KnownUrlType::class.'::Nothing" is no case of an enum implementing Phil\Url\UrlType'];
        yield 'an address of another enum' => [['urls' => [['type' => PhoneRole::class.'::Voice', 'url' => 'https://example.com/']]], '"'.PhoneRole::class.'::Voice" is no case of an enum implementing Phil\Url\UrlType'];
        yield 'an address of a case and more' => [['urls' => [['type' => KnownUrlType::class.'::Website::Facebook', 'url' => 'https://example.com/']]], '"'.KnownUrlType::class.'::Website::Facebook" is no case'];
        yield 'an address of a class' => [['urls' => [['type' => 'stdClass', 'url' => 'https://example.com/']]], '"stdClass" is no case of an enum implementing Phil\Url\UrlType'];
        yield 'an address that is none' => [['urls' => [['type' => KnownUrlType::class.'::Website', 'url' => 'nope']]], 'is not'];
        yield 'a handle of no service' => [['handles' => [['type' => KnownUrlType::class.'::Website', 'handle' => 'ada']]], '"'.KnownUrlType::class.'::Website" is no case of an enum implementing Phil\InstantMessenger\MessengerType'];
        yield 'a structured name that is no object' => [['structuredName' => 'Ada'], 'the structured name is not an object'];
        yield 'additional names that are no list' => [['structuredName' => ['given' => 'Ada', 'additional' => 'Augusta']], 'the additional names are not a list'];
        yield 'additional names with keys' => [['structuredName' => ['given' => 'Ada', 'additional' => ['a' => 'Augusta']]], 'the additional names are not a list'];
        yield 'an additional name that is no text' => [['structuredName' => ['given' => 'Ada', 'additional' => [1]]], 'an additional name is not a text'];
        yield 'a structured name of no order' => [['structuredName' => ['given' => 'Ada', 'order' => 'sideways']], 'the structured name has no known order'];
        yield 'a structured name part that is no text' => [['structuredName' => ['given' => 7]], 'the given is not a text'];
        yield 'a structured name that is none' => [['structuredName' => ['honorific' => 'Dr.']], 'A person name has a given name or a family name.'];
        yield 'a handle that is none' => [['handles' => [['type' => KnownMessenger::class.'::Telegram', 'handle' => '!']]], '"!" is not a Telegram handle.'];
    }

    public function testAReasonEndsWithOnePeriod(): void
    {
        try {
            ContactCardArray::to(['emails' => ['ada']]);
            self::fail('A bad email was read.');
        } catch (UnreadableContact $unreadableContact) {
            self::assertSame('The stored contact card cannot be read: "ada" is not an email address.', $unreadableContact->getMessage());
        }
    }

    #[DataProvider('unreadable')]
    public function testWhatCannotBeReadBackIsRefused(mixed $data, string $reason): void
    {
        $this->expectException(UnreadableContact::class);
        $this->expectExceptionMessageIsOrContains('The stored contact card cannot be read: ');
        $this->expectExceptionMessageIsOrContains($reason);

        ContactCardArray::to($data);
    }
}
