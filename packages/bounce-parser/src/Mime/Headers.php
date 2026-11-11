<?php

declare(strict_types=1);

namespace Phil\BounceParser\Mime;

/**
 * The header fields of a message, a MIME part or a group of report fields, in their order, each value unfolded and
 * trimmed. Names are compared without case.
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
     * Reads header lines: a line that starts with a space or a tab continues the field before it, a line without a
     * colon is skipped, and reading stops after `$limit` fields.
     */
    public static function parse(string $text, int $limit = 500): self
    {
        $fields = [];
        $current = null;

        foreach (explode("\n", str_replace("\r\n", "\n", $text)) as $line) {
            if (null !== $current && ('' !== $line && (' ' === $line[0] || "\t" === $line[0]))) {
                $current[1] .= ' '.trim($line);

                continue;
            }

            if (null !== $current) {
                $fields[] = [$current[0], trim($current[1])];
                $current = null;
            }

            $colon = strpos($line, ':');

            if (false !== $colon && \count($fields) < $limit && 1 === preg_match('/^[!-9;-~]+$/', substr($line, 0, $colon))) {
                $current = [substr($line, 0, $colon), substr($line, $colon + 1)];
            }
        }

        if (null !== $current) {
            $fields[] = [$current[0], trim($current[1])];
        }

        return new self($fields);
    }

    /**
     * The value of the first field of that name, or `null`.
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
     * The value of the first field of that name with its encoded words (RFC 2047) decoded to UTF-8, for a subject or a
     * display name.
     */
    public function decoded(string $name): ?string
    {
        $value = $this->get($name);

        if (null === $value || !str_contains($value, '=?')) {
            return $value;
        }

        $decoded = iconv_mime_decode($value, \ICONV_MIME_DECODE_CONTINUE_ON_ERROR, 'UTF-8');

        return false === $decoded ? $value : $decoded;
    }
}
