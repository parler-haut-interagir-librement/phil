<?php

declare(strict_types=1);

namespace Phil\Contact;

use Phil\Address\Address;
use Phil\Address\AddressField;
use Phil\InstantMessenger\Handle;
use Phil\Phone\PhoneFormat;
use Phil\Url\TypedUrl;

/**
 * A card written as JSContact (RFC 9553), version 1.0: what JMAP Contacts and recent address books exchange.
 *
 * The name is written in its components, in the order its culture displays it, with its full form and, when its
 * culture sorts it otherwise than it is written, `sortAs`; the culture of a structured name is the card's `language`.
 * Phone lines carry the feature of their role, handles are `onlineServices` named by their enum case, web addresses
 * are `links`, and addresses are written in components: each address line a `name`, the postal code and then the
 * sorting code each a `postcode`. The recipient fields of an address are left out, the card naming its owner. The
 * unmapped properties follow, as they were read.
 */
final class JSContactWriter
{
    /**
     * @return array<string, mixed>
     */
    public static function toArray(JSContactCard $document): array
    {
        $card = $document->card;
        $person = $card->isPerson() || null === $card->organization;
        $name = $card->structuredName;
        $language = $name->culture ?? $document->unmapped['language'] ?? null;

        return array_filter([
            '@type' => 'Card',
            'version' => '1.0',
            'uid' => $document->uid,
            'kind' => $person ? 'individual' : 'org',
            'language' => $language,
            'name' => null === $card->displayName() ? null : ($name instanceof PersonName ? self::name($name, (string) $card->name) : ['@type' => 'Name', 'full' => $card->displayName()]),
            'organizations' => self::map('o', null === $card->organization ? [] : [['@type' => 'Organization', 'name' => $card->organization]]),
            'titles' => self::map('t', null === $card->title ? [] : [['@type' => 'Title', 'name' => $card->title]]),
            'emails' => self::map('e', array_map(static fn (Email $email): array => ['@type' => 'EmailAddress', 'address' => $email->value], $card->emails)),
            'phones' => self::map('p', array_map(self::phone(...), $card->phones)),
            'onlineServices' => self::map('s', array_map(self::service(...), $card->handles)),
            'links' => self::map('l', array_map(static fn (TypedUrl $url): array => ['@type' => 'Link', 'uri' => $url->url->toString()], $card->urls)),
            'addresses' => self::map('a', array_map(self::address(...), $document->addresses)),
        ], static fn (mixed $value): bool => null !== $value && [] !== $value) + $document->unmapped;
    }

    /**
     * The card as JSON, slashes and characters beyond ASCII written as they are.
     */
    public static function toJson(JSContactCard $document): string
    {
        return json_encode(self::toArray($document), \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE | \JSON_PRESERVE_ZERO_FRACTION);
    }

    /**
     * @return array<string, mixed>
     */
    private static function name(PersonName $name, string $full): array
    {
        $parts = [['given', $name->given], ...array_map(static fn (string $additional): array => ['given2', $additional], $name->additional), ['surname', $name->surname()], ['surname2', $name->secondFamily]];

        if (NameOrder::FamilyFirst === $name->displayOrder()) {
            // Family name, additional names, given name: as `PersonName::display()` writes it.
            $parts = [$parts[\count($parts) - 2], $parts[\count($parts) - 1], ...\array_slice($parts, 1, -2), $parts[0]];
        }

        $components = [];

        foreach ([['title', $name->honorific], ...$parts, ['generation', $name->generation], ['credential', $name->credential]] as [$kind, $value]) {
            if (null !== $value) {
                $components[] = ['kind' => $kind, 'value' => $value];
            }
        }

        $sorted = $name->sortFamily();

        return array_filter([
            '@type' => 'Name',
            'components' => $components,
            'isOrdered' => true,
            'defaultSeparator' => $name->separator(),
            'full' => $full,
            'sortAs' => null === $sorted || $sorted === $name->surname() ? null : ['surname' => $sorted],
        ], static fn (mixed $value): bool => null !== $value);
    }

    /**
     * @return array<string, mixed>
     */
    private static function phone(PhoneLine $line): array
    {
        return ['@type' => 'Phone', 'number' => $line->number->format(PhoneFormat::Rfc3966), 'features' => [match ($line->role) {
            PhoneRole::Voice => 'voice',
            PhoneRole::Mobile => 'mobile',
            PhoneRole::Fax => 'fax',
            PhoneRole::Text => 'text',
            PhoneRole::Pager => 'pager',
        } => true]];
    }

    /**
     * @return array<string, mixed>
     */
    private static function service(Handle $handle): array
    {
        return array_filter(['@type' => 'OnlineService', 'service' => $handle->type->name, 'user' => $handle->value, 'uri' => $handle->link()], static fn (?string $value): bool => null !== $value);
    }

    /**
     * @return array<string, mixed>
     */
    private static function address(Address $address): array
    {
        $kinds = [
            AddressField::AddressLine1->value => 'name',
            AddressField::AddressLine2->value => 'name',
            AddressField::AddressLine3->value => 'name',
            AddressField::DependentLocality->value => 'district',
            AddressField::Locality->value => 'locality',
            AddressField::AdministrativeArea->value => 'region',
            AddressField::PostalCode->value => 'postcode',
            AddressField::SortingCode->value => 'postcode',
        ];
        $components = [];

        foreach (array_intersect_key($address->fields(), $kinds) as $field => $value) {
            $components[] = ['kind' => $kinds[$field], 'value' => $value];
        }

        return ['@type' => 'Address', 'components' => $components, 'countryCode' => $address->country];
    }

    /**
     * Entries keyed by their prefix and their position, from 1: `e1`, `e2`.
     *
     * @param list<array<string, mixed>> $entries
     *
     * @return array<string, array<string, mixed>>
     */
    private static function map(string $prefix, array $entries): array
    {
        $map = [];

        foreach ($entries as $index => $entry) {
            $map[$prefix.($index + 1)] = $entry;
        }

        return $map;
    }
}
