<?php

declare(strict_types=1);

namespace Phil\IpAnonymizer;

use Override;
use Phil\IpAnonymizer\Contract\IpAnonymizer;
use Phil\IpAnonymizer\Contract\IpTransformer;

/**
 * Transformers run in their order, then the anonymizer that makes the text: the digest of a truncated address counts
 * a network rather than a host.
 */
final readonly class Pipeline implements IpAnonymizer
{
    /**
     * @var array<IpTransformer>
     */
    private array $steps;

    public function __construct(
        private IpAnonymizer $anonymizer,
        IpTransformer ...$steps,
    ) {
        $this->steps = $steps;
    }

    #[Override]
    public function anonymize(IpAddress $address): string
    {
        foreach ($this->steps as $step) {
            $address = $step->transform($address);
        }

        return $this->anonymizer->anonymize($address);
    }
}
