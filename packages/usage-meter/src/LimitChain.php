<?php

declare(strict_types=1);

namespace Phil\UsageMeter;

use Override;

/**
 * Several limit providers asked in turn; the first that names a limit wins. An override an administrator set comes
 * before the subscription's features, for example.
 */
final readonly class LimitChain implements LimitProvider
{
    /** @var array<LimitProvider> */
    private array $providers;

    public function __construct(LimitProvider ...$providers)
    {
        $this->providers = $providers;
    }

    #[Override]
    public function limitFor(string $subject, Meter $meter): ?Limit
    {
        foreach ($this->providers as $provider) {
            $limit = $provider->limitFor($subject, $meter);

            if ($limit instanceof Limit) {
                return $limit;
            }
        }

        return null;
    }
}
