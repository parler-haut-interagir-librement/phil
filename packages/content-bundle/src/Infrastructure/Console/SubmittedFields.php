<?php

declare(strict_types=1);

namespace Phil\ContentBundle\Infrastructure\Console;

use JsonException;
use Phil\ContentBundle\Domain\Exception\InvalidContent;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

/**
 * The submitted data of a console command: a YAML or JSON file, then `--field` and `--json` options over it.
 *
 * - `--file=news.yaml` (or `.yml`, `.json`): a map of the keys of the normalized form.
 * - `--field=key=value`: a string value; `--field=templateData.title=Bonjour` sets a key inside a map.
 * - `--json=key=<json>`: any value, typed — `--json=seoNoIndex=true`, `--json=slug=null`,
 *   `--json='seoKeywords=["larp","2026"]'`.
 *
 * Options are applied in order after the file, so an option overrides the file.
 */
final class SubmittedFields
{
    /**
     * @param list<string> $fields
     * @param list<string> $json
     *
     * @return array<string, mixed>
     *
     * @throws InvalidContent
     */
    public static function read(?string $file, array $fields, array $json): array
    {
        $data = null === $file ? [] : self::file($file);

        foreach ($fields as $field) {
            [$key, $value] = self::pair($field, '--field');
            $data = self::set($data, $key, $value);
        }

        foreach ($json as $field) {
            [$key, $value] = self::pair($field, '--json');

            try {
                $decoded = json_decode($value, true, flags: \JSON_THROW_ON_ERROR);
            } catch (JsonException $jsonException) {
                throw InvalidContent::because(\sprintf('The value of "%s" is not JSON: %s.', $key, $jsonException->getMessage()));
            }

            $data = self::set($data, $key, $decoded);
        }

        return $data;
    }

    /**
     * @return array<string, mixed>
     */
    private static function file(string $file): array
    {
        if (!is_file($file)) {
            throw InvalidContent::because(\sprintf('The file "%s" does not exist.', $file));
        }

        $extension = strtolower(pathinfo($file, \PATHINFO_EXTENSION));

        try {
            $data = match ($extension) {
                'json' => json_decode((string) file_get_contents($file), true, flags: \JSON_THROW_ON_ERROR),
                'yaml', 'yml' => Yaml::parseFile($file),
                default => throw InvalidContent::because(\sprintf('The file "%s" is neither YAML (.yaml, .yml) nor JSON (.json).', $file)),
            };
        } catch (JsonException|ParseException $exception) {
            throw InvalidContent::because(\sprintf('The file "%s" cannot be parsed: %s', $file, $exception->getMessage()));
        }

        return self::map($data, $file);
    }

    /**
     * @return array<string, mixed>
     */
    private static function map(mixed $data, string $source): array
    {
        if (!\is_array($data)) {
            throw InvalidContent::because(\sprintf('"%s" must hold a map of keys to values.', $source));
        }

        $map = [];

        foreach ($data as $key => $value) {
            if (!\is_string($key)) {
                throw InvalidContent::because(\sprintf('"%s" must hold a map of keys to values; got the key %d.', $source, $key));
            }

            $map[$key] = $value;
        }

        return $map;
    }

    /**
     * @return array{string, string}
     */
    private static function pair(string $option, string $name): array
    {
        $parts = explode('=', $option, 2);

        if (2 !== \count($parts) || '' === $parts[0]) {
            throw InvalidContent::because(\sprintf('%s takes "key=value"; got "%s".', $name, $option));
        }

        return [$parts[0], $parts[1]];
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    private static function set(array $data, string $path, mixed $value): array
    {
        $keys = explode('.', $path);
        $key = array_shift($keys);

        if ([] === $keys) {
            $data[$key] = $value;

            return $data;
        }

        $inner = $data[$key] ?? [];
        $data[$key] = self::set(self::map($inner, $key), implode('.', $keys), $value);

        return $data;
    }
}
