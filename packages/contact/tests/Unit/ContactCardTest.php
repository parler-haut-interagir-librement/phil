<?php

declare(strict_types=1);

namespace Phil\Contact\Tests\Unit;

use Phil\Contact\ContactCard;
use Phil\Contact\Email;
use Phil\Contact\Exception\InvalidContact;
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

#[CoversClass(ContactCard::class)]
#[CoversClass(PhoneLine::class)]
#[CoversClass(PhoneRole::class)]
#[CoversClass(InvalidContact::class)]
final class ContactCardTest extends TestCase
{
    public function testACardNamesAPersonOrAnOrganisation(): void
    {
        $person = ContactCard::of(' Ada Lovelace ', 'Analytical Engines', 'Countess');
        $organisation = ContactCard::of(organization: 'Analytical Engines');

        self::assertSame(['Ada Lovelace', 'Analytical Engines', 'Countess', 'Ada Lovelace', true], [$person->name, $person->organization, $person->title, $person->displayName(), $person->isPerson()]);
        self::assertSame(['Analytical Engines', false], [$organisation->displayName(), $organisation->isPerson()]);
        self::assertSame([null, null, null, null], [ContactCard::of('  ', '')->name, ContactCard::of('  ')->organization, ContactCard::of(title: ' ')->title, ContactCard::of()->displayName()]);
    }

    /**
     * @return iterable<string, array{ContactCard, bool}>
     */
    public static function cards(): iterable
    {
        yield 'nothing' => [ContactCard::of(' '), true];
        yield 'a name' => [ContactCard::of('Ada'), false];
        yield 'an organisation' => [ContactCard::of(organization: 'Engines'), false];
        yield 'a title' => [ContactCard::of(title: 'Countess'), false];
        yield 'an email' => [ContactCard::of(emails: [Email::of('ada@example.com')]), false];
        yield 'a phone' => [ContactCard::of(phones: [new PhoneLine(PhoneRole::Voice, PhoneNumber::parse('+15145550123'))]), false];
        yield 'an address' => [ContactCard::of(urls: [TypedUrl::of(KnownUrlType::Website, 'https://example.com')]), false];
        yield 'a handle' => [ContactCard::of(handles: [Handle::of(KnownMessenger::Telegram, 'adalove')]), false];
    }

    #[DataProvider('cards')]
    public function testACardWithNothingIsEmpty(ContactCard $card, bool $empty): void
    {
        self::assertSame($empty, $card->isEmpty());
    }

    public function testAStructuredNameGivesTheNameItLacks(): void
    {
        $name = PersonName::of('Charles', 'Gaulle', 'de', culture: 'fr');

        self::assertSame(['Charles de Gaulle', true, $name], [ContactCard::of(structuredName: $name)->name, ContactCard::of(structuredName: $name)->isPerson(), ContactCard::of(structuredName: $name)->structuredName]);
        self::assertSame('Le Général', ContactCard::of(' Le Général ', structuredName: $name)->name);
        self::assertNull(ContactCard::of()->structuredName);

        $this->expectException(InvalidContact::class);
        $this->expectExceptionMessageIsOrContains('The name of a contact card is 255 characters or fewer.');
        ContactCard::of(structuredName: PersonName::of(str_repeat('a', 200), str_repeat('b', 60)));
    }

    public function testThePhonesOfARoleKeepTheirOrder(): void
    {
        $office = new PhoneLine(PhoneRole::Voice, PhoneNumber::parse('+15145550123'));
        $fax = new PhoneLine(PhoneRole::Fax, PhoneNumber::parse('+15145550124'));
        $shop = new PhoneLine(PhoneRole::Voice, PhoneNumber::parse('+15145550125'));
        $card = ContactCard::of('Ada', phones: [$office, $fax, $shop]);

        self::assertSame([$office, $shop], $card->phonesFor(PhoneRole::Voice));
        self::assertSame([$fax], $card->phonesFor(PhoneRole::Fax));
        self::assertSame([], $card->phonesFor(PhoneRole::Mobile));
    }

    public function testARoleHasItsVCardType(): void
    {
        self::assertSame(['voice', 'cell', 'fax', 'text', 'pager'], array_map(static fn (PhoneRole $role): string => $role->vCardType(), PhoneRole::cases()));
    }

    public function testATextIs255CharactersOrFewer(): void
    {
        self::assertSame(255, mb_strlen(ContactCard::of(str_repeat('é', 255))->name ?? ''));

        foreach (['name' => [str_repeat('é', 256)], 'organization' => [null, str_repeat('a', 256)], 'title' => [null, null, str_repeat('a', 256)]] as $field => $arguments) {
            try {
                ContactCard::of(...$arguments);
                self::fail('A long '.$field.' was taken.');
            } catch (InvalidContact $invalidContact) {
                self::assertSame(\sprintf('The %s of a contact card is 255 characters or fewer.', $field), $invalidContact->getMessage());
            }
        }
    }
}
