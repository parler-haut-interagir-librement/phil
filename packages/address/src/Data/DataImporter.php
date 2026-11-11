<?php

declare(strict_types=1);

namespace Phil\Address\Data;

use FilesystemIterator;
use JsonException;
use Phil\Address\AddressField;
use Phil\Address\Exception\InvalidData;
use SplFileInfo;
use Throwable;

/**
 * Turns the address data of commerceguys/addressing into the library's: one PHP file per country's format, `ZZ.php`
 * for the countries without one, and one per list of subdivisions, named by its parents — `CA.php`, `BR-AC.php`.
 *
 * A format keeps its fields as `AddressField` values, its patterns, its types, and how deep its subdivisions go; a
 * subdivision keeps its identifier, its codes and names — local ones too —, its postal code pattern, and whether
 * it has children.
 */
final class DataImporter
{
    private const array TYPES = [
        'administrative_area_type' => ['administrativeAreaType', 'province'],
        'locality_type' => ['localityType', 'city'],
        'dependent_locality_type' => ['dependentLocalityType', 'suburb'],
        'postal_code_type' => ['postalCodeType', 'postal'],
    ];

    private const array FOLDERS = ['formats', 'subdivisions'];

    /**
     * Writes the files in a staging folder inside `$directory`, then puts its `formats/` and `subdivisions/` in place of
     * those already there. Data refused halfway leave the folders already there as they were.
     *
     * @param array<string, array<string, mixed>> $definitions  the formats by country code, each completed by the generic one
     * @param array<string, mixed>                $generic      the format of the other countries
     * @param iterable<string, string>            $subdivisions the JSON of each list, by its file name without extension
     *
     * @return int the files written
     */
    public function import(array $definitions, array $generic, iterable $subdivisions, string $directory): int
    {
        $staging = \sprintf('%s/.import-%d', $directory, random_int(\PHP_INT_MIN, \PHP_INT_MAX));

        foreach (self::FOLDERS as $folder) {
            mkdir($staging.'/'.$folder, recursive: true);
        }

        try {
            $count = $this->writeAll($definitions, $generic, $subdivisions, $staging);
        } catch (Throwable $throwable) {
            $this->remove($staging);

            throw $throwable;
        }

        foreach (self::FOLDERS as $folder) {
            $this->remove($directory.'/'.$folder);
            rename($staging.'/'.$folder, $directory.'/'.$folder);
        }

        rmdir($staging);

        return $count;
    }

    /**
     * @param array<string, array<string, mixed>> $definitions
     * @param array<string, mixed>                $generic
     * @param iterable<string, string>            $subdivisions
     */
    private function writeAll(array $definitions, array $generic, iterable $subdivisions, string $directory): int
    {
        $count = 0;

        // A country's definition takes what it does not say from the generic one, as commerceguys/addressing does.
        foreach (['ZZ' => $generic, ...$definitions] as $country => $definition) {
            $this->write($directory.'/formats/'.$country.'.php', $this->format($country, $definition + $generic));
            ++$count;
        }

        foreach ($subdivisions as $name => $json) {
            $this->write($directory.'/subdivisions/'.$name.'.php', $this->subdivisions($name, $json));
            ++$count;
        }

        return $count;
    }

    /**
     * Removes a folder the importer writes, which holds files only, or one of its own folders of such folders.
     */
    private function remove(string $folder): void
    {
        if (!is_dir($folder)) {
            return;
        }

        /** @var iterable<SplFileInfo> $entries */
        $entries = new FilesystemIterator($folder);

        foreach ($entries as $entry) {
            $entry->isDir() ? $this->remove($entry->getPathname()) : unlink($entry->getPathname());
        }

        rmdir($folder);
    }

    /**
     * @param array<string, mixed> $definition
     *
     * @return array<string, mixed>
     */
    private function format(string $country, array $definition): array
    {
        if (1 !== preg_match('/^[A-Z]{2}$/', $country) || !\is_string($definition['format'] ?? null)) {
            throw InvalidData::because(\sprintf('The address format of "%s" has no country code or no format.', $country));
        }

        $format = [
            'country' => $country,
            'format' => $definition['format'],
            'localFormat' => $this->string($definition, 'local_format'),
            'locale' => $this->string($definition, 'locale'),
            'required' => $this->fields($definition['required_fields'] ?? []),
            'uppercase' => $this->fields($definition['uppercase_fields'] ?? []),
            'postalCodePattern' => $this->string($definition, 'postal_code_pattern'),
            'postalCodePrefix' => $this->string($definition, 'postal_code_prefix'),
        ];

        foreach (self::TYPES as $key => [$name, $default]) {
            $format[$name] = $this->string($definition, $key) ?? $default;
        }

        $depth = $definition['subdivision_data_fields'] ?? [];
        $defaults = $definition['default_values'] ?? [];

        return [
            ...$format,
            'subdivisionDepth' => \is_array($depth) ? \count($depth) : 0,
            'defaults' => \is_array($defaults) ? array_filter($defaults, \is_string(...)) : [],
        ];
    }

    /**
     * @return array{locale: ?string, subdivisions: list<array<string, mixed>>}
     */
    private function subdivisions(string $name, string $json): array
    {
        try {
            $data = json_decode($json, true, flags: \JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            $data = null;
        }

        if (1 !== preg_match('/^[A-Z]{2}(-[A-Za-z0-9]*)*$/', $name) || !\is_array($data) || !\is_array($data['subdivisions'] ?? null)) {
            throw InvalidData::because(\sprintf('The subdivisions "%s" are not commerceguys/addressing\'s.', $name));
        }

        $subdivisions = [];

        foreach ($data['subdivisions'] as $id => $definition) {
            $definition = \is_array($definition) ? $definition : [];
            $label = $this->string($definition, 'name') ?? (string) $id;
            $code = $this->string($definition, 'code') ?? $label;

            $subdivisions[] = [
                'id' => (string) $id,
                'code' => $code,
                'localCode' => $this->string($definition, 'local_code'),
                'name' => $label,
                'localName' => $this->string($definition, 'local_name'),
                'isoCode' => $this->string($definition, 'iso_code'),
                'postalCodePattern' => $this->string($definition, 'postal_code_pattern'),
                'hasChildren' => true === ($definition['has_children'] ?? false),
            ];
        }

        return ['locale' => $this->string($data, 'locale'), 'subdivisions' => $subdivisions];
    }

    /**
     * @return list<string>
     */
    private function fields(mixed $fields): array
    {
        $known = array_map(static fn (AddressField $field): string => $field->value, AddressField::cases());

        return \is_array($fields) ? array_values(array_filter($fields, static fn (mixed $field): bool => \in_array($field, $known, true))) : [];
    }

    /**
     * @param array<array-key, mixed> $data
     */
    private function string(array $data, string $key): ?string
    {
        $value = $data[$key] ?? null;

        return \is_string($value) && '' !== $value ? $value : null;
    }

    private function write(string $file, mixed $data): void
    {
        file_put_contents($file, "<?php\n\ndeclare(strict_types=1);\n\n// Generated from commerceguys/addressing's data (MIT License) by Phil\\Address\\Data\\DataImporter.\nreturn ".$this->export($data).";\n");
    }

    private function export(mixed $value): string
    {
        return match (true) {
            \is_array($value) => '['.implode(', ', array_map(
                fn (int|string $key): string => (array_is_list($value) ? '' : $this->export($key).' => ').$this->export($value[$key]),
                array_keys($value),
            )).']',
            \is_string($value) => "'".addcslashes($value, "'\\")."'",
            \is_int($value) => (string) $value,
            \is_bool($value) => $value ? 'true' : 'false',
            default => 'null',
        };
    }
}
