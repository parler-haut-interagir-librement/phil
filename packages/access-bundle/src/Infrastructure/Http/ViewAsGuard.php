<?php

declare(strict_types=1);

namespace Phil\AccessBundle\Infrastructure\Http;

use Phil\AccessBundle\Application\Exception\ViewAsRefused;
use Phil\AccessBundle\Application\ViewAs;
use Phil\AccessBundle\Domain\ValueObject\MemberId;
use Phil\AccessBundle\Infrastructure\Http\Attribute\AllowedDuringViewAs;
use Phil\AccessBundle\Infrastructure\Http\Attribute\RefusedDuringViewAs;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Keeps "view as this member" read-only for the whole application, not only for the checks of this bundle: while an
 * admin views the site as a member, a request whose method is not safe — `POST`, `PUT`, `PATCH`, `DELETE` — is
 * answered with a `403`, unless its controller carries `#[AllowedDuringViewAs]`; a controller that carries
 * `#[RefusedDuringViewAs]` is refused whatever the method. Each refusal is logged by identifiers alone. On
 * `kernel.controller`, after the firewall, so that the admin is known; it keeps nothing between requests.
 */
final readonly class ViewAsGuard
{
    public function __construct(
        private ViewAs $viewAs,
        private ?LoggerInterface $logger = null,
    ) {
    }

    public function onController(ControllerEvent $event): void
    {
        $request = $event->getRequest();
        $refused = [] !== $event->getAttributes(RefusedDuringViewAs::class);

        if (!$refused && ($request->isMethodSafe() || [] !== $event->getAttributes(AllowedDuringViewAs::class))) {
            return;
        }

        $member = $this->viewAs->member();

        if (!$member instanceof MemberId) {
            return;
        }

        $this->logger?->notice('A {method} request was refused while viewing the site as {member}.', ['method' => $request->getMethod(), 'member' => $member->value]);
        $refusal = ViewAsRefused::readOnly();

        throw new AccessDeniedHttpException($refusal->getMessage(), $refusal);
    }
}
