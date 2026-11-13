<?php

declare(strict_types=1);

namespace Phil\AccessBundle\Infrastructure\Session;

use Override;
use Phil\AccessBundle\Application\Contract\ViewAsStore;
use Phil\AccessBundle\Domain\Exception\InvalidAccess;
use Phil\AccessBundle\Domain\ValueObject\MemberId;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * The member viewed, in the session of the current request; nothing without a request or a session. The service keeps
 * nothing itself, so it is safe in a long-running worker.
 */
final readonly class SessionViewAsStore implements ViewAsStore
{
    public const string ATTRIBUTE = '_phil_access_view_as';

    public function __construct(
        private RequestStack $requests,
    ) {
    }

    #[Override]
    public function get(): ?MemberId
    {
        $request = $this->requests->getCurrentRequest();

        if (!$request instanceof Request || !$request->hasSession()) {
            return null;
        }

        $value = $request->getSession()->get(self::ATTRIBUTE);

        try {
            return \is_string($value) ? MemberId::of($value) : null;
        } catch (InvalidAccess) {
            return null;
        }
    }

    #[Override]
    public function set(?MemberId $member): void
    {
        $request = $this->requests->getCurrentRequest();

        if (!$request instanceof Request || !$request->hasSession()) {
            return;
        }

        if (!$member instanceof MemberId) {
            $request->getSession()->remove(self::ATTRIBUTE);

            return;
        }

        $request->getSession()->set(self::ATTRIBUTE, $member->value);
    }
}
