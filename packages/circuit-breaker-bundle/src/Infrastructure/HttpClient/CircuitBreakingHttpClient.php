<?php

declare(strict_types=1);

namespace Phil\CircuitBreakerBundle\Infrastructure\HttpClient;

use Generator;
use Override;
use Phil\CircuitBreaker\CallOutcome;
use Phil\CircuitBreaker\CircuitBreakers;
use Phil\CircuitBreaker\CircuitKey;
use Phil\CircuitBreakerBundle\Infrastructure\HttpClient\Exception\CircuitOpen;
use Symfony\Component\HttpClient\AsyncDecoratorTrait;
use Symfony\Component\HttpClient\Response\AsyncContext;
use Symfony\Component\HttpClient\Response\AsyncResponse;
use Symfony\Contracts\HttpClient\ChunkInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * An HTTP client behind its circuit breaker: while the circuit is open, a request is refused with `CircuitOpen`
 * before it leaves; otherwise an answer of `5xx` or a transport error is a failure, any other answer a success.
 * A `4xx` is the caller's mistake, not the third party's, and counts as a success.
 *
 * Each request is measured up to its answer or its error: the transport's own `total_time` when it gives one, the time
 * elapsed since the request was made otherwise. The duration goes with the outcome, so that a circuit whose policy has
 * a slowness threshold counts the slow calls.
 */
final class CircuitBreakingHttpClient implements HttpClientInterface
{
    use AsyncDecoratorTrait;

    public function __construct(
        HttpClientInterface $client,
        private readonly CircuitBreakers $breakers,
        private readonly string $key,
    ) {
        $this->client = $client;
    }

    /**
     * @param array<array-key, mixed> $options
     */
    #[Override]
    public function request(string $method, string $url, array $options = []): ResponseInterface
    {
        $breaker = $this->breakers->get(CircuitKey::of($this->key));

        if (!$breaker->isAvailable()) {
            throw CircuitOpen::for($this->key, $method, $url);
        }

        $started = hrtime(true);

        return new AsyncResponse($this->client, $method, $url, $options, static function (ChunkInterface $chunk, AsyncContext $context) use ($breaker, $started): Generator {
            try {
                $first = $chunk->isFirst();
            } catch (TransportExceptionInterface $transportException) {
                $breaker->record(CallOutcome::failed(self::milliseconds($context, $started)));

                throw $transportException;
            }

            if ($first) {
                $milliseconds = self::milliseconds($context, $started);
                $breaker->record($context->getStatusCode() >= 500 ? CallOutcome::failed($milliseconds) : CallOutcome::succeeded($milliseconds));
            }

            yield $chunk;
        });
    }

    /**
     * How long the request has lasted, in whole milliseconds: the transport's `total_time`, in seconds, when it is
     * positive, otherwise the monotonic time since the request was made.
     */
    private static function milliseconds(AsyncContext $context, int|float $started): int
    {
        $total = $context->getInfo('total_time');

        if ((\is_float($total) || \is_int($total)) && $total > 0) {
            return (int) round($total * 1000);
        }

        return (int) ((hrtime(true) - $started) / 1_000_000);
    }
}
