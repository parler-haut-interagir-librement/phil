<?php

declare(strict_types=1);

namespace Phil\Ujo\Json;

use JsonException;
use Phil\Ujo\Document;
use Phil\Ujo\Exception\InvalidDocument;
use stdClass;

/**
 * Writes a document as UJO JSON, in canonical form: UTF-8 and slashes unescaped, and the empty values of the
 * keys that hold objects — a block's `data` and `tunes`, a list's `meta`, an image's `file` — written `{}` rather than
 * `[]`.
 */
final readonly class JsonEncoder
{
    /** The keys whose empty value is an object. */
    public const array OBJECT_KEYS = ['data', 'tunes', 'meta', 'file', 'settings'];

    /**
     * @throws InvalidDocument when a text of the document is not valid UTF-8
     */
    public function encode(Document $document, bool $pretty = false): string
    {
        $flags = \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES | \JSON_PRESERVE_ZERO_FRACTION;

        try {
            return json_encode(self::objects($document->toArray()), $pretty ? $flags | \JSON_PRETTY_PRINT : $flags);
        } catch (JsonException $jsonException) {
            throw InvalidDocument::at('blocks', 'it cannot be written as JSON: '.lcfirst($jsonException->getMessage()));
        }
    }

    private static function objects(mixed $value, int|string|null $key = null): mixed
    {
        if (!\is_array($value)) {
            return $value;
        }

        if ([] === $value && \in_array($key, self::OBJECT_KEYS, true)) {
            return new stdClass();
        }

        $objects = [];

        foreach ($value as $name => $item) {
            $objects[$name] = self::objects($item, $name);
        }

        return $objects;
    }
}
