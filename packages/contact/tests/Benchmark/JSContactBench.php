<?php

declare(strict_types=1);

namespace Phil\Contact\Tests\Benchmark;

use Generator;
use Phil\Address\Address;
use Phil\Contact\ContactCard;
use Phil\Contact\Email;
use Phil\Contact\JSContactCard;
use Phil\Contact\JSContactReader;
use Phil\Contact\JSContactWriter;
use Phil\Contact\PersonName;
use Phil\Contact\PhoneLine;
use Phil\Contact\PhoneRole;
use Phil\InstantMessenger\Handle;
use Phil\InstantMessenger\KnownMessenger;
use Phil\Phone\PhoneNumber;
use Phil\Url\KnownUrlType;
use Phil\Url\TypedUrl;

/**
 * An address book import reads one JSContact card per contact, each through the bounded reader and the value types
 * of the card, and an export writes them back: the cost of one card is multiplied by the size of the directory.
 *
 * @Groups({"contact"})
 */
final class JSContactBench
{
    /**
     * @var array<string, string>
     */
    private array $json;

    /**
     * @var array<string, JSContactCard>
     */
    private array $documents;

    private readonly JSContactReader $reader;

    public function __construct()
    {
        $typical = new JSContactCard('urn:uuid:7', ContactCard::of(
            organization: 'Analytical Engines',
            title: 'Countess of Lovelace',
            emails: [Email::of('ada@example.com'), Email::of('countess@example.org')],
            phones: [new PhoneLine(PhoneRole::Mobile, PhoneNumber::parse('+1 514 555 0123')), new PhoneLine(PhoneRole::Fax, PhoneNumber::parse('+33 1 23 45 67 89 x2'))],
            urls: [TypedUrl::of(KnownUrlType::Website, 'https://example.com')],
            handles: [Handle::of(KnownMessenger::Telegram, 'adalove')],
            structuredName: PersonName::of('Ada', 'Lovelace', additional: ['Augusta'], honorific: 'Lady', culture: 'en-GB'),
        ), [Address::of('GB', ['addressLine1' => '12 St James’s Square', 'locality' => 'London', 'postalCode' => 'SW1Y 4LB'])]);

        $this->documents = [
            'minimal' => new JSContactCard('u', ContactCard::of('Ada Lovelace')),
            'typical' => $typical,
        ];
        $this->json = array_map(JSContactWriter::toJson(...), $this->documents);
        $this->reader = new JSContactReader();
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
    public function benchRead(array $params): void
    {
        $this->reader->read($this->json[$params['card']]);
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
    public function benchWrite(array $params): void
    {
        JSContactWriter::toJson($this->documents[$params['card']]);
    }

    /**
     * @return Generator<string, array{card: string}>
     */
    public function cards(): Generator
    {
        yield 'minimal' => ['card' => 'minimal'];
        yield 'typical' => ['card' => 'typical'];
    }
}
