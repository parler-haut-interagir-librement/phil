<?php

declare(strict_types=1);

namespace Phil\TenancyBundle\Infrastructure\Session;

use LogicException;
use Override;
use Phil\DddBundle\Domain\Exception\InvalidValue;
use Phil\TenancyBundle\Application\Contract\ActiveTenantStore;
use Phil\TenancyBundle\Domain\ValueObject\TenantId;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

/**
 * The remembered choice in the application's own Symfony session: one choice per browser, kept as long as the
 * session. A request without a session cookie remembers nothing and starts no session; a stored value that is
 * no longer a valid reference is dropped.
 */
final readonly class SessionActiveTenantStore implements ActiveTenantStore
{
    public const string KEY = '_phil_tenancy.active_tenant';

    public function __construct(
        private RequestStack $requests,
    ) {
    }

    #[Override]
    public function remembered(): ?TenantId
    {
        $request = $this->requests->getMainRequest();

        if (!$request instanceof Request || !$request->hasPreviousSession()) {
            return null;
        }

        $session = $request->getSession();
        $value = $session->get(self::KEY);

        if (!\is_string($value)) {
            return null;
        }

        try {
            return TenantId::fromString($value);
        } catch (InvalidValue) {
            $session->remove(self::KEY);

            return null;
        }
    }

    #[Override]
    public function remember(TenantId $tenant): void
    {
        ($this->session() ?? throw new LogicException('There is no session to remember the active tenant in.'))->set(self::KEY, $tenant->value);
    }

    #[Override]
    public function forget(): void
    {
        $this->session()?->remove(self::KEY);
    }

    private function session(): ?SessionInterface
    {
        $request = $this->requests->getMainRequest();

        return $request instanceof Request && $request->hasSession() ? $request->getSession() : null;
    }
}
