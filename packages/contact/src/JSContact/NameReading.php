<?php

declare(strict_types=1);

namespace Phil\Contact\JSContact;

use Phil\Contact\Exception\InvalidContact;
use Phil\Contact\Exception\UnreadableJSContact;
use Phil\Contact\NameOrder;
use Phil\Contact\PersonName;

/**
 * The `name` of a JSContact card: its full form, and the structured name of a person from its components.
 *
 * @internal
 */
final class NameReading
{
    /**
     * Where each kind of name component goes; `given2` adds an additional name.
     */
    private const array PARTS = ['title' => 'honorific', 'given' => 'given', 'surname' => 'surname', 'surname2' => 'secondFamily', 'generation' => 'generation', 'credential' => 'credential'];

    /**
     * The full name, the structured name, and whether the card's language became the culture of the name.
     *
     * @param array<string, mixed> $card
     *
     * @return array{?string, ?PersonName, bool}
     */
    public static function read(Reading $reading, array $card, bool $organisation, ?string $language): array
    {
        if (!\array_key_exists('name', $card)) {
            return [null, null, false];
        }

        $name = $reading->object($card['name'], '/name', 'Name');
        $full = $reading->string($name, 'full', '/name');
        $components = $reading->list($name, 'components', '/name');
        $ordered = $name['isOrdered'] ?? false;
        $sortAs = \array_key_exists('sortAs', $name) ? $reading->object($name['sortAs'], '/name/sortAs') : [];
        $reading->unknown($name, ['@type', 'full', 'components', 'isOrdered', 'defaultSeparator', 'sortAs'], '/name');

        if (!\is_bool($ordered)) {
            throw UnreadableJSContact::because('/name/isOrdered is not a boolean');
        }

        if ([] === $components || $organisation) {
            self::unstructured($reading, $name, $sortAs, [] !== $components);

            return [$full, null, false];
        }

        [$parts, $positions] = self::components($reading, $components);
        $sorted = $reading->string($sortAs, 'surname', '/name/sortAs');
        $reading->unknown($sortAs, ['surname'], '/name/sortAs');
        $person = self::person($reading, $parts, $sorted, $language);

        if (!$person instanceof PersonName) {
            return [$full, null, false];
        }

        if ($ordered && isset($positions['given'], $positions['surname'])) {
            $order = $positions['surname'] < $positions['given'] ? NameOrder::FamilyFirst : NameOrder::GivenFirst;
            $person = $order === $person->displayOrder() ? $person : PersonName::of($person->given, $person->family, $person->particle, $person->additional, $person->secondFamily, $person->honorific, $person->generation, $person->credential, $person->culture, $order);
        }

        if (null !== $sorted && $sorted !== $person->sortFamily()) {
            $reading->notice('/name/sortAs/surname', 'the name is sorted as its culture sorts it');
        }

        $separator = $reading->string($name, 'defaultSeparator', '/name');

        if (null !== $separator && $separator !== $person->separator()) {
            $reading->notice('/name/defaultSeparator', 'the name is separated as its culture writes it');
        }

        return [$full, $person, null !== $person->culture];
    }

    /**
     * The notices of a name kept in its full form only: its display hints, its sort forms, and the components of an
     * organisation's card.
     *
     * @param array<string, mixed> $name
     * @param array<string, mixed> $sortAs
     */
    private static function unstructured(Reading $reading, array $name, array $sortAs, bool $hasComponents): void
    {
        foreach (['isOrdered', 'defaultSeparator'] as $hint) {
            if (\array_key_exists($hint, $name)) {
                $reading->notice('/name/'.$hint, 'a name without components is written as it is');
            }
        }

        $reading->unknown($sortAs, [], '/name/sortAs');

        if ($hasComponents) {
            $reading->notice('/name/components', 'the card of an organisation names no person');
        }
    }

    /**
     * The value of each part, the additional names, and the position of each kind kept.
     *
     * @param list<mixed> $components
     *
     * @return array{array{additional: list<string>, honorific?: string, given?: string, surname?: string, secondFamily?: string, generation?: string, credential?: string}, array<string, int>}
     */
    private static function components(Reading $reading, array $components): array
    {
        $parts = ['additional' => []];
        $positions = [];

        foreach ($components as $index => $entry) {
            $pointer = '/name/components/'.$index;
            $component = $reading->object($entry, $pointer);
            $kind = $reading->required($component, 'kind', $pointer);
            $value = $reading->required($component, 'value', $pointer);
            $reading->unknown($component, ['kind', 'value'], $pointer);
            $part = self::PARTS[$kind] ?? null;

            if ('given2' === $kind && \count($parts['additional']) < PersonName::MAX_ADDITIONAL) {
                $parts['additional'][] = $value;
            } elseif (null !== $part && !isset($parts[$part])) {
                $parts[$part] = $value;
                $positions[$kind] = $index;
            } else {
                $reading->notice($pointer, match (true) {
                    'separator' === $kind => 'separators follow the convention of the name',
                    'given2' === $kind => \sprintf('a name keeps %d additional names', PersonName::MAX_ADDITIONAL),
                    null === $part => \sprintf('"%s" is no kind of name component the card keeps', mb_substr($kind, 0, 100)),
                    default => \sprintf('the name keeps one %s', $kind),
                });
            }
        }

        return [$parts, $positions];
    }

    /**
     * The structured name, in the card's language when it can take it as its culture; none, with a notice, when the
     * components make no name.
     *
     * @param array{additional: list<string>, honorific?: string, given?: string, surname?: string, secondFamily?: string, generation?: string, credential?: string} $parts
     */
    private static function person(Reading $reading, array $parts, ?string $sorted, ?string $language): ?PersonName
    {
        [$particle, $family] = self::particle($parts['surname'] ?? null, $sorted);
        $build = static fn (?string $culture): PersonName => PersonName::of($parts['given'] ?? null, $family, $particle, $parts['additional'], $parts['secondFamily'] ?? null, $parts['honorific'] ?? null, $parts['generation'] ?? null, $parts['credential'] ?? null, $culture);

        try {
            return $build($language);
        } catch (InvalidContact $invalidContact) {
            try {
                // A language the name cannot take as its culture: the name without it.
                $person = null === $language ? throw $invalidContact : $build(null);
                $reading->notice('/language', $invalidContact->getMessage());

                return $person;
            } catch (InvalidContact $invalidContact) {
                $reading->notice('/name/components', $invalidContact->getMessage());

                return null;
            }
        }
    }

    /**
     * The particle and the family name of a surname, split where its sort form starts: `van Gogh` sorted as `Gogh`.
     *
     * @return array{?string, ?string}
     */
    private static function particle(?string $surname, ?string $sorted): array
    {
        if (null === $surname || null === $sorted || $surname === $sorted || !str_ends_with($surname, $sorted)) {
            return [null, $surname];
        }

        $particle = substr($surname, 0, -\strlen($sorted));

        return match (true) {
            str_ends_with($particle, ' ') => [$particle, $sorted],
            str_ends_with($particle, "'"), str_ends_with($particle, '’') => [$particle, $sorted],
            default => [null, $surname],
        };
    }
}
