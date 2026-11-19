<?php

declare(strict_types=1);

namespace Phil\UserSecretsBundle\Tests\Unit\Fixture;

use Closure;
use Override;
use Phil\UserSecretsBundle\Application\Contract\TokenRefresher;
use Phil\UserSecretsBundle\Domain\Credential\OAuthToken;
use Phil\UserSecretsBundle\Domain\ValueObject\ServiceName;

/**
 * Serves one service, and returns the token it was given, keeping the tokens it was asked to refresh. What it runs
 * while the service answers stands for another process at work meanwhile.
 */
final class FixedRefresher implements TokenRefresher
{
    /**
     * @var list<OAuthToken>
     */
    public array $refreshed = [];

    public function __construct(
        private readonly string $service,
        private readonly OAuthToken $fresh,
        private ?Closure $meanwhile = null,
    ) {
    }

    #[Override]
    public function supports(ServiceName $service): bool
    {
        return $service->value === $this->service;
    }

    #[Override]
    public function refresh(ServiceName $service, OAuthToken $token): OAuthToken
    {
        $this->refreshed[] = $token;
        $meanwhile = $this->meanwhile;
        // Once only: the other process does not wait on itself.
        $this->meanwhile = null;

        if ($meanwhile instanceof Closure) {
            $meanwhile();
        }

        return $this->fresh;
    }
}
