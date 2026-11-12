<?php

declare(strict_types=1);

namespace Phil\ApiContract\OpenApi;

use Generator;
use JsonException;
use Phil\ApiContract\Exception\UnreadableDocument;

/**
 * An OpenAPI document read for verification: the decoded object, its operations, and the local references it holds.
 *
 * Reading is bounded: a JSON text of at most 8 MiB, at most 128 levels deep and 500 000 values. A YAML document is
 * parsed by the caller (Symfony's Yaml component does it) and given to `fromArray()`. Only local references, those
 * that start with `#`, are followed; an external reference is left as it is.
 */
final readonly class OpenApiDocument
{
    public const int MAX_BYTES = 8 * 1024 * 1024;

    public const int MAX_DEPTH = 128;

    public const int MAX_VALUES = 500_000;

    /**
     * The methods of a path item, in the order OpenAPI lists them; `query` is OpenAPI 3.2's.
     */
    public const array METHODS = ['get', 'put', 'post', 'delete', 'options', 'head', 'patch', 'trace', 'query'];

    /**
     * How many references a chain may follow before it is taken for a loop.
     */
    private const int MAX_HOPS = 16;

    /**
     * @param array<array-key, mixed> $data
     */
    private function __construct(public array $data)
    {
    }

    public static function fromJson(string $json): self
    {
        if (\strlen($json) > self::MAX_BYTES) {
            throw UnreadableDocument::because(\sprintf('The document is larger than %d bytes.', self::MAX_BYTES));
        }

        try {
            $data = json_decode($json, true, self::MAX_DEPTH, \JSON_THROW_ON_ERROR | \JSON_BIGINT_AS_STRING);
        } catch (JsonException $jsonException) {
            throw UnreadableDocument::because('The document is not JSON: '.$jsonException->getMessage().'.');
        }

        if (!\is_array($data)) {
            throw UnreadableDocument::because('The document is not a JSON object.');
        }

        return self::fromArray($data);
    }

    /**
     * @param array<array-key, mixed> $data the decoded document, from JSON or YAML
     */
    public static function fromArray(array $data): self
    {
        if ([] !== $data && array_is_list($data)) {
            throw UnreadableDocument::because('The document is a list, not an object.');
        }

        $values = 0;
        $stack = [[$data, 1]];

        while ([] !== $stack) {
            [$node, $depth] = array_pop($stack);

            if ($depth > self::MAX_DEPTH) {
                throw UnreadableDocument::because(\sprintf('The document is deeper than %d levels.', self::MAX_DEPTH));
            }

            foreach ($node as $value) {
                if (++$values > self::MAX_VALUES) {
                    throw UnreadableDocument::because(\sprintf('The document holds more than %d values.', self::MAX_VALUES));
                }

                if (\is_array($value)) {
                    $stack[] = [$value, $depth + 1];
                }
            }
        }

        return new self($data);
    }

    /**
     * The JSON Pointer (RFC 6901) of these reference tokens: `pointer('paths', '/orders')` is `/paths/~1orders`.
     */
    public static function pointer(string|int ...$tokens): string
    {
        return implode('', array_map(static fn (string|int $token): string => '/'.str_replace(['~', '/'], ['~0', '~1'], (string) $token), $tokens));
    }

    /**
     * The value a local reference points at, or `null` when it points at nothing: `#/components/schemas/Order`.
     */
    public function at(string $reference): mixed
    {
        if (!str_starts_with($reference, '#')) {
            return null;
        }

        $pointer = rawurldecode(substr($reference, 1));

        if ('' === $pointer) {
            return $this->data;
        }

        if (!str_starts_with($pointer, '/')) {
            return null;
        }

        $node = $this->data;

        foreach (explode('/', substr($pointer, 1)) as $token) {
            $token = str_replace(['~1', '~0'], ['/', '~'], $token);

            if (!\is_array($node) || !\array_key_exists($token, $node)) {
                return null;
            }

            $node = $node[$token];
        }

        return $node;
    }

    /**
     * The node itself, or, when it is a reference object, what its chain of local references ends at; `null` when
     * the chain breaks, leaves the document or loops.
     */
    public function resolve(mixed $node): mixed
    {
        for ($hops = 0; \is_array($node) && \array_key_exists('$ref', $node); ++$hops) {
            if (self::MAX_HOPS === $hops || !\is_string($node['$ref'])) {
                return null;
            }

            $node = $this->at($node['$ref']);
        }

        return $node;
    }

    /**
     * Every operation of `paths`, path by path in the document's order.
     *
     * @return list<Operation>
     */
    public function operations(): array
    {
        $operations = [];
        $paths = $this->data['paths'] ?? null;

        foreach (\is_array($paths) ? $paths : [] as $path => $item) {
            if (!\is_array($item)) {
                continue;
            }

            foreach (self::METHODS as $method) {
                if (\is_array($item[$method] ?? null)) {
                    $operations[] = new Operation((string) $path, $method, self::pointer('paths', $path, $method), $item[$method], $item);
                }
            }
        }

        return $operations;
    }

    /**
     * Every object and list of the document with its JSON Pointer, the document itself first, depth first; the values
     * of examples (`example`, `examples`) and of extensions (`x-…`) are data, not part of the description, and are
     * left out, unless they name the properties of a schema.
     *
     * @return Generator<string, array<array-key, mixed>>
     */
    public function nodes(): Generator
    {
        return self::walk($this->data, '', false);
    }

    /**
     * @param array<array-key, mixed> $node
     *
     * @return Generator<string, array<array-key, mixed>>
     */
    private static function walk(array $node, string $pointer, bool $properties): Generator
    {
        yield $pointer => $node;

        foreach ($node as $key => $value) {
            $key = (string) $key;

            if (\is_array($value) && ($properties || ('example' !== $key && 'examples' !== $key && !str_starts_with($key, 'x-')))) {
                yield from self::walk($value, $pointer.self::pointer($key), !$properties && 'properties' === $key);
            }
        }
    }
}
