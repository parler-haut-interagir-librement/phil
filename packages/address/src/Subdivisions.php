<?php

declare(strict_types=1);

namespace Phil\Address;

/**
 * The subdivisions of a country, or of a subdivision, read from `data/subdivisions/` as asked for.
 */
final class Subdivisions
{
    private const string DATA = __DIR__.'/../data/subdivisions';

    /** @var array<string, list<Subdivision>> */
    private static array $lists = [];

    /** @var array<string, array<string, Subdivision>> by list, then by each key of a subdivision, the first one wins */
    private static array $indexes = [];

    /**
     * The subdivisions below a country and the identifiers of its subdivisions down to the list: `of('BR', 'AC')`.
     *
     * @return list<Subdivision>
     */
    public static function of(string $country, string ...$parents): array
    {
        return self::$lists[self::load($country, array_values($parents))];
    }

    /**
     * The subdivision a value of an address names, among those below the parents: the first whose identifier, code or
     * name it is, whatever the case. Each list is indexed once, the first time a value is looked up in it.
     */
    public static function find(string $value, string $country, string ...$parents): ?Subdivision
    {
        $name = self::load($country, array_values($parents));

        if (!isset(self::$indexes[$name])) {
            $index = [];

            foreach (self::$lists[$name] as $subdivision) {
                $index += array_fill_keys($subdivision->keys(), $subdivision);
            }

            self::$indexes[$name] = $index;
        }

        return self::$indexes[$name][mb_strtolower(trim($value))] ?? null;
    }

    /**
     * The name of a list, read once.
     *
     * @param list<string> $parents
     */
    private static function load(string $country, array $parents): string
    {
        $name = self::file(Countries::code($country), $parents);

        if (!isset(self::$lists[$name])) {
            $file = self::DATA.'/'.$name.'.php';
            self::$lists[$name] = 1 === preg_match('/[\/\\\\]/', $name) || !is_file($file) ? [] : self::read($file);
        }

        return $name;
    }

    /**
     * The file of a list, named as commerceguys/addressing names it: the country, then a parent of up to three letters
     * as it is — `BR-AC` —, or a dash per parent and a hash of them — `CN--…` — deeper down.
     *
     * @param list<string> $parents
     */
    private static function file(string $country, array $parents): string
    {
        return match (true) {
            [] === $parents => $country,
            1 === \count($parents) && \strlen($parents[0]) <= 3 => $country.'-'.$parents[0],
            default => $country.str_repeat('-', \count($parents)).hash('tiger128,3', implode('-', $parents)),
        };
    }

    /**
     * @return list<Subdivision>
     */
    private static function read(string $file): array
    {
        /** @var array{locale: ?string, subdivisions: list<array{id: string, code: string, localCode: ?string, name: string, localName: ?string, isoCode: ?string, postalCodePattern: ?string, hasChildren: bool}>} $data */
        $data = require $file;

        return array_map(static fn (array $subdivision): Subdivision => new Subdivision(
            $subdivision['id'],
            $subdivision['code'],
            $subdivision['localCode'],
            $subdivision['name'],
            $subdivision['localName'],
            $subdivision['isoCode'],
            $subdivision['postalCodePattern'],
            $subdivision['hasChildren'],
            $data['locale'],
        ), $data['subdivisions']);
    }
}
