<?php

declare(strict_types=1);

namespace Phil\UserSecretsBundle\Tests\Unit\Fixture;

use Override;
use Phil\UserSecretsBundle\Application\Contract\TokenRevoker;
use Phil\UserSecretsBundle\Domain\Credential\OAuthToken;
use Phil\UserSecretsBundle\Domain\ValueObject\ServiceName;
use Throwable;

/**
 * Serves one service and keeps the access tokens it was asked to revoke, or fails as it is told.
 */
final class RecordingRevoker implements TokenRevoker
{
    /**
     * @var list<string>
     */
    public array $revoked = [];

    public function __construct(
        private readonly string $service,
        private readonly ?Throwable $failure = null,
    ) {
    }

    #[Override]
    public function supports(ServiceName $service): bool
    {
        return $service->value === $this->service;
    }

    #[Override]
    public function revoke(ServiceName $service, OAuthToken $token): void
    {
        $this->revoked[] = $token->accessToken->reveal();

        if ($this->failure instanceof Throwable) {
            throw $this->failure;
        }
    }
}
