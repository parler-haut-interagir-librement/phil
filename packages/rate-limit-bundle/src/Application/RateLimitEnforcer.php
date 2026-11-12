<?php

declare(strict_types=1);

namespace Phil\RateLimitBundle\Application;

use Phil\RateLimitBundle\Application\Event\RateLimitExceeded;
use Phil\RateLimitBundle\Application\Event\RateLimitObserved;
use Phil\RateLimitBundle\Application\Exception\TooManyRequests;
use Phil\RateLimitBundle\Domain\Attribute\RateLimit;
use Phil\RateLimitBundle\Domain\Exception\UnknownLimiter;
use Phil\RateLimitBundle\Domain\ValueObject\Decision;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Holds a request to its rules, in order: each consumes on its limiter for the key of its strategy, the first refusal
 * dispatches `RateLimitExceeded` and throws `TooManyRequests`, and the tightest of the accepted decisions is returned
 * for the response's headers.
 *
 * A limiter in observation — its `observe` option — counts the same, but its refusal lets the request go on: it
 * dispatches `RateLimitObserved` and logs a warning naming the limiter, the route and the form, never the key, and its
 * decision is left out of the response's headers.
 *
 * A strategy that finds no key for the request — a `user` strategy without a signed-in user — leaves the rule out. A
 * form's submissions count under the key and the form's name, apart from the other requests of the same client. A
 * key longer than 200 bytes is replaced by its SHA-256.
 *
 * It keeps nothing between requests.
 */
final readonly class RateLimitEnforcer
{
    /** The longest key handed to a limiter as it is. */
    public const int MAX_KEY_LENGTH = 200;

    public function __construct(
        private Limiters $limiters,
        private KeyResolvers $keys,
        private RateLimitHeaders $headers,
        private ?EventDispatcherInterface $events = null,
        private ?LoggerInterface $logger = null,
    ) {
    }

    /**
     * @param iterable<RateLimit> $rules
     * @param string|null         $form  the name of the form whose submission is limited
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
            $limiter = $this->limiters->get($rule->limiter);
            $key = $this->keys->get($rule->key ?? $this->limiters->keyOf($rule->limiter))->keyOf($request);

            if (null === $key) {
                continue;
            }

            $key = $this->bounded(null === $form ? $key : $key.'|form:'.$form);
            $decision = $limiter->consume($key, $rule->amount ?? $this->limiters->amountOf($rule->limiter), $request)->named($rule->limiter);

            if (!$decision->accepted) {
                $route = $request->attributes->get('_route');
                $route = \is_string($route) ? $route : null;

                if ($this->limiters->observes($rule->limiter)) {
                    $this->observed($rule->limiter, $key, $decision, $route, $form);

                    continue;
                }

                $this->events?->dispatch(new RateLimitExceeded($rule->limiter, $key, $decision, $route, $form));

                throw new TooManyRequests($decision, $this->headers->of($decision));
            }

            if ($decision->isTighterThan($tightest)) {
                $tightest = $decision;
            }
        }

        return $tightest;
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
