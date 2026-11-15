<?php

declare(strict_types=1);

namespace Phil\TimedText\Internal;

use JsonException;
use Phil\TimedText\Exception\InvalidTimedText;
use Phil\TimedText\Exception\LimitExceeded;
use Phil\TimedText\Limits;

/**
 * JSON read within the limits, its nesting bounded, and written as the Podcasting 2.0 files are. Not part of the
 * public API.
 *
 * @internal
 */
final class Json
{
    private const int DEPTH = 8;

    private function __construct()
    {
    }

    /**
     * The object at the top of the document, with the list it must hold under `$key`.
     *
     * @return array{array<array-key, mixed>, list<mixed>}
     *
     * @throws InvalidTimedText when the document is not a JSON object with that list
     * @throws LimitExceeded    when it is larger than the limits allow
     */
    public static function document(string $input, string $key, Limits $limits): array
    {
        try {
            $data = json_decode(Input::text($input, $limits), true, self::DEPTH, \JSON_THROW_ON_ERROR | \JSON_BIGINT_AS_STRING);
        } catch (JsonException $jsonException) {
            throw InvalidTimedText::at(null, \sprintf('The document is not JSON, or nests deeper than %d levels.', self::DEPTH), $jsonException);
        }

        if (!\is_array($data) || array_is_list($data) || !\is_array($data[$key] ?? null) || !array_is_list($data[$key])) {
            throw InvalidTimedText::at(null, \sprintf('The document is a JSON object with a "%s" list.', $key));
        }

        if (\count($data[$key]) > $limits->maxCues) {
            throw LimitExceeded::count($key, $limits->maxCues);
        }

        return [$data, $data[$key]];
    }

    /**
     * A string field, checked against the text limit; null when it is missing.
     *
     * @param array<array-key, mixed> $data
     *
     * @return string|false|null false when present but not a string
     */
    public static function string(array $data, string $key, Limits $limits): string|false|null
    {
        $value = $data[$key] ?? null;

        if (null === $value) {
            return null;
        }

        return \is_string($value) ? Input::checkText($value, null, $limits) : false;
    }

    /**
     * @param array<array-key, mixed> $data
     */
    public static function encode(array $data): string
    {
        return json_encode($data, \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE | \JSON_THROW_ON_ERROR)."\n";
    }
}
