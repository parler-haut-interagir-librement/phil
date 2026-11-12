<?php

declare(strict_types=1);

namespace Phil\IdentityClientBundle\Application\Refresh;

use SensitiveParameter;

/**
 * The result of a refresh, as the other requests of the session must apply it.
 */
final readonly class RefreshOutcome
{
    /**
     * @param array<string, mixed> $attributes the attributes of the security token the refresh changed
     */
    public function __construct(
        public RefreshStatus $status,
        #[SensitiveParameter]
        public array $attributes = [],
    ) {
    }
}
