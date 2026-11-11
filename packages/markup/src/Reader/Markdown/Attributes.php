<?php

declare(strict_types=1);

namespace Phil\Markup\Reader\Markdown;

use Phil\Markup\Lexer\Cursor;

/**
 * The attributes written in braces, in either grammar: `{.class #id}`, and `{class,#id,property:value}` of the `{{ }}`
 * blocks. A token is a class, an identifier or a CSS property; tokens are separated by commas or spaces. A token
 * that is none of them — `key=value` — is a class no look can declare, which `Layout` leaves out.
 */
final readonly class Attributes
{
    /**
     * @param list<string>          $classes
     * @param array<string, string> $styles
     */
    public function __construct(
        public string $id = '',
        public array $classes = [],
        public array $styles = [],
    ) {
    }

    public static function parse(string $text): self
    {
        $cursor = new Cursor($text);
        $id = '';
        $classes = [];
        $styles = [];
        $cursor->consumeWhile(", \t:");

        while (!$cursor->isEnd()) {
            $token = $cursor->consumeUntil(", \t");
            $colon = strpos($token, ':');

            match (true) {
                str_starts_with($token, '#') => $id = substr($token, 1),
                str_starts_with($token, '.') => $classes[] = substr($token, 1),
                false !== $colon => $styles[strtolower(substr($token, 0, $colon))] = substr($token, $colon + 1),
                default => $classes[] = $token,
            };

            $cursor->consumeWhile(", \t:");
        }

        return new self($id, $classes, $styles);
    }

    /**
     * The tags of a `{{tags content}}` span and its content: the first space outside double quotes ends the tags.
     *
     * @return array{string, string}
     */
    public static function split(string $text): array
    {
        $cursor = new Cursor($text);

        do {
            $cursor->consumeUntil(' "');
        } while ($cursor->consume('"') && null !== $cursor->consumeThrough('"'));

        return [substr($text, 0, $cursor->position()), ltrim($cursor->rest(), ' ')];
    }

    public function has(string $class): bool
    {
        return \in_array($class, $this->classes, true);
    }
}
