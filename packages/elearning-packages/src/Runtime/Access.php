<?php

declare(strict_types=1);

namespace Phil\ElearningPackages\Runtime;

/**
 * Who may read and write an element of the data model: the content reads a read-only element the platform sets, writes
 * a write-only one the platform alone reads, and both read and write the others.
 */
enum Access: string
{
    case ReadOnly = 'ro';
    case WriteOnly = 'wo';
    case ReadWrite = 'rw';

    public function readable(): bool
    {
        return self::WriteOnly !== $this;
    }

    public function writable(): bool
    {
        return self::ReadOnly !== $this;
    }
}
