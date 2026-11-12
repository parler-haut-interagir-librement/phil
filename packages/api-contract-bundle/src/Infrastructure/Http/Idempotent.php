<?php

declare(strict_types=1);

namespace Phil\ApiContractBundle\Infrastructure\Http;

use Attribute;
use Phil\ApiContract\Exception\InvalidContractValue;

/**
 * A controller that requires an `Idempotency-Key`: a repeated request with the same key and payload gets the first
 * response again, without running twice. `ttl` replaces the configured retention of an answered key, in seconds,
 * and is at least 1: a key kept for no time would turn idempotency off without a word.
 */
#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD)]
final readonly class Idempotent
{
    public function __construct(
        public ?int $ttl = null,
    ) {
        if (null !== $ttl && $ttl < 1) {
            throw InvalidContractValue::because(\sprintf('The ttl of #[Idempotent] is at least 1 second; %d is not.', $ttl));
        }
    }
}
