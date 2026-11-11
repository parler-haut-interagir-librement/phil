<?php

declare(strict_types=1);

namespace Phil\Markup\Reader\Wiki;

use Phil\Markup\Reader\BBCode\Node;
use Phil\Markup\Reader\BBCode\Options;

/**
 * An extension tag of a wiki, read from what stands between its delimiters: `note title="Title" wide`, `/note`, or
 * `ref name=a/`. Its name is in lower case; its attributes are `key=value`, their value in quotes when it holds spaces,
 * and its flags single words.
 *
 * @internal
 */
final readonly class Tag
{
    private const string NAME = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';

    /**
     * @param array<string, string> $attributes
     * @param list<string>          $flags
     */
    public function __construct(
        public string $name,
        public array $attributes = [],
        public array $flags = [],
        public bool $closing = false,
        public bool $void = false,
    ) {
    }

    /**
     * The tag written between its delimiters; `null` when it does not start with a name, or a closing tag holds more.
     */
    public static function parse(string $inside): ?self
    {
        $closing = str_starts_with($inside, '/');
        $body = $closing ? substr($inside, 1) : $inside;
        $void = !$closing && str_ends_with($body, '/');
        $body = $void ? substr($body, 0, -1) : $body;
        $length = strspn($body, self::NAME);
        $rest = substr($body, $length);

        if (0 === $length || ('' !== $rest && ' ' !== $rest[0]) || ($closing && '' !== trim($rest))) {
            return null;
        }

        [, $attributes, $flags] = Options::parse($rest);

        return new self(strtolower(substr($body, 0, $length)), $attributes, $flags, $closing, $void);
    }

    /**
     * The name, after a `/` for a closing tag: what ends a container.
     */
    public function key(): string
    {
        return ($this->closing ? '/' : '').$this->name;
    }

    public function attribute(string $name): string
    {
        return $this->attributes[$name] ?? '';
    }

    public function first(): ?string
    {
        return $this->flags[0] ?? null;
    }

    /**
     * The tag as the BBCode reader knows it, for the blocks both readers make the same way.
     */
    public function node(?string $option): Node
    {
        return new Node($this->name, $option, $this->attributes, $this->flags);
    }
}
