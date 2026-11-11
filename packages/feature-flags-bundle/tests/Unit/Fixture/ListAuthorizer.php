<?php

declare(strict_types=1);

namespace Phil\FeatureFlagsBundle\Tests\Unit\Fixture;

use Override;
use Phil\FeatureFlagsBundle\Application\Toggle\ToggleAuthorizer;
use Phil\FeatureFlagsBundle\Domain\Edit\EditRequest;
use Phil\FeatureFlagsBundle\Domain\Exception\ToggleRefused;
use Phil\FeatureFlagsBundle\Domain\Toggle\ToggleRequest;

/**
 * Authorizes the listed actors only.
 */
final readonly class ListAuthorizer implements ToggleAuthorizer
{
    /**
     * @param list<string> $actors
     */
    public function __construct(
        private array $actors,
    ) {
    }

    #[Override]
    public function authorize(ToggleRequest $request): void
    {
        if (!\in_array($request->actor, $this->actors, true)) {
            throw ToggleRefused::forFlag($request->flag, 'the actor lacks the permission.');
        }
    }

    #[Override]
    public function authorizeEdit(EditRequest $request): void
    {
        if (!\in_array($request->actor, $this->actors, true)) {
            throw ToggleRefused::forFlag($request->flag(), 'the actor lacks the permission.');
        }
    }
}
