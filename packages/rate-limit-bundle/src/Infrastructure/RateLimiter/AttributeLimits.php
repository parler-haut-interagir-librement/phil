<?php

declare(strict_types=1);

namespace Phil\RateLimitBundle\Infrastructure\RateLimiter;

use LogicException;
use Phil\RateLimitBundle\Application\Exception\TooManyRequests;
use Phil\RateLimitBundle\Application\KeyResolvers;
use Phil\RateLimitBundle\Application\RateLimitEnforcer;
use SensitiveParameter;
use Symfony\Component\HttpFoundation\IpUtils;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\RateLimiter\Policy\NoLimiter;
use Symfony\Component\RateLimiter\RateLimit;

/**
 * What Symfony's `#[RateLimit]` consumes on a limiter of this bundle, through `AttributeLimiterFactory`: the key that
 * Symfony computed is turned into a key of the bundle, never kept in clear, and the consumption goes through the
 * enforcer, with its events, its observation and the headers of the response.
 *
 * - The key Symfony computes when the attribute names none — the client's address, or its /64, and the path — takes
 *   the limiter's own key strategy instead.
 * - A key that names a key strategy — `ip`, `user`, `user_or_ip`, `ip_route` or a `KeyResolver` service id — takes
 *   that strategy.
 * - Any other key — a route parameter's value, an expression's result — is kept as `key:` and its HMAC-SHA-256 under
 *   a key derived from the bundle's secret.
 *
 * A strategy that finds no key leaves the request uncounted: it consumes nothing and is accepted. A refusal throws
 * `TooManyRequests` out of Symfony's listener, which the responder answers.
 *
 * It keeps nothing between requests: the request is read from the stack at each consumption.
 */
final readonly class AttributeLimits
{
    /** The usage label the key of the explicit keys is derived under, by HKDF-SHA256, from the secret given. */
    public const string USAGE = 'phil_rate_limit.attribute_key';

    /** The key of this usage alone: no other usage of the same secret gives the same hash. */
    private string $key;

    public function __construct(
        private RateLimitEnforcer $enforcer,
        private KeyResolvers $keys,
        private RequestStack $requests,
        #[SensitiveParameter]
        string $secret,
    ) {
        $this->key = hash_hkdf('sha256', $secret, 32, self::USAGE);
    }

    /**
     * Consumes `$tokens` on the limiter of that name, for the request being handled.
     *
     * @param string|null $key the key Symfony's attribute computed
     *
     * @throws TooManyRequests when the limiter refuses the request
     * @throws LogicException  when no request is being handled
     */
    public function consume(string $limiter, ?string $key, int $tokens): RateLimit
    {
        $request = $this->requests->getCurrentRequest() ?? throw new LogicException(\sprintf('The rate limiter "%s" of phil/rate-limit-bundle counts the requests of #[RateLimit], and no request is being handled.', $limiter));
        $resolved = $this->keyOf($request, $limiter, $key);

        if (null === $resolved) {
            return new NoLimiter()->consume($tokens);
        }

        $decision = $this->enforcer->consume($request, $limiter, $resolved, $tokens);
        $this->enforcer->remember($request, $decision);

        return $this->enforcer->rateLimitOf($decision, true);
    }

    private function keyOf(Request $request, string $limiter, ?string $key): ?string
    {
        if (null === $key || $key === self::defaultKeyOf($request)) {
            return $this->enforcer->keyOf($request, $limiter);
        }

        if ($this->keys->has($key)) {
            return $this->enforcer->keyOf($request, $limiter, $key);
        }

        return 'key:'.hash_hmac('sha256', $key, $this->key);
    }

    /**
     * The key Symfony's attribute computes when it names none: the client's address, its /64 for an IPv6 one, `~` and
     * the path with its percent-encoding normalized.
     */
    public static function defaultKeyOf(Request $request): string
    {
        $path = strtr(rawurlencode(rawurldecode($request->getPathInfo())), ['%2F' => '/']);
        $address = $request->getClientIp() ?? 'unknown';

        if (str_contains($address, ':')) {
            $address = IpUtils::anonymize($address, 0, 8);
        }

        return $address.'~'.$path;
    }
}
