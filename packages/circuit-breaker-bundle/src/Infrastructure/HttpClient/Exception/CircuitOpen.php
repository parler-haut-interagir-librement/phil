<?php

declare(strict_types=1);

namespace Phil\CircuitBreakerBundle\Infrastructure\HttpClient\Exception;

use RuntimeException;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;

/**
 * A request not sent because its circuit is open: the third party failed too often, or an operator forced the
 * circuit open. It is a transport error, so that code which already handles an unreachable host handles it too.
 */
final class CircuitOpen extends RuntimeException implements TransportExceptionInterface
{
    public static function for(string $key, string $method, string $url): self
    {
        return new self(\sprintf('The circuit "%s" is open: %s %s was not sent.', $key, $method, $url));
    }
}
