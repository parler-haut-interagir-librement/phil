<?php

declare(strict_types=1);

namespace Phil\Contact;

use Phil\Phone\PhoneFormat;
use Phil\Url\TypedUrl;

/**
 * A card as schema.org JSON-LD: a `Person` when it names one — working for its organisation, with the parts of its
 * structured name —, an `Organization` otherwise. Empty properties are left out; the others are lists.
 */
final class SchemaOrg
{
    /**
     * @return array<string, mixed>
     */
    public static function of(ContactCard $card): array
    {
        $person = $card->isPerson();
        $faxes = $card->phonesFor(PhoneRole::Fax);
        $phones = array_filter($card->phones, static fn (PhoneLine $line): bool => PhoneRole::Fax !== $line->role);
        $name = $card->structuredName;
        $suffixes = array_filter([$name?->generation, $name?->credential], static fn (?string $suffix): bool => null !== $suffix);

        return array_filter([
            '@context' => 'https://schema.org',
            '@type' => $person ? 'Person' : 'Organization',
            'name' => $card->displayName(),
            'honorificPrefix' => $name?->honorific,
            'givenName' => $name?->given,
            'additionalName' => $name->additional ?? [],
            'familyName' => $name?->familyName(),
            'honorificSuffix' => [] === $suffixes ? null : implode(', ', $suffixes),
            'jobTitle' => $person ? $card->title : null,
            'worksFor' => $person && null !== $card->organization ? ['@type' => 'Organization', 'name' => $card->organization] : null,
            'email' => array_map(strval(...), $card->emails),
            'telephone' => array_values(array_map(self::number(...), $phones)),
            'faxNumber' => array_map(self::number(...), $faxes),
            'sameAs' => array_map(static fn (TypedUrl $url): string => $url->url->toString(), $card->urls),
        ], static fn (mixed $value): bool => null !== $value && [] !== $value);
    }

    private static function number(PhoneLine $line): string
    {
        return $line->number->format(PhoneFormat::E164);
    }
}
