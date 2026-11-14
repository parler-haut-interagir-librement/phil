<?php

declare(strict_types=1);

namespace Phil\PrivacyBundle\Infrastructure\Http;

use Phil\PrivacyBundle\Domain\Cookie\CookieInventory;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Response;

/**
 * The cookies a response sets that the inventory does not declare. A cookie the response clears is not one it sets: it
 * removes what the browser holds.
 */
final class UndeclaredCookies
{
    private function __construct()
    {
    }

    /**
     * @return list<string> their names, in the order of the response, each once
     */
    public static function in(Response $response, CookieInventory $inventory): array
    {
        return $inventory->undeclared(array_map(
            static fn (Cookie $cookie): string => $cookie->getName(),
            array_filter($response->headers->getCookies(), static fn (Cookie $cookie): bool => !$cookie->isCleared()),
        ));
    }
}
