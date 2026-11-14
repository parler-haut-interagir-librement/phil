<?php

declare(strict_types=1);

namespace Phil\TenancyBundle\Infrastructure\Messenger;

use Phil\DddBundle\Domain\Exception\InvalidValue;
use Phil\TenancyBundle\Domain\ValueObject\TenantId;
use Symfony\Component\Messenger\Stamp\StampInterface;

/**
 * The tenant a message was dispatched in, kept as its reference — an identifier, at most
 * {@see TenantId::MAX_LENGTH} bytes, and nothing else of the tenant — so that every transport serializer carries it
 * and the message stays short.
 */
final readonly class TenantStamp implements StampInterface
{
    /**
     * @throws InvalidValue when the reference is not a valid tenant reference
     */
    public function __construct(
        public string $tenant,
    ) {
        TenantId::fromString($tenant);
    }
}
