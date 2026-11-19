<?php

declare(strict_types=1);

namespace Phil\DocumentImport\Internal\Pdf;

use Generator;
use Phil\DocumentImport\Exception\LimitExceeded;

/**
 * The objects of a PDF file read from its bytes: numbers, strings — literal or hexadecimal —, names, arrays,
 * dictionaries, references, and the keywords and operators between them; a content stream read as its operations.
 * Arrays and dictionaries nest no deeper than the limit, and a malformed value reads as `null` rather than failing.
 *
 * @internal
 */
final class PdfParser
{
    private const string WHITESPACE = "\0\t\n\f\r ";

    private const string DELIMITERS = "\0\t\n\f\r ()<>[]{}/%";

    private readonly int $length;

    public function __construct(
        private readonly string $bytes,
        public int $at = 0,
        private readonly int $depth = 100,
    ) {
        $this->length = \strlen($bytes);
    }

    /**
     * The operations of a content stream, each operator with its operands; the data of an inline image skipped.
     *
     * @return Generator<int, array{string, list<mixed>}>
     *
     * @throws LimitExceeded
     */
    public function operations(): Generator
    {
        $operands = [];

        while ($this->at < $this->length) {
            $value = $this->value();

            if (!$value instanceof PdfKeyword) {
                // Operands after the last operator are left with it: nothing reads them.
                $operands[] = $value;

                continue;
            }

            if ('ID' === $value->word) {
                $this->skipInlineImage();
            }

            yield [$value->word, $operands];
            $operands = [];
        }
    }

    /**
     * The next value; `R` after two integers makes a reference.
     *
     * @throws LimitExceeded
     */
    public function value(int $level = 0): mixed
    {
        if ($level > $this->depth) {
            throw LimitExceeded::because(\sprintf('The PDF nests arrays and dictionaries deeper than %d.', $this->depth));
        }

        $this->skipSpace();

        if ($this->at >= $this->length) {
            return null;
        }

        $char = $this->bytes[$this->at];

        return match (true) {
            '/' === $char => $this->name(),
            '(' === $char => $this->literal(),
            '<' === $char => '<' === ($this->bytes[$this->at + 1] ?? '') ? $this->dictionary($level) : $this->hex(),
            '[' === $char => $this->array($level),
            str_contains('0123456789+-.', $char) => $this->number(),
            default => $this->keyword(),
        };
    }

    public function skipSpace(): void
    {
        while ($this->at < $this->length) {
            $this->at += strspn($this->bytes, self::WHITESPACE, $this->at);

            if ('%' !== ($this->bytes[$this->at] ?? '')) {
                return;
            }

            $this->at += strcspn($this->bytes, "\r\n", $this->at);
        }
    }

    private function name(): PdfName
    {
        ++$this->at;
        $run = strcspn($this->bytes, self::DELIMITERS, $this->at);
        $name = substr($this->bytes, $this->at, $run);
        $this->at += $run;

        return new PdfName(str_contains($name, '#') ? (string) preg_replace_callback('/#([0-9a-fA-F]{2})/', static fn (array $match): string => \chr((int) hexdec($match[1]) & 0xFF), $name) : $name);
    }

    private function literal(): string
    {
        ++$this->at;
        $nesting = 1;
        $text = '';

        while ($this->at < $this->length) {
            $run = strcspn($this->bytes, '()\\', $this->at);
            $text .= substr($this->bytes, $this->at, $run);
            $this->at += $run;
            $char = $this->bytes[$this->at] ?? '';
            ++$this->at;

            if ('\\' === $char) {
                $text .= $this->escape();

                continue;
            }

            $nesting += '(' === $char ? 1 : -1;

            if (0 === $nesting || '' === $char) {
                break;
            }

            $text .= $char;
        }

        return $text;
    }

    private function escape(): string
    {
        $char = $this->bytes[$this->at] ?? '';
        ++$this->at;

        if (1 === preg_match('/\G[0-7]{1,3}/', $this->bytes, $match, 0, $this->at - 1)) {
            $this->at += \strlen($match[0]) - 1;

            return \chr(octdec($match[0]) & 0xFF);
        }

        return match ($char) {
            'n' => "\n",
            'r' => "\r",
            't' => "\t",
            'b' => "\x08",
            'f' => "\f",
            "\r" => "\n" === ($this->bytes[$this->at] ?? '') ? $this->skipOne() : '',
            "\n" => '',
            default => $char,
        };
    }

    private function skipOne(): string
    {
        ++$this->at;

        return '';
    }

    private function hex(): string
    {
        ++$this->at;
        $end = strpos($this->bytes, '>', $this->at);
        $end = false === $end ? $this->length : $end;

        $digits = (string) preg_replace('/[^0-9a-fA-F]/', '', substr($this->bytes, $this->at, $end - $this->at));
        $this->at = $end + 1;

        return sodium_hex2bin(1 === \strlen($digits) % 2 ? $digits.'0' : $digits);
    }

    /**
     * @return list<mixed>
     *
     * @throws LimitExceeded
     */
    private function array(int $level): array
    {
        ++$this->at;
        $items = [];

        while (true) {
            $this->skipSpace();

            if ($this->at >= $this->length) {
                return $items;
            }

            if (']' === $this->bytes[$this->at]) {
                ++$this->at;

                return $items;
            }

            $start = $this->at;
            $items[] = $this->value($level + 1);
            $this->reference($items);

            if ($this->at === $start) {
                ++$this->at;
            }
        }
    }

    /**
     * @throws LimitExceeded
     */
    private function dictionary(int $level): PdfDictionary
    {
        $this->at += 2;
        $entries = [];

        while (true) {
            $this->skipSpace();

            if ($this->at >= $this->length || '>>' === substr($this->bytes, $this->at, 2)) {
                $this->at += 2;

                return new PdfDictionary($entries);
            }

            $start = $this->at;
            $key = $this->value($level + 1);
            $value = $this->value($level + 1);

            if ($value instanceof PdfKeyword) {
                $value = null;
            }

            if (\is_int($value)) {
                $value = $this->maybeReference($value);
            }

            if ($key instanceof PdfName) {
                $entries[$key->name] = $value;
            }

            if ($this->at === $start) {
                ++$this->at;
            }
        }
    }

    /**
     * The last two integers of a list read as a reference when an `R` follows them.
     *
     * @param list<mixed> $items
     */
    private function reference(array &$items): void
    {
        $count = \count($items);

        if ($count >= 3 && $items[$count - 1] instanceof PdfKeyword && 'R' === $items[$count - 1]->word && \is_int($items[$count - 2]) && \is_int($items[$count - 3])) {
            array_splice($items, -3, null, [new PdfReference($items[$count - 3], $items[$count - 2])]);
        }
    }

    /**
     * An integer, or a reference when another integer and `R` follow it.
     */
    private function maybeReference(int $number): int|PdfReference
    {
        if (1 === preg_match('/\G\s+(\d+)\s+R(?=[\s\/\[\]<>()%]|$)/', $this->bytes, $match, 0, $this->at)) {
            $this->at += \strlen($match[0]);

            return new PdfReference($number, (int) $match[1]);
        }

        return $number;
    }

    /**
     * A number; a run of signs, digits and points that is none, as damaged files hold, reads as 0.
     */
    private function number(): int|float
    {
        $run = strspn($this->bytes, '0123456789+-.', $this->at);
        $token = substr($this->bytes, $this->at, $run);
        $this->at += $run;

        if (!is_numeric($token)) {
            return 0;
        }

        return str_contains($token, '.') ? (float) $token : (int) $token;
    }

    private function keyword(): bool|PdfKeyword|null
    {
        $run = max(1, strcspn($this->bytes, self::DELIMITERS, $this->at));
        $word = substr($this->bytes, $this->at, $run);
        $this->at += $run;

        return match ($word) {
            'true' => true,
            'false' => false,
            'null' => null,
            default => new PdfKeyword($word),
        };
    }

    /**
     * The data of an inline image, after `ID`, up to its `EI`.
     */
    private function skipInlineImage(): void
    {
        if (1 === preg_match('/[\0\t\n\f\r ]EI(?=[\0\t\n\f\r ]|$)/', $this->bytes, $match, \PREG_OFFSET_CAPTURE, $this->at)) {
            // The space before `EI` is skipped with the others.
            $this->at = $match[0][1];

            return;
        }

        $this->at = $this->length;
    }
}
