<?php

declare(strict_types=1);

namespace Phil\Contact;

use BackedEnum;
use JsonException;
use Phil\Contact\Exception\InvalidContact;
use Phil\Contact\Exception\UnreadableJSContact;
use Phil\Contact\JSContact\AddressReading;
use Phil\Contact\JSContact\NameReading;
use Phil\Contact\JSContact\Reading;
use Phil\Enums\Collection\EnumSet;
use Phil\InstantMessenger\Exception\InstantMessengerException;
use Phil\InstantMessenger\Handle;
use Phil\InstantMessenger\KnownMessenger;
use Phil\InstantMessenger\MessengerType;
use Phil\Phone\Exception\PhoneException;
use Phil\Phone\PhoneNumber;
use Phil\Url\Exception\UrlException;
use Phil\Url\KnownUrlType;
use Phil\Url\TypedUrl;
use Phil\Url\Url;
use Phil\Url\UrlType;
use Phil\Url\UrlTypes;

/**
 * A JSContact card (RFC 9553), version 1.0, read into a `JSContactCard`: the card, its addresses, the properties the
 * library does not map, kept as they are, and a notice for each part read but not kept. Nothing is dropped silently.
 *
 * The reader is strict and bounded. A text longer than `maxBytes`, nested deeper than `maxDepth`, a map or a list of
 * more than `maxEntries` entries, a property of the wrong JSON type, an `@var` that is not the expected one or an
 * entry key that is no JSContact `Id` throws `UnreadableJSContact`. A value its type refuses — an email that is none,
 * a number no plan knows — leaves its entry out with a notice.
 *
 * | JSContact | Card |
 * |---|---|
 * | `name`: `full`, `components`, `sortAs.surname`, `isOrdered` | `name` and `structuredName`; a particle is the part of the surname before its `sortAs` |
 * | `language` | The culture of the structured name; kept unmapped when the card has none |
 * | `kind` `org` | A card that names only its organisation |
 * | `organizations`, `titles` | `organization` and `title`, the first of each |
 * | `emails`, `phones` | `emails`, `phones`: the role from the `features` |
 * | `onlineServices` | `handles` when the service is an accepted messaging service, `urls` when the URI is an accepted kind |
 * | `links` | `urls` |
 * | `addresses` | `Address`es of phil/address |
 */
final readonly class JSContactReader
{
    private const array ROLES = ['mobile' => PhoneRole::Mobile, 'fax' => PhoneRole::Fax, 'pager' => PhoneRole::Pager, 'voice' => PhoneRole::Voice, 'text' => PhoneRole::Text];

    /**
     * @var positive-int
     */
    public int $maxBytes;

    /**
     * @var int<2, max>
     */
    public int $maxDepth;

    /**
     * @var positive-int
     */
    public int $maxEntries;

    /**
     * @var list<EnumSet<covariant MessengerType>>
     */
    private array $messengers;

    /**
     * @var list<EnumSet<covariant UrlType>>
     */
    private array $urlTypes;

    /**
     * @param ?string                                $region     the country a phone number without its country code is read in
     * @param list<EnumSet<covariant MessengerType>> $messengers the messaging services accepted; `KnownMessenger` when empty
     * @param list<EnumSet<covariant UrlType>>       $urlTypes   the kinds of web address accepted; `KnownUrlType` when empty
     */
    public function __construct(
        int $maxBytes = 65_536,
        int $maxDepth = 16,
        int $maxEntries = 100,
        public ?string $region = null,
        array $messengers = [],
        array $urlTypes = [],
    ) {
        if ($maxBytes < 1 || $maxDepth < 2 || $maxEntries < 1) {
            throw InvalidContact::because('A JSContact reader reads at least one byte, two levels and one entry.');
        }

        $this->maxBytes = $maxBytes;
        $this->maxDepth = $maxDepth;
        $this->maxEntries = $maxEntries;

        $this->messengers = [] === $messengers ? [EnumSet::all(KnownMessenger::class)] : $messengers;
        $this->urlTypes = [] === $urlTypes ? [EnumSet::all(KnownUrlType::class)] : $urlTypes;
    }

    public function read(string $json): JSContactCard
    {
        if (\strlen($json) > $this->maxBytes) {
            throw UnreadableJSContact::because(\sprintf('it is longer than %d bytes', $this->maxBytes));
        }

        try {
            $data = json_decode($json, false, $this->maxDepth, \JSON_THROW_ON_ERROR | \JSON_BIGINT_AS_STRING);
        } catch (JsonException $jsonException) {
            throw UnreadableJSContact::because(\sprintf('it is no JSON of %d levels or fewer (%s)', $this->maxDepth, $jsonException->getMessage()));
        }

        $reading = new Reading($this->maxEntries);
        $card = $reading->object($data, '');

        if ('Card' !== ($card['@type'] ?? null) || '1.0' !== ($card['version'] ?? null)) {
            throw UnreadableJSContact::because('it is no Card of version 1.0');
        }

        $uid = $reading->required($card, 'uid', '');
        $kind = $reading->string($card, 'kind', '') ?? 'individual';
        $organisation = 'org' === $kind;

        if (!$organisation && 'individual' !== $kind) {
            $reading->notice('/kind', \sprintf('a card of kind "%s" is read as the card of a person', mb_substr($kind, 0, 100)));
        }

        [$full, $name, $languageUsed] = NameReading::read($reading, $card, $organisation, $reading->string($card, 'language', ''));
        $organization = $this->first($reading, $card, 'organizations', 'Organization');
        [$urls, $handles] = $this->services($reading, $card);

        if ($organisation && null !== $full && null !== $organization && $full !== $organization) {
            $reading->notice('/name/full', 'the card of an organisation keeps the name of its organisation');
        }

        try {
            $contact = ContactCard::of(
                $organisation ? null : $full,
                $organization ?? ($organisation ? $full : null),
                $this->first($reading, $card, 'titles', 'Title'),
                $this->emails($reading, $card),
                $this->phones($reading, $card),
                [...$this->links($reading, $card), ...$urls],
                $handles,
                $name,
            );
            $addresses = AddressReading::read($reading, $card);

            return new JSContactCard($uid, $contact, $addresses, array_diff_key($card, array_flip($languageUsed ? [...JSContactCard::MAPPED, 'language'] : JSContactCard::MAPPED)), $reading->notices);
        } catch (InvalidContact $invalidContact) {
            throw UnreadableJSContact::because($invalidContact->getMessage());
        }
    }

    /**
     * The `name` of the first entry of `organizations` or `titles`, with a notice for each other entry and for a title
     * that is a role.
     *
     * @param array<string, mixed> $card
     */
    private function first(Reading $reading, array $card, string $property, string $type): ?string
    {
        $text = null;
        $titles = 'titles' === $property;

        foreach ($reading->map($card, $property, $type) as $id => $entry) {
            $pointer = '/'.$property.'/'.$id;
            $value = $reading->required($entry, 'name', $pointer);
            $role = $titles && 'role' === $reading->string($entry, 'kind', $pointer);
            $reading->unknown($entry, $titles ? ['@type', 'name', 'kind'] : ['@type', 'name'], $pointer);

            if ($role) {
                $reading->notice($pointer, 'a role is not the title of the card');
            } elseif (null === $text) {
                $text = $value;
            } else {
                $reading->notice($pointer, \sprintf('the card keeps the first of its %s', $property));
            }
        }

        return $text;
    }

    /**
     * @param array<string, mixed> $card
     *
     * @return list<Email>
     */
    private function emails(Reading $reading, array $card): array
    {
        $emails = [];

        foreach ($reading->map($card, 'emails', 'EmailAddress') as $id => $entry) {
            $pointer = '/emails/'.$id;
            $address = $reading->required($entry, 'address', $pointer);
            $reading->unknown($entry, ['@type', 'address'], $pointer);

            try {
                $emails[] = Email::of($address);
            } catch (InvalidContact $invalidContact) {
                $reading->notice($pointer.'/address', $invalidContact->getMessage());
            }
        }

        return $emails;
    }

    /**
     * Each line with the role of its first feature among mobile, fax, pager, voice and text; voice without any.
     *
     * @param array<string, mixed> $card
     *
     * @return list<PhoneLine>
     */
    private function phones(Reading $reading, array $card): array
    {
        $lines = [];

        foreach ($reading->map($card, 'phones', 'Phone') as $id => $entry) {
            $pointer = '/phones/'.$id;
            $number = $reading->required($entry, 'number', $pointer);
            $features = \array_key_exists('features', $entry) ? $reading->object($entry['features'], $pointer.'/features') : [];
            $reading->unknown($entry, ['@type', 'number', 'features'], $pointer);

            if (!array_all($features, static fn (mixed $on): bool => \is_bool($on))) {
                throw UnreadableJSContact::because($pointer.'/features holds a value that is not a boolean');
            }

            $features = array_map(strval(...), array_keys(array_filter($features, static fn (mixed $on): bool => true === $on)));
            $role = array_find(self::ROLES, static fn (PhoneRole $role, string $feature): bool => \in_array($feature, $features, true)) ?? PhoneRole::Voice;
            // A mobile line takes calls and texts: those features are its role's.
            $implied = PhoneRole::Mobile === $role ? ['mobile', 'voice', 'text'] : [array_search($role, self::ROLES, true)];

            foreach (array_diff($features, $implied) as $feature) {
                $reading->notice($pointer.'/features/'.Reading::escape($feature), \sprintf('the line keeps its role, %s', $role->value));
            }

            try {
                $lines[] = new PhoneLine($role, PhoneNumber::parse($number, $this->region));
            } catch (PhoneException $phoneException) {
                $reading->notice($pointer.'/number', $phoneException->getMessage());
            }
        }

        return $lines;
    }

    /**
     * The handles of the accepted messaging services, and the web addresses of the others.
     *
     * @param array<string, mixed> $card
     *
     * @return array{list<TypedUrl>, list<Handle>}
     */
    private function services(Reading $reading, array $card): array
    {
        $urls = [];
        $handles = [];

        foreach ($reading->map($card, 'onlineServices', 'OnlineService') as $id => $entry) {
            $pointer = '/onlineServices/'.$id;
            $service = $reading->string($entry, 'service', $pointer);
            $user = $reading->string($entry, 'user', $pointer);
            $uri = $reading->string($entry, 'uri', $pointer);
            $reading->unknown($entry, ['@type', 'service', 'user', 'uri'], $pointer);
            $messenger = null === $service ? null : $this->messenger($service);
            $value = $user ?? $uri;

            try {
                if ($messenger instanceof MessengerType && null !== $value) {
                    $handles[] = Handle::of($messenger, $value);
                } elseif (null !== $uri) {
                    $urls[] = $this->url($uri);

                    foreach (array_filter(['service' => $service, 'user' => $user], static fn (?string $text): bool => null !== $text) as $property => $text) {
                        $reading->notice($pointer.'/'.$property, \sprintf('"%s" is kept as a web address', mb_substr($text, 0, 100)));
                    }
                } else {
                    $reading->notice($pointer, 'it is no accepted messaging service and has no URI');
                }
            } catch (InstantMessengerException|UrlException $exception) {
                $reading->notice($pointer, $exception->getMessage());
            }
        }

        return [$urls, $handles];
    }

    /**
     * @param array<string, mixed> $card
     *
     * @return list<TypedUrl>
     */
    private function links(Reading $reading, array $card): array
    {
        $urls = [];

        foreach ($reading->map($card, 'links', 'Link') as $id => $entry) {
            $pointer = '/links/'.$id;
            $uri = $reading->required($entry, 'uri', $pointer);
            $reading->unknown($entry, ['@type', 'uri'], $pointer);

            try {
                $urls[] = $this->url($uri);
            } catch (UrlException $urlException) {
                $reading->notice($pointer.'/uri', $urlException->getMessage());
            }
        }

        return $urls;
    }

    /**
     * The accepted messaging service a JSContact `service` names: by its case name or its value, in any case.
     */
    private function messenger(string $service): ?MessengerType
    {
        foreach ($this->messengers as $set) {
            foreach ($set as $type) {
                if (0 === strcasecmp($type->name, $service) || ($type instanceof BackedEnum && 0 === strcasecmp((string) $type->value, $service))) {
                    return $type;
                }
            }
        }

        return null;
    }

    private function url(string $uri): TypedUrl
    {
        return UrlTypes::type(Url::fromString($uri), ...$this->urlTypes);
    }
}
