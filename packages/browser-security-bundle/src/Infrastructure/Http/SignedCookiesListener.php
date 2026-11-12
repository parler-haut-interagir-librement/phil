<?php

declare(strict_types=1);

namespace Phil\BrowserSecurityBundle\Infrastructure\Http;

use Phil\BrowserSecurityBundle\Application\CookieSigner;
use Phil\BrowserSecurityBundle\Application\RequestState;
use Phil\BrowserSecurityBundle\Domain\Enum\Protection;
use Phil\BrowserSecurityBundle\Domain\Syntax\ProtectionSyntax;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;

/**
 * Signs the named cookies on the way out and checks them on the way in: the application reads and writes plain values,
 * and a cookie whose signature fails is taken away from the request, as if the browser had not sent it.
 */
final readonly class SignedCookiesListener
{
    /**
     * @param list<string> $names the cookies signed on every page
     */
    public function __construct(
        private CookieSigner $signer,
        private array $names = [],
    ) {
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $cookies = $request->cookies->all();

        foreach ($this->names($request) as $name) {
            if (!isset($cookies[$name])) {
                continue;
            }

            $value = \is_string($cookies[$name]) ? $this->signer->verify($name, $cookies[$name]) : null;
            $request->cookies->remove($name);

            if (null !== $value) {
                $request->cookies->set($name, $value);
            }
        }
    }

    public function onKernelResponse(ResponseEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $names = $this->names($event->getRequest());
        $headers = $event->getResponse()->headers;

        foreach ($headers->getCookies() as $cookie) {
            $value = $cookie->getValue();

            if (null !== $value && \in_array($cookie->getName(), $names, true)) {
                $headers->setCookie($cookie->withValue($this->signer->sign($cookie->getName(), $value)));
            }
        }
    }

    /**
     * @return list<string>
     */
    private function names(Request $request): array
    {
        return ProtectionSyntax::items(RequestState::of($request)->protection(Protection::SignedCookies, $this->names));
    }
}
