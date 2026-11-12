<?php

declare(strict_types=1);

namespace Phil\Idempotency\Messenger;

use Override;
use Phil\Idempotency\Exception\IdempotencyKeyReused;
use Phil\Idempotency\Exception\RequestInProgress;
use Phil\Idempotency\IdempotencyGuard;
use Phil\Idempotency\IdempotencyKey;
use Phil\Idempotency\IdempotencyScope;
use Phil\Idempotency\PayloadFingerprint;
use Phil\Idempotency\StoredResponse;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\RecoverableMessageHandlingException;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;
use Symfony\Component\Messenger\Middleware\MiddlewareInterface;
use Symfony\Component\Messenger\Middleware\StackInterface;
use Symfony\Component\Messenger\Stamp\MessageIdStamp;
use Symfony\Component\Messenger\Stamp\ReceivedStamp;
use Symfony\Component\Messenger\Stamp\StampInterface;

/**
 * Handles a received message once per key.
 *
 * The key is the envelope's {@see IdempotencyStamp}, else its {@see MessageIdStamp}, which Messenger keeps across
 * retries and redeliveries; an envelope with neither passes through. Only a received envelope is guarded: a
 * duplicate arrives through a transport, and the dispatch of an asynchronous message is not its handling.
 *
 * A message already handled is acknowledged without running its handlers again. One still being handled elsewhere is
 * retried after the seconds left on its reservation; one whose key was used by another message class is refused,
 * without retry. A handler that throws marks the key failed, so that the retry Messenger schedules runs it again.
 */
final readonly class IdempotencyMiddleware implements MiddlewareInterface
{
    public function __construct(
        private IdempotencyGuard $guard,
    ) {
    }

    #[Override]
    public function handle(Envelope $envelope, StackInterface $stack): Envelope
    {
        [$scope, $key] = $this->keyOf($envelope);

        if (null === $key || !$envelope->last(ReceivedStamp::class) instanceof StampInterface) {
            return $stack->next()->handle($envelope, $stack);
        }

        $fingerprint = PayloadFingerprint::ofParts('messenger', $envelope->getMessage()::class);
        $handled = $envelope;

        try {
            $this->guard->run(IdempotencyScope::of($scope), IdempotencyKey::fromString($key), $fingerprint, static function () use ($envelope, $stack, &$handled): StoredResponse {
                $handled = $stack->next()->handle($envelope, $stack);

                return StoredResponse::nothing();
            });
        } catch (RequestInProgress $requestInProgress) {
            throw new RecoverableMessageHandlingException($requestInProgress->getMessage(), previous: $requestInProgress, retryDelay: $requestInProgress->retryAfter * 1000);
        } catch (IdempotencyKeyReused $idempotencyKeyReused) {
            throw new UnrecoverableMessageHandlingException($idempotencyKeyReused->getMessage(), $idempotencyKeyReused->getCode(), previous: $idempotencyKeyReused);
        }

        return $handled;
    }

    /**
     * @return array{string, ?string}
     */
    private function keyOf(Envelope $envelope): array
    {
        $stamp = $envelope->last(IdempotencyStamp::class);

        if ($stamp instanceof IdempotencyStamp) {
            return [$stamp->scope, $stamp->key];
        }

        $id = $envelope->last(MessageIdStamp::class);

        return [IdempotencyStamp::DEFAULT_SCOPE, $id instanceof MessageIdStamp ? $id->getId() : null];
    }
}
