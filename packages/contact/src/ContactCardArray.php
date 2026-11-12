<?php

declare(strict_types=1);

namespace Phil\Contact;

use Phil\Contact\Exception\InvalidContact;
use Phil\Contact\Exception\UnreadableContact;
use Phil\InstantMessenger\Exception\InstantMessengerException;
use Phil\InstantMessenger\Handle;
use Phil\InstantMessenger\MessengerType;
use Phil\Phone\Exception\PhoneException;
use Phil\Phone\PhoneNumber;
use Phil\Url\Exception\UrlException;
use Phil\Url\TypedUrl;
use Phil\Url\UrlType;
use UnitEnum;

/**
 * A card as an array of texts, for one JSON column, and back. The kinds of address and the messaging services are
 * written `Enum\Class::Case`, read back only as cases of enums implementing `UrlType` and `MessengerType`.
 *
 * ```json
 * {"name": "Ada Lovelace", "organization": null, "title": null, "emails": ["ada@example.com"],
 *  "phones": [{"role": "mobile", "number": "+15145550123"}],
 *  "urls": [{"type": "Phil\\Url\\KnownUrlType::Website", "url": "https://example.com/"}],
 *  "handles": [{"type": "Phil\\InstantMessenger\\KnownMessenger::Telegram", "handle": "adalove"}],
 *  "structuredName": {"given": "Ada", "family": "Lovelace", "particle": null, "additional": ["Augusta"],
 *   "secondFamily": null, "honorific": null, "generation": null, "credential": null, "culture": "en-GB", "order": null}}
 * ```
 *
 * A card stored without `structuredName` is read back without one.
 */
final class ContactCardArray
{
    /**
     * @return array{name: ?string, organization: ?string, title: ?string, emails: list<string>, phones: list<array{role: string, number: string}>, urls: list<array{type: string, url: string}>, handles: list<array{type: string, handle: string}>, structuredName: ?array{given: ?string, family: ?string, particle: ?string, additional: list<string>, secondFamily: ?string, honorific: ?string, generation: ?string, credential: ?string, culture: ?string, order: ?string}}
     */
    public static function from(ContactCard $card): array
    {
        return [
            'name' => $card->name,
            'organization' => $card->organization,
            'title' => $card->title,
            'emails' => array_map(static fn (Email $email): string => $email->value, $card->emails),
            'phones' => array_map(static fn (PhoneLine $line): array => ['role' => $line->role->value, 'number' => (string) $line->number], $card->phones),
            'urls' => array_map(static fn (TypedUrl $url): array => ['type' => self::reference($url->type), 'url' => $url->url->toString()], $card->urls),
            'handles' => array_map(static fn (Handle $handle): array => ['type' => self::reference($handle->type), 'handle' => $handle->value], $card->handles),
            'structuredName' => ($name = $card->structuredName) instanceof PersonName ? [
                'given' => $name->given,
                'family' => $name->family,
                'particle' => $name->particle,
                'additional' => $name->additional,
                'secondFamily' => $name->secondFamily,
                'honorific' => $name->honorific,
                'generation' => $name->generation,
                'credential' => $name->credential,
                'culture' => $name->culture,
                'order' => $name->order?->value,
            ] : null,
        ];
    }

    public static function to(mixed $data): ContactCard
    {
        if (!\is_array($data)) {
            throw UnreadableContact::because('it is not an array');
        }

        try {
            return ContactCard::of(
                self::text($data, 'name'),
                self::text($data, 'organization'),
                self::text($data, 'title'),
                array_map(static fn (array $entry): Email => Email::of(self::string($entry, 'value')), self::entries($data, 'emails')),
                array_map(static fn (array $entry): PhoneLine => new PhoneLine(PhoneRole::tryFrom(self::string($entry, 'role')) ?? throw UnreadableContact::because('a phone line has no known role'), PhoneNumber::parse(self::string($entry, 'number'))), self::entries($data, 'phones')),
                array_map(static fn (array $entry): TypedUrl => TypedUrl::of(self::enumCase(self::string($entry, 'type'), UrlType::class), self::string($entry, 'url')), self::entries($data, 'urls')),
                array_map(static fn (array $entry): Handle => Handle::of(self::enumCase(self::string($entry, 'type'), MessengerType::class), self::string($entry, 'handle')), self::entries($data, 'handles')),
                self::structuredName($data['structuredName'] ?? null),
            );
        } catch (InvalidContact|PhoneException|UrlException|InstantMessengerException $exception) {
            throw UnreadableContact::because($exception->getMessage());
        }
    }

    private static function structuredName(mixed $data): ?PersonName
    {
        if (null === $data) {
            return null;
        }

        if (!\is_array($data)) {
            throw UnreadableContact::because('the structured name is not an object');
        }

        $additional = $data['additional'] ?? [];

        if (!\is_array($additional) || !array_is_list($additional)) {
            throw UnreadableContact::because('the additional names are not a list');
        }

        $order = self::text($data, 'order');

        return PersonName::of(
            self::text($data, 'given'),
            self::text($data, 'family'),
            self::text($data, 'particle'),
            array_map(static fn (mixed $name): string => \is_string($name) ? $name : throw UnreadableContact::because('an additional name is not a text'), $additional),
            self::text($data, 'secondFamily'),
            self::text($data, 'honorific'),
            self::text($data, 'generation'),
            self::text($data, 'credential'),
            self::text($data, 'culture'),
            null === $order ? null : NameOrder::tryFrom($order) ?? throw UnreadableContact::because('the structured name has no known order'),
        );
    }

    private static function reference(UnitEnum $case): string
    {
        return $case::class.'::'.$case->name;
    }

    /**
     * @param array<array-key, mixed> $data
     */
    private static function text(array $data, string $key): ?string
    {
        $value = $data[$key] ?? null;

        return null === $value || \is_string($value) ? $value : throw UnreadableContact::because(\sprintf('the %s is not a text', $key));
    }

    /**
     * Each entry as an array: an email, a text, becomes `['value' => …]`.
     *
     * @param array<array-key, mixed> $data
     *
     * @return list<array<array-key, mixed>>
     */
    private static function entries(array $data, string $key): array
    {
        $list = $data[$key] ?? [];

        if (!\is_array($list) || !array_is_list($list)) {
            throw UnreadableContact::because(\sprintf('the %s are not a list', $key));
        }

        return array_map(static fn (mixed $entry): array => match (true) {
            \is_array($entry) => $entry,
            \is_string($entry) => ['value' => $entry],
            default => throw UnreadableContact::because(\sprintf('an entry of the %s is neither a text nor an object', $key)),
        }, $list);
    }

    /**
     * @param array<array-key, mixed> $entry
     */
    private static function string(array $entry, string $key): string
    {
        $value = $entry[$key] ?? null;

        return \is_string($value) ? $value : throw UnreadableContact::because(\sprintf('an entry has no text "%s"', $key));
    }

    /**
     * @template T of UnitEnum
     *
     * @param class-string<T> $interface
     *
     * @return T
     */
    private static function enumCase(string $reference, string $interface): UnitEnum
    {
        [$class, $name] = explode('::', $reference, 2) + [1 => ''];

        if (enum_exists($class) && is_subclass_of($class, $interface)) {
            foreach ($class::cases() as $case) {
                if ($name === $case->name) {
                    return $case;
                }
            }
        }

        throw UnreadableContact::because(\sprintf('"%s" is no case of an enum implementing %s', $reference, $interface));
    }
}
