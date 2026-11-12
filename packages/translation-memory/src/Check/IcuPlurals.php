<?php

declare(strict_types=1);

namespace Phil\TranslationMemory\Check;

/**
 * The plural arguments of an ICU message — `{count, plural, one {# point} other {# points}}` — and whether its braces
 * are balanced.
 *
 * Text between apostrophes is literal, as ICU reads it: `'{'` is a brace, `''` an apostrophe. Arguments nest at most
 * sixteen deep; deeper, the message counts as unbalanced.
 *
 * @internal
 */
final class IcuPlurals
{
    public const int MAX_DEPTH = 16;

    private int $at = 0;

    private readonly int $length;

    /**
     * @var list<array{string, list<string>}>
     */
    private array $plurals = [];

    private function __construct(private string $text)
    {
        $this->length = \strlen($this->text);
    }

    /**
     * The plural arguments of a message, each with its name and its selectors in order, nested ones included, and
     * whether the message is well formed.
     *
     * @return array{list<array{string, list<string>}>, bool}
     */
    public static function read(string $message): array
    {
        $parser = new self($message);
        $balanced = $parser->message(0) && $parser->at >= $parser->length;

        return [$parser->plurals, $balanced];
    }

    /**
     * Reads a message up to the brace that closes it, or to the end at the top; whether it is well formed. A brace
     * that closes nothing stops the top message with the text left to read, which the caller takes as not balanced.
     */
    private function message(int $depth): bool
    {
        while ($this->at < $this->length) {
            $character = $this->text[$this->at];

            if ("'" === $character) {
                $this->quoted();
            } elseif ('{' === $character) {
                ++$this->at;

                if ($depth >= self::MAX_DEPTH || !$this->argument($depth + 1)) {
                    return false;
                }
            } elseif ('}' === $character) {
                return true;
            } else {
                ++$this->at;
            }
        }

        return 0 === $depth;
    }

    /**
     * Reads an argument after its opening brace, through its closing one.
     */
    private function argument(int $depth): bool
    {
        $name = trim($this->until(',}'));

        if ($this->at >= $this->length) {
            return false;
        }

        if ('}' === $this->text[$this->at++]) {
            return '' !== $name;
        }

        $type = trim($this->until(',}'));

        if ($this->at >= $this->length) {
            return false;
        }

        if ('}' === $this->text[$this->at++]) {
            return '' !== $name;
        }

        if (!\in_array($type, ['plural', 'select', 'selectordinal'], true)) {
            $this->until('}');

            return $this->at < $this->length && '}' === $this->text[$this->at++];
        }

        $selectors = [];

        while (true) {
            $this->spaces();

            if ($this->at >= $this->length) {
                return false;
            }

            if ('}' === $this->text[$this->at]) {
                ++$this->at;

                break;
            }

            $selector = $this->until("{} \t\r\n");
            $this->spaces();

            if (str_starts_with($selector, 'offset:')) {
                continue;
            }

            if ('' === $selector || '{' !== ($this->text[$this->at] ?? '')) {
                return false;
            }

            ++$this->at;

            if (!$this->message($depth)) {
                return false;
            }

            ++$this->at;
            $selectors[] = $selector;
        }

        if ([] === $selectors) {
            return false;
        }

        if ('plural' === $type) {
            $this->plurals[] = [$name, $selectors];
        }

        return true;
    }

    /**
     * The text up to one of the characters, which is left to read.
     */
    private function until(string $characters): string
    {
        $span = strcspn($this->text, $characters, $this->at);
        $read = substr($this->text, $this->at, $span);
        $this->at += $span;

        return $read;
    }

    private function spaces(): void
    {
        $this->at += strspn($this->text, " \t\r\n", $this->at);
    }

    /**
     * Steps over an apostrophe: two of them are one, and one before a brace opens literal text up to the next.
     */
    private function quoted(): void
    {
        $next = $this->text[$this->at + 1] ?? '';

        if ("'" === $next) {
            $this->at += 2;
        } elseif ('{' === $next || '}' === $next) {
            $end = strpos($this->text, "'", $this->at + 1);
            $this->at = false === $end ? $this->length : $end + 1;
        } else {
            ++$this->at;
        }
    }
}
