<?php

declare(strict_types=1);

namespace Phil\AuditBundle\Domain\Read;

use Phil\AuditBundle\Domain\Chain\AuditRow;
use Phil\DddBundle\Domain\Exception\InvalidValue;

/**
 * Where a page stopped: the key of its last row. The next page starts after it, on the index, however many rows
 * came before: no offset is ever counted. Its token — instant, segment and sequence joined by `~` — travels in a
 * query string, and is checked when it comes back.
 */
final readonly class Cursor
{
    private function __construct(
        public string $instant,
        public string $segment,
        public int $sequence,
    ) {
    }

    public static function after(AuditRow $row): self
    {
        return new self($row->instant(), $row->segment, $row->sequence);
    }

    /**
     * @throws InvalidValue when the token is not one {@see self::token()} gave
     */
    public static function fromToken(string $token): self
    {
        if (1 !== preg_match('/^(\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}\.\d{6}Z)~([a-z0-9][a-z0-9._-]{0,63})~([1-9]\d{0,17})$/D', $token, $parts)) {
            throw InvalidValue::because('The page cursor is not valid.');
        }

        return new self($parts[1], $parts[2], (int) $parts[3]);
    }

    /**
     * The cursor as a token for a URL.
     */
    public function token(): string
    {
        return \sprintf('%s~%s~%d', $this->instant, $this->segment, $this->sequence);
    }
}
