<?php

declare(strict_types=1);

namespace Phil\Citations\Internal;

use Phil\Citations\Exception\InvalidImport;
use Phil\Citations\Import\ImportLimits;

/**
 * The entries of a BibTeX file, their values still in LaTeX: `@var{key, field = value, …}` or with parentheses,
 * values in braces, in quotes, as numbers or as `@string` macros, joined by `#`. `@comment` and `@preamble` are read
 * and left out, and so is any text outside an entry, an `@` not followed by a type and a brace among it.
 *
 * @internal
 */
final class BibTeXParser
{
    private const array MONTHS = [
        'jan' => 'January', 'feb' => 'February', 'mar' => 'March', 'apr' => 'April', 'may' => 'May', 'jun' => 'June',
        'jul' => 'July', 'aug' => 'August', 'sep' => 'September', 'oct' => 'October', 'nov' => 'November',
        'dec' => 'December',
    ];

    private const string NOT_IDENTIFIER = " \t\r\n\"#%'(),={}";

    private int $position = 0;

    /** @var array<string, string> */
    private array $macros = self::MONTHS;

    private function __construct(
        private readonly string $input,
        private readonly ImportLimits $limits,
    ) {
    }

    /**
     * @return list<array{type: string, key: string, fields: array<string, string>}>
     *
     * @throws InvalidImport
     */
    public static function entries(string $input, ImportLimits $limits): array
    {
        return new self($input, $limits)->parse();
    }

    /**
     * @return list<array{type: string, key: string, fields: array<string, string>}>
     */
    private function parse(): array
    {
        $entries = [];

        while (false !== ($at = strpos($this->input, '@', $this->position))) {
            $this->position = $at + 1;
            $length = strcspn($this->input, self::NOT_IDENTIFIER, $this->position);
            $type = strtolower(substr($this->input, $this->position, $length));
            $this->position += $length;
            $this->skipSpace();

            if ('' === $type || !\in_array($this->peek(), ['{', '('], true)) {
                continue;
            }

            $close = '{' === $this->peek() ? '}' : ')';

            if ('comment' === $type) {
                $this->delimited($this->peek(), $close);

                continue;
            }

            ++$this->position;

            if ('preamble' === $type) {
                $this->value();
            } elseif ('string' === $type) {
                $name = strtolower($this->identifier('a macro name'));
                $this->expect('=');
                $this->macros[$name] = $this->value();
            } else {
                if (\count($entries) >= $this->limits->maxEntries) {
                    throw $this->error(\sprintf('the file holds more than %d entries', $this->limits->maxEntries));
                }

                $entries[] = ['type' => $type, 'key' => $this->key($close), 'fields' => $this->fields($close)];
            }

            $this->expect($close);
        }

        return $entries;
    }

    /**
     * @return array<string, string>
     */
    private function fields(string $close): array
    {
        $fields = [];

        while (true) {
            $this->skipSpace();

            if ($this->peek() === $close) {
                return $fields;
            }

            $name = strtolower($this->identifier('a field name'));
            $this->expect('=');
            $value = $this->value();
            $fields[$name] ??= $value;

            if (',' !== $this->peek()) {
                return $fields;
            }

            ++$this->position;
        }
    }

    private function key(string $close): string
    {
        $this->skipSpace();
        $length = strcspn($this->input, ",\t\n\r ".$close, $this->position);
        $key = substr($this->input, $this->position, $length);
        $this->position += $length;
        $this->skipSpace();

        if ('' === $key) {
            throw $this->error('an entry has no key');
        }

        if (',' === $this->peek()) {
            ++$this->position;
        }

        return $key;
    }

    private function value(): string
    {
        $value = '';

        while (true) {
            $this->skipSpace();
            $char = $this->peek();

            $value .= match (true) {
                '{' === $char => $this->delimited('{', '}'),
                '"' === $char => $this->delimited('"', '"'),
                ctype_digit($char) => $this->digits(),
                default => $this->macro(),
            };

            if (\strlen($value) > $this->limits->maxFieldLength) {
                throw $this->error(\sprintf('a value is longer than %d bytes', $this->limits->maxFieldLength));
            }

            $this->skipSpace();

            if ('#' !== $this->peek()) {
                return $value;
            }

            ++$this->position;
        }
    }

    private function delimited(string $open, string $close): string
    {
        $start = ++$this->position;
        $depth = 0;
        $length = \strlen($this->input);

        while ($this->position < $length) {
            $char = $this->input[$this->position];

            if ('\\' === $char) {
                $this->position += 2;

                continue;
            }

            if (0 === $depth && $char === $close) {
                return substr($this->input, $start, $this->position++ - $start);
            }

            if ('{' === $char && ++$depth > $this->limits->maxDepth) {
                throw $this->error(\sprintf('braces are nested deeper than %d', $this->limits->maxDepth));
            }

            if ('}' === $char && --$depth < 0) {
                throw $this->error('a closing brace has no opening one');
            }

            ++$this->position;
        }

        throw $this->error(\sprintf('a value opened with %s is not closed', $open));
    }

    private function digits(): string
    {
        $length = strspn($this->input, '0123456789', $this->position);
        $digits = substr($this->input, $this->position, $length);
        $this->position += $length;

        return $digits;
    }

    private function macro(): string
    {
        $name = strtolower($this->identifier('a value'));

        return $this->macros[$name] ?? throw $this->error(\sprintf('the macro "%s" is not defined', $name));
    }

    private function identifier(string $what): string
    {
        $this->skipSpace();
        $length = strcspn($this->input, self::NOT_IDENTIFIER, $this->position);

        if (0 === $length) {
            throw $this->error(\sprintf('%s is expected', $what));
        }

        $identifier = substr($this->input, $this->position, $length);
        $this->position += $length;

        return $identifier;
    }

    private function expect(string $char): void
    {
        $this->skipSpace();

        if ($this->peek() !== $char) {
            throw $this->error(\sprintf('"%s" is expected', $char));
        }

        ++$this->position;
    }

    private function peek(): string
    {
        return $this->input[$this->position] ?? '';
    }

    private function skipSpace(): void
    {
        $this->position += strspn($this->input, " \t\r\n", $this->position);
    }

    private function error(string $problem): InvalidImport
    {
        $line = substr_count($this->input, "\n", 0, min($this->position, \strlen($this->input))) + 1;

        return InvalidImport::at('BibTeX', $line, $problem);
    }
}
