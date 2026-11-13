<?php

declare(strict_types=1);

namespace Phil\PagedMedia\Gotenberg\Signing;

/**
 * An RFC 3161 timestamp authority, which vouches that a PDF or a signature existed, unchanged, at a time. Gotenberg
 * calls it itself, within what its operator allows.
 */
interface TimestampAuthority
{
    /**
     * The authority's `http://` or `https://` URL.
     */
    public function url(): string;
}
