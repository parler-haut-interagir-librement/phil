<?php

declare(strict_types=1);

namespace Phil\PagedMedia\Gotenberg\Stage;

use Override;
use Phil\PagedMedia\Gotenberg\Signing\TimestampAuthority;

/**
 * Adds a document timestamp from an RFC 3161 timestamp authority: proof the PDF existed, unchanged, at that time. It
 * names no signer; to timestamp a signature, give the signature the authority instead.
 */
final readonly class Timestamp implements Stage
{
    public function __construct(public TimestampAuthority $authority)
    {
    }

    #[Override]
    public function operation(): Operation
    {
        return Operation::Timestamp;
    }

    #[Override]
    public function options(): array
    {
        return ['timestampUrl' => $this->authority->url()];
    }

    #[Override]
    public function files(): array
    {
        return [];
    }
}
