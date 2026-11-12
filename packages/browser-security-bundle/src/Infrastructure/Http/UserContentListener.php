<?php

declare(strict_types=1);

namespace Phil\BrowserSecurityBundle\Infrastructure\Http;

use Phil\BrowserSecurityBundle\Application\Attribute\UserContent;
use Phil\BrowserSecurityBundle\Application\UserContentPolicy;
use Phil\BrowserSecurityBundle\Domain\Exception\InvalidPolicy;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;

/**
 * Applies the policy of user content to the response of a main request whose route has the default
 * `_user_content: true` or whose controller has `#[UserContent]`, before the listeners of the other headers, which then
 * keep what it wrote.
 *
 * The attribute sets the request attribute the route default would; nothing is kept between requests.
 */
final readonly class UserContentListener
{
    public function __construct(
        private UserContentPolicy $policy,
    ) {
    }

    public function onKernelController(ControllerEvent $event): void
    {
        if ($event->isMainRequest() && [] !== $event->getAttributes(UserContent::class)) {
            $event->getRequest()->attributes->set(UserContentPolicy::ROUTE_DEFAULT, true);
        }
    }

    /**
     * @throws InvalidPolicy when the route default is not a boolean
     */
    public function onKernelResponse(ResponseEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $applies = $event->getRequest()->attributes->get(UserContentPolicy::ROUTE_DEFAULT, false);

        if (!\is_bool($applies)) {
            throw new InvalidPolicy(\sprintf('The route default "%s" is true or false.', UserContentPolicy::ROUTE_DEFAULT));
        }

        if ($applies) {
            $this->policy->apply($event->getResponse());
        }
    }
}
