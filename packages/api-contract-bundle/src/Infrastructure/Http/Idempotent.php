<?php

declare(strict_types=1);

namespace Phil\ApiContractBundle\Infrastructure\Http;

use Attribute;
use Phil\ApiContract\Exception\InvalidContractValue;

/**
 * A controller that requires an `Idempotency-Key`: a repeated request with the same key and payload gets the first
 * response again, without running twice. `ttl` replaces the configured retention of an answered key, in seconds,
 * and is at least 1: a key kept for no time would turn idempotency off without a word.
 *
 * `anonymous` replaces the option `idempotency.anonymous` for this endpoint alone: `'refuse'` or `'ip'`, or a case of
 * {@see AnonymousCallers}. Null follows the option, which refuses anonymous callers unless it says otherwise.
 */
#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD)]
final readonly class Idempotent
{
    public ?AnonymousCallers $anonymous;

    public function __construct(
        public ?int $ttl = null,
        AnonymousCallers|string|null $anonymous = null,
    ) {
        if (null !== $ttl && $ttl < 1) {
            throw InvalidContractValue::because(\sprintf('The ttl of #[Idempotent] is at least 1 second; %d is not.', $ttl));
        }

        if (\is_string($anonymous)) {
            $anonymous = AnonymousCallers::tryFrom($anonymous) ?? throw InvalidContractValue::because(\sprintf('The anonymous of #[Idempotent] is "refuse" or "ip"; "%s" is not.', $anonymous));
        }

        $this->anonymous = $anonymous;
    }
}
