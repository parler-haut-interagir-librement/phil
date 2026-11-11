<?php

declare(strict_types=1);

namespace Phil\FeatureFlagsBundle\Domain\Flag;

/**
 * A flag a provider holds but cannot read as a definition (a stored row edited by hand), with the reason it gives.
 *
 * The name is kept as the provider stores it, which may not even be a valid flag name: that can be the reason.
 */
final readonly class UnreadableFlag
{
    public function __construct(
        public string $name,
        public string $reason,
    ) {
    }
}
