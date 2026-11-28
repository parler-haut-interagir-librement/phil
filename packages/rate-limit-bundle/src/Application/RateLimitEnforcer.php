<?php

declare(strict_types=1);

namespace Phil\RateLimitBundle\Application;

use DateInterval;
use DateTimeImmutable;
use Phil\RateLimitBundle\Application\Event\RateLimitExceeded;
use Phil\RateLimitBundle\Application\Event\RateLimitObserved;
use Phil\RateLimitBundle\Application\Exception\TooManyRequests;
use Phil\RateLimitBundle\Domain\Exception\UnknownLimiter;
use Phil\RateLimitBundle\Domain\ValueObject\Decision;
use Phil\RateLimitBundle\Domain\ValueObject\Rule;
use Psr\Clock\ClockInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\RateLimiter\Event\RateLimitExceededEvent;
use Symfony\Component\RateLimiter\RateLimit;

/**
 * Holds a request to its rules, in order: each consumes on its limiter for the key of its strategy, the first refusal
 * dispatches `RateLimitExceeded` and Symfony's `RateLimitExceededEvent` and throws `TooManyRequests`, and the tightest
 * of the accepted decisions is returned for the response's headers.
 *
 * A limiter in observation — its `observe` option — counts the same, but its refusal lets the request go on: it
 * dispatches `RateLimitObserved` and logs a warning naming the limiter, the route and the form, never the key, and its
 * decision is left out of the response's headers.
 *
 * A strategy that finds no key for the request — a `user` strategy without a signed-in user — leaves the rule out. A
 * form's submissions count under the key and the form's name, apart from the other requests of the same client. A
 * key longer than 200 bytes is replaced by its SHA-256.
 *
 * It keeps nothing between requests: the decision the headers are written from travels in a request attribute.
 */
final readonly class RateLimitEnforcer
{
    /** The longest key handed to a limiter as it is. */
    public const int MAX_KEY_LENGTH = 200;

    /** The request attribute that keeps the tightest accepted decision, which the response headers are written from. */
    public const string DECISION_ATTRIBUTE = '_phil_rate_limit';

    public function __construct(
        private Limiters $limiters,
        private KeyResolvers $keys,
        private RateLimitHeaders $headers,
        private ?EventDispatcherInterface $events = null,
        private ?LoggerInterface $logger = null,
        private ?ClockInterface $clock = null,
    ) {
    }

    /**
     * @param iterable<Rule> $rules
     * @param string|null    $form  the name of the form whose submission is limited
     *
     * @return Decision|null the accepted decision with the least left, null when no rule counted the request
     *
     * @throws TooManyRequests when a limiter refuses the request
     * @throws UnknownLimiter  when a rule names a limiter or a strategy nothing declares
     */
    public function enforce(Request $request, iterable $rules, ?string $form = null): ?Decision
    {
        $tightest = null;

        foreach ($rules as $rule) {
            $amount = $rule->amount ?? $this->limiters->amountOf($rule->limiter);
            $key = $this->keyOf($request, $rule->limiter, $rule->key);

            if (null === $key) {
                continue;
            }

            $decision = $this->consume($request, $rule->limiter, null === $form ? $key : $key.'|form:'.$form, $amount, $form);

            if ($decision->accepted && $decision->isTighterThan($tightest)) {
                $tightest = $decision;
            }
        }

        return $tightest;
    }

    /**
     * The key a request counts under for a strategy: the strategy named, or the limiter's own.
     *
     * @return string|null null when the strategy finds no key for the request
     *
     * @throws UnknownLimiter when the limiter or the strategy is not declared
     */
    public function keyOf(Request $request, string $limiter, ?string $strategy = null): ?string
    {
        return $this->keys->get($strategy ?? $this->limiters->keyOf($limiter))->keyOf($request);
    }

    /**
     * Consumes `$amount` on one limiter for a key already hashed.
     *
     * @return Decision the decision of the limiter: accepted, or refused by a limiter in observation, which lets the
     *                  request go on
     *
     * @throws TooManyRequests when the limiter refuses the request
     * @throws UnknownLimiter  when no limiter has that name
     */
    public function consume(Request $request, string $limiter, string $key, int $amount, ?string $form = null): Decision
    {
        $key = $this->bounded($key);
        $decision = $this->limiters->get($limiter)->consume($key, $amount, $request)->named($limiter);

        if ($decision->accepted) {
            return $decision;
        }

        $route = $request->attributes->get('_route');
        $route = \is_string($route) ? $route : null;

        if ($this->limiters->observes($limiter)) {
            $this->observed($limiter, $key, $decision, $route, $form);

            return $decision;
        }

        $this->events?->dispatch(new RateLimitExceeded($limiter, $key, $decision, $route, $form));
        $this->events?->dispatch(new RateLimitExceededEvent($this->rateLimitOf($decision), $limiter, $key));

        throw new TooManyRequests($decision, $this->headers->of($decision));
    }

    /**
     * Keeps an accepted decision on the request when it leaves less than the one kept so far, for the headers of the
     * response.
     */
    public function remember(Request $request, Decision $decision): void
    {
        $kept = $request->attributes->get(self::DECISION_ATTRIBUTE);

        if ($decision->accepted && $decision->isTighterThan($kept instanceof Decision ? $kept : null)) {
            $request->attributes->set(self::DECISION_ATTRIBUTE, $decision);
        }
    }

    /**
     * A decision as the `RateLimit` of `symfony/rate-limiter`: its moments counted from now, a limiter without a limit
     * as one of `PHP_INT_MAX`, as Symfony's own `no_limit` gives.
     *
     * @param bool|null $accepted whether the request goes on; the decision's own answer when null
     */
    public function rateLimitOf(Decision $decision, ?bool $accepted = null): RateLimit
    {
        $now = $this->clock?->now() ?? new DateTimeImmutable();

        return new RateLimit(
            $decision->remaining,
            $now->add(new DateInterval('PT'.$decision->retryAfter.'S')),
            $accepted ?? $decision->accepted,
            $decision->limit ?? \PHP_INT_MAX,
            $now->add(new DateInterval('PT'.$decision->resetAfter.'S')),
        );
    }

    /**
     * @return array<string, string>
     */
    public function headersOf(Decision $decision): array
    {
        return $this->headers->of($decision);
    }

    private function observed(string $limiter, string $key, Decision $decision, ?string $route, ?string $form): void
    {
        $this->events?->dispatch(new RateLimitObserved($limiter, $key, $decision, $route, $form));
        $this->logger?->warning('The rate limit "{limiter}" in observation would have refused a request.', [
            'limiter' => $limiter,
            'route' => $route,
            'form' => $form,
            'limit' => $decision->limit,
            'retry_after' => $decision->retryAfter,
        ]);
    }

    private function bounded(string $key): string
    {
        return \strlen($key) > self::MAX_KEY_LENGTH ? 'sha256:'.hash('sha256', $key) : $key;
    }
}
