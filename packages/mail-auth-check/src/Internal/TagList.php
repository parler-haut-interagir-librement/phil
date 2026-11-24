<?php

declare(strict_types=1);

namespace Phil\MailAuthCheck\Internal;

use Phil\MailAuthCheck\Exception\InvalidRecord;

/**
 * Reads the `tag=value; tag=value` lists of DKIM keys and DMARC records (RFC 6376, section 3.2), within bounds.
 *
 * @internal
 */
final class TagList
{
    public const int MAX_LENGTH = 4096;

    public const int MAX_TAGS = 32;

    /**
     * @return non-empty-array<string, string> the values by tag name, in the order of the record
     *
     * @throws InvalidRecord
     */
    public static function parse(string $text, string $kind): array
    {
        if (\strlen($text) > self::MAX_LENGTH) {
            throw InvalidRecord::because(\sprintf('The %s record is longer than %d bytes.', $kind, self::MAX_LENGTH));
        }

        $tags = [];

        foreach (explode(';', $text) as $spec) {
            $spec = trim($spec, " \t\r\n");

            if ('' === $spec) {
                continue;
            }

            if (self::MAX_TAGS === \count($tags)) {
                throw InvalidRecord::because(\sprintf('The %s record has more than %d tags.', $kind, self::MAX_TAGS));
            }

            $parts = explode('=', $spec, 2);
            $name = rtrim($parts[0], " \t\r\n");

            if (!isset($parts[1]) || 1 !== preg_match('/^[A-Za-z][A-Za-z0-9_]*$/D', $name)) {
                throw InvalidRecord::because(\sprintf('The %s record has a part that is not "tag=value".', $kind));
            }

            if (isset($tags[$name])) {
                throw InvalidRecord::because(\sprintf('The %s record gives the tag "%s" twice.', $kind, $name));
            }

            $tags[$name] = ltrim($parts[1], " \t\r\n");
        }

        if ([] === $tags) {
            throw InvalidRecord::because(\sprintf('The %s record is empty.', $kind));
        }

        return $tags;
    }
}
