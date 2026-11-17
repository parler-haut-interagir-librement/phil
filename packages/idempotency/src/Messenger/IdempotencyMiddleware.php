<?php

declare(strict_types=1);

namespace Phil\Idempotency\Messenger;

use Closure;
use JsonException;
use Override;
use Phil\Idempotency\Contract\FingerprintedMessage;
use Phil\Idempotency\Exception\IdempotencyKeyReused;
use Phil\Idempotency\Exception\InvalidIdempotencyValue;
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
 * The fingerprint is the message class and its content: the stamp's fingerprint when the producer gave one, else the
 * {@see FingerprintedMessage::idempotencyFingerprint()} of the message, else every property of the message, private
 * ones included. A key reused with another class or another content is refused, as an HTTP request that reuses a key
 * with another body.
 * `$compareContent` set to false compares the class only, for messages whose key alone names the intention.
 *
 * A message already handled is acknowledged without running its handlers again. One still being handled elsewhere is
 * retried after the seconds left on its reservation; one whose key was used with another fingerprint is refused,
 * without retry. A handler that throws marks the key failed, so that the retry Messenger schedules runs it again.
 */
final readonly class IdempotencyMiddleware implements MiddlewareInterface
{
    /** How deep the content of a message is read, objects and arrays included. */
    public const int MAX_DEPTH = 32;

    public function __construct(
        private IdempotencyGuard $guard,
        private bool $compareContent = true,
    ) {
    }

    #[Override]
    public function handle(Envelope $envelope, StackInterface $stack): Envelope
    {
        [$scope, $key] = $this->keyOf($envelope);

        if (null === $key || !$envelope->last(ReceivedStamp::class) instanceof StampInterface) {
            return $stack->next()->handle($envelope, $stack);
        }

        $fingerprint = $this->fingerprintOf($envelope);
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

    private function fingerprintOf(Envelope $envelope): PayloadFingerprint
    {
        $message = $envelope->getMessage();

        if (!$this->compareContent) {
            return PayloadFingerprint::ofParts('messenger', $message::class);
        }

        $given = $envelope->last(IdempotencyStamp::class)?->fingerprint;

        if (null !== $given) {
            return PayloadFingerprint::ofParts('messenger', $message::class, $given);
        }

        if ($message instanceof FingerprintedMessage) {
            return PayloadFingerprint::ofParts('messenger', $message::class, $message->idempotencyFingerprint()->hex);
        }

        try {
            $content = json_encode(self::contentOf($message, 0), \JSON_THROW_ON_ERROR | \JSON_PRESERVE_ZERO_FRACTION | \JSON_UNESCAPED_SLASHES);
        } catch (InvalidIdempotencyValue|JsonException $exception) {
            throw new UnrecoverableMessageHandlingException(\sprintf('The content of the message %s cannot be read for its idempotency fingerprint: implement %s, or give the IdempotencyStamp a fingerprint.', $message::class, FingerprintedMessage::class), $exception->getCode(), previous: $exception);
        }

        return PayloadFingerprint::ofParts('messenger', $message::class, $content);
    }

    /**
     * Every property of an object, private ones included, with its class, down to scalars: two messages of one class
     * whose properties hold the same values have the same content. A closure, a resource, or more than
     * {@see self::MAX_DEPTH} levels cannot be read.
     */
    private static function contentOf(mixed $value, int $depth): mixed
    {
        if ($depth > self::MAX_DEPTH) {
            throw InvalidIdempotencyValue::because(\sprintf('A message is read %d levels deep at most.', self::MAX_DEPTH));
        }

        if ($value instanceof Closure || \is_resource($value)) {
            throw InvalidIdempotencyValue::because('A closure or a resource has no content to compare.');
        }

        if (\is_object($value)) {
            return [$value::class => self::contentOf((array) $value, $depth + 1)];
        }

        if (\is_array($value)) {
            return array_map(static fn (mixed $item): mixed => self::contentOf($item, $depth + 1), $value);
        }

        return $value;
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
