<?php

declare(strict_types=1);

namespace Phil\Contact\Tests\Benchmark;

use Generator;
use Phil\Contact\ContactCard;
use Phil\Contact\Email;
use Phil\Contact\PhoneLine;
use Phil\Contact\PhoneRole;
use Phil\Contact\VCard;
use Phil\InstantMessenger\Handle;
use Phil\InstantMessenger\KnownMessenger;
use Phil\Phone\PhoneNumber;
use Phil\Url\KnownUrlType;
use Phil\Url\TypedUrl;

/**
 * Every line of a vCard goes through the folding loop, and an address book export writes one card per contact, so the
 * cost of one card is multiplied by the size of the directory.
 *
 * @Groups({"contact"})
 */
final class VCardBench
{
    /**
     * @var array<string, ContactCard>
     */
    private array $cards;

    /**
     * @var list<ContactCard>
     */
    private readonly array $directory;

    public function __construct()
    {
        $typical = ContactCard::of(
            'Ada Lovelace',
            'Analytical Engines; Looms, and more',
            'Countess of Lovelace',
            [Email::of('ada@example.com'), Email::of('countess@example.org')],
            [new PhoneLine(PhoneRole::Mobile, PhoneNumber::parse('+1 514 555 0123')), new PhoneLine(PhoneRole::Fax, PhoneNumber::parse('+33 1 23 45 67 89 x2'))],
            [TypedUrl::of(KnownUrlType::Website, 'https://example.com')],
            [Handle::of(KnownMessenger::Xmpp, 'ada@jabber.org'), Handle::of(KnownMessenger::Telegram, 'adalove')],
        );

        $this->cards = [
            'minimal' => ContactCard::of('Ada Lovelace'),
            'typical' => $typical,
            'long multibyte fields' => ContactCard::of(
                str_repeat('Élodie Bérubé-Québec ', 12),
                str_repeat('Coopérative des hôtes, ', 11),
                str_repeat('Directrice générale ; ', 11),
                [Email::of('elodie@example.com')],
            ),
        ];

        $this->directory = array_fill(0, 1_000, $typical);
    }

    /**
     * @ParamProviders("cards")
     *
     * @Revs(1000)
     *
     * @Iterations(5)
     *
     * @param array{card: string} $params
     */
    public function benchOneCard(array $params): void
    {
        VCard::of($this->cards[$params['card']]);
    }

    /**
     * @ParamProviders("directories")
     *
     * @Revs(5)
     *
     * @Iterations(5)
     *
     * @param array{cards: int} $params
     */
    public function benchDirectoryExport(array $params): void
    {
        foreach (\array_slice($this->directory, 0, $params['cards']) as $card) {
            VCard::of($card);
        }
    }

    /**
     * @return Generator<string, array{card: string}>
     */
    public function cards(): Generator
    {
        yield 'minimal' => ['card' => 'minimal'];
        yield 'typical' => ['card' => 'typical'];
        yield 'long multibyte fields' => ['card' => 'long multibyte fields'];
    }

    /**
     * @return Generator<string, array{cards: int}>
     */
    public function directories(): Generator
    {
        yield '10 cards' => ['cards' => 10];
        yield '100 cards' => ['cards' => 100];
        yield '1000 cards' => ['cards' => 1_000];
    }
}
