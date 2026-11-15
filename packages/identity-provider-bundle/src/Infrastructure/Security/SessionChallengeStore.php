<?php

declare(strict_types=1);

namespace Phil\IdentityProviderBundle\Infrastructure\Security;

use Override;
use Phil\IdentityProviderBundle\Application\Contract\ChallengeStore;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * The challenges in the session of the current request, under `_phil_identity_provider.<purpose>`: they belong to
 * the browser that started the ceremony, and leave with the session.
 */
final readonly class SessionChallengeStore implements ChallengeStore
{
    private const string PREFIX = '_phil_identity_provider.';

    public function __construct(
        private RequestStack $requests,
    ) {
    }

    #[Override]
    public function put(string $purpose, string $value): void
    {
        $this->requests->getSession()->set(self::PREFIX.$purpose, $value);
    }

    #[Override]
    public function take(string $purpose): ?string
    {
        $value = $this->requests->getSession()->remove(self::PREFIX.$purpose);

        return \is_string($value) ? $value : null;
    }

    #[Override]
    public function peek(string $purpose): ?string
    {
        $value = $this->requests->getSession()->get(self::PREFIX.$purpose);

        return \is_string($value) ? $value : null;
    }
}
