<?php

declare(strict_types=1);

namespace Phil\Ujo\Json;

use JsonException;
use Phil\Ujo\Document;
use Phil\Ujo\Exception\InvalidDocument;
use Phil\Ujo\Exception\InvalidJson;
use stdClass;

/**
 * Reads UJO JSON strictly: a text under a size limit, valid JSON nested no deeper than a limit, whose top level is an
 * object, and whose structure is a document — `Document::fromArray()`. The data of each block is checked afterwards,
 * by `Normalizer`.
 */
final readonly class JsonDecoder
{
    public const int DEFAULT_MAX_BYTES = 4_194_304;

    public const int DEFAULT_MAX_DEPTH = 64;

    /**
     * @param int<1, max> $maxDepth
     */
    public function __construct(
        private int $maxBytes = self::DEFAULT_MAX_BYTES,
        private int $maxDepth = self::DEFAULT_MAX_DEPTH,
    ) {
    }

    /**
     * @throws InvalidJson     when the text is too large, is not JSON, or is not an object
     * @throws InvalidDocument when the object is not a document
     */
    public function decode(string $json): Document
    {
        $bytes = \strlen($json);

        if ($bytes > $this->maxBytes) {
            throw InvalidJson::tooLarge($bytes, $this->maxBytes);
        }

        try {
            $value = json_decode($json, false, $this->maxDepth, \JSON_THROW_ON_ERROR | \JSON_BIGINT_AS_STRING);
        } catch (JsonException $jsonException) {
            throw InvalidJson::syntax($jsonException->getMessage());
        }

        if (!$value instanceof stdClass) {
            throw InvalidJson::notAnObject();
        }

        /** @var array<string, mixed> $document */
        $document = self::toArray($value);

        return Document::fromArray($document);
    }

    private static function toArray(mixed $value): mixed
    {
        if ($value instanceof stdClass) {
            $value = get_object_vars($value);
        }

        return \is_array($value) ? array_map(self::toArray(...), $value) : $value;
    }
}
