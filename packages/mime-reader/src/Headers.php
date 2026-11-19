<?php

declare(strict_types=1);

namespace Phil\MimeReader;

use Phil\MimeReader\Internal\EncodedWords;

/**
 * The header fields of a message, of a MIME part or of a group of report fields, in their order, each value unfolded
 * and trimmed but not decoded. Names are compared without case.
 */
final readonly class Headers
{
    /**
     * @param list<array{string, string}> $fields each name as written and its value
     */
    public function __construct(
        public array $fields = [],
    ) {
    }

    /**
     * Reads header lines (RFC 5322): a line that starts with a space or a tab continues the field before it, a line
     * without a colon or whose name is not printable is skipped, and reading stops after `$limit` fields.
     */
    public static function parse(string $text, int $limit = 500): self
    {
        $fields = [];
        $name = null;
        $value = '';

        foreach (explode("\n", str_replace("\r\n", "\n", $text)) as $line) {
            if (null !== $name && '' !== $line && (' ' === $line[0] || "\t" === $line[0])) {
                $value .= $line;

                continue;
            }

            if (null !== $name) {
                $fields[] = [$name, trim($value)];
                $name = null;
            }

            $colon = strpos($line, ':');

            if (false !== $colon && \count($fields) < $limit && 1 === preg_match('/^[!-9;-~]+$/', substr($line, 0, $colon))) {
                $name = substr($line, 0, $colon);
                $value = substr($line, $colon + 1);
            }
        }

        if (null !== $name) {
            $fields[] = [$name, trim($value)];
        }

        return new self($fields);
    }

    /**
     * The value of the first field of that name, as written, or `null`.
     */
    public function get(string $name): ?string
    {
        foreach ($this->fields as [$field, $value]) {
            if (0 === strcasecmp($field, $name)) {
                return $value;
            }
        }

        return null;
    }

    /**
     * The values of every field of that name, in their order.
     *
     * @return list<string>
     */
    public function all(string $name): array
    {
        $values = [];

        foreach ($this->fields as [$field, $value]) {
            if (0 === strcasecmp($field, $name)) {
                $values[] = $value;
            }
        }

        return $values;
    }

    public function has(string $name): bool
    {
        return null !== $this->get($name);
    }

    /**
     * The value of the first field of that name with its encoded words (RFC 2047) decoded, always valid UTF-8: for a
     * subject or another field of free text.
     */
    public function decoded(string $name): ?string
    {
        $value = $this->get($name);

        return null === $value ? null : EncodedWords::decode($value);
    }

    /**
     * The first field of that name read as a value with parameters, such as `Content-Type`.
     */
    public function parameterized(string $name, int $limit = 32): ?HeaderValue
    {
        $value = $this->get($name);

        return null === $value ? null : HeaderValue::parse($value, $limit);
    }

    /**
     * The mailboxes of every field of that name, such as `To` or `Cc`, at most `$limit`: a group's name and its
     * closing semicolon are dropped, comments are skipped, and a display name is decoded.
     *
     * @return list<Mailbox>
     */
    public function mailboxes(string $name, int $limit = 1000): array
    {
        $mailboxes = [];

        foreach ($this->all($name) as $value) {
            foreach ($this->entries($value) as $entry) {
                if (\count($mailboxes) >= $limit) {
                    return $mailboxes;
                }

                $mailbox = $this->mailbox($entry);

                if ($mailbox instanceof Mailbox) {
                    $mailboxes[] = $mailbox;
                }
            }
        }

        return $mailboxes;
    }

    /**
     * The value split at the commas, colons of group names and semicolons outside quotes, angle brackets and comments,
     * comments removed, empty ones included.
     *
     * @return array<int, string>
     */
    private function entries(string $value): array
    {
        $entries = [''];
        $last = 0;
        $quoted = false;
        $angle = false;
        $comment = 0;
        $length = \strlen($value);

        for ($i = 0; $i < $length; ++$i) {
            $char = $value[$i];

            if ($comment > 0) {
                $i += '\\' === $char ? 1 : 0;
                $comment += match ($char) {
                    '(' => 1,
                    ')' => -1,
                    default => 0,
                };
            } elseif ($quoted) {
                $entries[$last] .= '\\' === $char ? $char.substr($value, ++$i, 1) : $char;
                $quoted = '"' !== $char;
            } elseif ('(' === $char) {
                $comment = 1;
            } elseif (!$angle && (',' === $char || ';' === $char || ':' === $char)) {
                $entries[$last] = ':' === $char ? '' : $entries[$last];
                $entries[] = '';
                ++$last;
            } else {
                $quoted = '"' === $char;
                $angle = '<' === $char || ($angle && '>' !== $char);
                $entries[$last] .= $char;
            }
        }

        return array_map(trim(...), $entries);
    }

    private function mailbox(string $entry): ?Mailbox
    {
        $open = strrpos($entry, '<');

        if (false === $open) {
            return str_contains($entry, '@') ? new Mailbox($entry) : null;
        }

        $close = strpos($entry, '>', $open);
        $address = trim(substr($entry, $open + 1, false === $close ? null : $close - $open - 1));
        $name = trim(substr($entry, 0, $open));

        if (\strlen($name) >= 2 && str_starts_with($name, '"') && str_ends_with($name, '"')) {
            $name = (string) preg_replace('/\\\\(.)/s', '$1', substr($name, 1, -1));
        }

        if ('' === $address) {
            return null;
        }

        return new Mailbox($address, '' === $name ? null : EncodedWords::decode($name));
    }
}
