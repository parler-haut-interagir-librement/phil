<?php

declare(strict_types=1);

namespace Phil\Measurement\Cldr;

use JsonException;
use Phil\Measurement\Exception\InvalidUnitDefinition;

/**
 * Writes the CLDR data the library reads — unit preferences by region and usage, unit patterns by locale — as PHP
 * arrays under `data/cldr/`, from the JSON of a cldr-json release. Run by `bin/import-cldr.php`.
 *
 * Only what the library reads is kept: the categories of `CATEGORIES`, and for each unit its display name and its
 * patterns by plural category in the long, short and narrow widths.
 *
 * @internal
 */
final class CldrImporter
{
    /** The CLDR categories whose preferences and patterns are imported. */
    public const array CATEGORIES = ['area', 'consumption', 'duration', 'energy', 'length', 'mass', 'power', 'pressure', 'speed', 'temperature', 'volume'];

    private const array WIDTHS = ['long', 'short', 'narrow'];

    private const array COUNTS = ['zero', 'one', 'two', 'few', 'many', 'other'];

    /**
     * Writes `preferences.php`, `patterns/<locale>.php` and `VERSION` in `$directory`, and returns the number of
     * locales written.
     *
     * @param iterable<string, string> $units the JSON of `cldr-units-full/main/<locale>/units.json`, by CLDR locale
     *
     * @throws JsonException
     */
    public function import(string $preferences, iterable $units, string $version, string $directory): int
    {
        if (1 !== preg_match('/^\d+(?:\.\d+){0,2}$/D', $version)) {
            throw InvalidUnitDefinition::because(\sprintf('"%s" is not a CLDR version such as 49.0.0.', $version));
        }

        $this->write($directory.'/preferences.php', $this->preferences($preferences), 'The unit preferences of CLDR '.$version.', by category, usage and region.');
        $count = 0;

        foreach ($units as $locale => $json) {
            $name = str_replace('-', '_', $locale);

            if (1 !== preg_match('/^[a-z]{2,3}(?:_[A-Za-z0-9]{2,8})*$/D', $name)) {
                throw InvalidUnitDefinition::because(\sprintf('"%s" is not a CLDR locale.', $locale));
            }

            $this->write($directory.'/patterns/'.$name.'.php', $this->patterns($locale, $json), 'The unit patterns of CLDR '.$version.' for '.$name.', by width and unit.');
            ++$count;
        }

        file_put_contents($directory.'/VERSION', $version."\n");

        return $count;
    }

    /**
     * @return array<string, array<string, array<string, list<array{units: list<string>, geq?: string}>>>>
     *
     * @throws JsonException
     */
    public function preferences(string $json): array
    {
        $data = json_decode($json, true, 16, \JSON_THROW_ON_ERROR);
        $categories = \is_array($data) && \is_array($data['supplemental'] ?? null) ? ($data['supplemental']['unitPreferenceData'] ?? null) : null;

        if (!\is_array($categories)) {
            throw InvalidUnitDefinition::because('The unit preferences are read from supplemental.unitPreferenceData.');
        }

        $preferences = [];

        foreach (self::CATEGORIES as $category) {
            foreach (\is_array($categories[$category] ?? null) ? $categories[$category] : [] as $usage => $regions) {
                foreach (\is_array($regions) ? $regions : [] as $region => $candidates) {
                    foreach (\is_array($candidates) ? $candidates : [] as $candidate) {
                        $preferences[$category][(string) $usage][(string) $region][] = $this->candidate($category, $candidate);
                    }
                }
            }
        }

        return $preferences;
    }

    /**
     * @return array<string, array<string, array<string, string>>>
     *
     * @throws JsonException
     */
    public function patterns(string $locale, string $json): array
    {
        $data = json_decode($json, true, 16, \JSON_THROW_ON_ERROR);
        $units = \is_array($data) && \is_array($data['main'] ?? null) && \is_array($data['main'][$locale] ?? null) ? ($data['main'][$locale]['units'] ?? null) : null;

        if (!\is_array($units)) {
            throw InvalidUnitDefinition::because(\sprintf('The unit patterns of "%s" are read from main.%1$s.units.', $locale));
        }

        $patterns = [];

        foreach (self::WIDTHS as $width) {
            foreach (\is_array($units[$width] ?? null) ? $units[$width] : [] as $key => $unit) {
                $kept = \is_array($unit) && \in_array(explode('-', (string) $key, 2)[0], self::CATEGORIES, true) ? $this->kept($unit) : [];

                if (isset($kept['other'])) {
                    $patterns[$width][(string) $key] = $kept;
                }
            }
        }

        return $patterns;
    }

    /**
     * The display name of a unit and its patterns by plural category, those that hold the number.
     *
     * @param array<mixed> $unit
     *
     * @return array<string, string>
     */
    private function kept(array $unit): array
    {
        $kept = \is_string($unit['displayName'] ?? null) ? ['name' => $unit['displayName']] : [];

        foreach (self::COUNTS as $count) {
            $pattern = $unit['unitPattern-count-'.$count] ?? null;

            if (\is_string($pattern) && str_contains($pattern, '{0}')) {
                $kept[$count] = $pattern;
            }
        }

        return $kept;
    }

    /**
     * @return array{units: list<string>, geq?: string}
     */
    private function candidate(string $category, mixed $candidate): array
    {
        $unit = \is_array($candidate) ? ($candidate['unit'] ?? null) : null;

        if (!\is_string($unit) || 1 !== preg_match('/^[a-z0-9-]{1,128}$/D', $unit)) {
            throw InvalidUnitDefinition::because(\sprintf('A unit preference of "%s" names its unit.', $category));
        }

        $read = ['units' => array_map(static fn (string $part): string => $category.'-'.$part, explode('-and-', $unit))];
        $geq = $candidate['geq'] ?? null;

        if (\is_int($geq) || \is_float($geq)) {
            // The shortest decimal that reads back as the float the JSON wrote: `0.9`, never `0.90000000000000002`.
            $read['geq'] = rtrim(rtrim(number_format($geq, 10, '.', ''), '0'), '.');
        } elseif (\is_string($geq) && 1 === preg_match('/^\d{1,18}(?:\.\d{1,18})?$/D', $geq)) {
            $read['geq'] = $geq;
        }

        return $read;
    }

    /**
     * @param array<mixed> $data
     */
    private function write(string $file, array $data, string $description): void
    {
        if (!is_dir(\dirname($file))) {
            mkdir(\dirname($file), 0o755, true);
        }

        file_put_contents($file, "<?php\n\ndeclare(strict_types=1);\n\n// ".$description." Generated by bin/import-cldr.php.\nreturn ".self::export($data).";\n");
    }

    /**
     * A PHP literal on one line per file, in short array syntax: the data files are read, never edited by hand.
     */
    private static function export(mixed $value): string
    {
        if (!\is_array($value)) {
            // The data holds strings only: a value of another type has no place in it.
            return \is_string($value) ? "'".addcslashes($value, "'\\")."'" : throw InvalidUnitDefinition::because('The CLDR data holds strings only.');
        }

        $list = array_is_list($value);
        $items = [];

        foreach ($value as $key => $item) {
            $items[] = ($list ? '' : self::export($key).' => ').self::export($item);
        }

        return '['.implode(', ', $items).']';
    }
}
