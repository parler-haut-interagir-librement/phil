<?php

declare(strict_types=1);

namespace Phil\NotificationsBundle\Infrastructure\CircuitBreaker;

use Override;
use Phil\CircuitBreaker\CircuitBreaker;
use Phil\CircuitBreaker\CircuitBreakers;
use Phil\CircuitBreaker\CircuitKey;
use Phil\CircuitBreaker\Exception\InvalidCircuit;
use Phil\NotificationsBundle\Application\Contract\ChannelCircuits;

/**
 * The circuit breakers of the channels, on `phil/circuit-breaker`: `notifications.<channel>.<transport>` for a channel
 * with a transport, such as `notifications.chat.slack`, else `notifications.<channel>`, such as `notifications.sms`;
 * the first of the two the application declares guards the channel. A channel whose circuit is not declared has no
 * breaker.
 */
final readonly class ChannelBreakers implements ChannelCircuits
{
    public const string PREFIX = 'notifications.';

    public function __construct(
        private ?CircuitBreakers $breakers = null,
    ) {
    }

    #[Override]
    public function isOpen(string $channel): bool
    {
        $breaker = $this->of($channel);

        return $breaker instanceof CircuitBreaker && !$breaker->isAvailable();
    }

    #[Override]
    public function recordSuccess(string $channel): void
    {
        $this->of($channel)?->recordSuccess();
    }

    #[Override]
    public function recordFailure(string $channel): void
    {
        $this->of($channel)?->recordFailure();
    }

    public function of(string $channel): ?CircuitBreaker
    {
        if (!$this->breakers instanceof CircuitBreakers) {
            return null;
        }

        $base = explode('/', $channel, 2)[0];

        foreach (array_unique([str_replace('/', '.', $channel), $base]) as $name) {
            try {
                $key = CircuitKey::of(self::PREFIX.$name);
            } catch (InvalidCircuit) {
                // A transport name the circuit keys do not allow, such as one ending with "-", cannot be declared.
                continue;
            }

            if ($this->breakers->has($key)) {
                return $this->breakers->get($key);
            }
        }

        return null;
    }
}
