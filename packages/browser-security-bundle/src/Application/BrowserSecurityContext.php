<?php

declare(strict_types=1);

namespace Phil\BrowserSecurityBundle\Application;

use Phil\BrowserSecurityBundle\Domain\Exception\InvalidPolicy;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * The protections of the current response, from code: an override decided by a service rather than by a route or an
 * attribute. It goes to the main request, so that a fragment rendered in a sub-request changes its page.
 */
final readonly class BrowserSecurityContext
{
    public function __construct(
        private RequestStack $requests,
    ) {
    }

    /**
     * Changes the protections of the current response, after the overrides of its route and controller.
     *
     * @throws InvalidPolicy when the override changes what only a route can change
     */
    public function override(ProtectionOverride $override): void
    {
        $override->assertAfterRouting();
        $request = $this->requests->getMainRequest();

        if ($request instanceof Request) {
            RequestState::of($request)->addProtectionOverride($override);
        }
    }
}
