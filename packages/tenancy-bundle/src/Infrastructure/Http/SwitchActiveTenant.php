<?php

declare(strict_types=1);

namespace Phil\TenancyBundle\Infrastructure\Http;

use Phil\DddBundle\Domain\Exception\InvalidValue;
use Phil\TenancyBundle\Application\ActiveTenant;
use Phil\TenancyBundle\Domain\Exception\TenantNotReachable;
use Phil\TenancyBundle\Domain\ValueObject\TenantId;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * The switch the selector posts to: the `tenant` field, checked against the reachable tenants, then a redirect
 * to the `target` field — a path of this site — or to the site's root.
 *
 * The form is accepted from this site only: `Sec-Fetch-Site: same-origin` when the browser sends it, otherwise
 * an `Origin` that is the site's own. A tenant the account cannot reach, or a malformed reference, is a 403 and
 * leaves the remembered choice as it was.
 */
final readonly class SwitchActiveTenant
{
    public const string ROUTE = 'phil_tenancy_switch';

    public function __construct(
        private ActiveTenant $activeTenant,
    ) {
    }

    public function __invoke(Request $request): RedirectResponse
    {
        $this->assertSameOrigin($request);

        try {
            $this->activeTenant->switchTo(TenantId::fromString($request->request->getString('tenant')));
        } catch (InvalidValue|TenantNotReachable) {
            throw new AccessDeniedHttpException('This tenant is not reachable.');
        }

        return new RedirectResponse($this->target($request));
    }

    private function assertSameOrigin(Request $request): void
    {
        $site = $request->headers->get('Sec-Fetch-Site');
        $sameOrigin = null === $site ? $request->headers->get('Origin') === $request->getSchemeAndHttpHost() : 'same-origin' === $site;

        if (!$sameOrigin) {
            throw new AccessDeniedHttpException('The form was not posted from this site.');
        }
    }

    /**
     * A path of this site, never an address elsewhere: `//host` and `/\host` are read as other hosts by browsers,
     * and browsers drop tabs and line breaks from a URL before reading it, so `/<TAB>/host` is `//host` to them.
     * A target is therefore refused when it holds a control character or whitespace anywhere — a requested URI is
     * percent-encoded and never does — or when it is not valid UTF-8.
     */
    private function target(Request $request): string
    {
        $target = $request->request->getString('target');

        return 1 === preg_match('#\A/(?![/\\\\])[^\p{Cc}\s]*\z#u', $target) ? $target : $request->getBasePath().'/';
    }
}
