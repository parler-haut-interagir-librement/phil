<?php

declare(strict_types=1);

namespace Phil\Outbox;

use DateTimeImmutable;
use Phil\Outbox\Exception\InvalidRecord;
use Throwable;

/**
 * A record of the outbox, as a store keeps it: what happened — its type, its payload, its ordering and idempotency keys,
 * when it happened — and where its delivery stands. The payload is kept as the JSON the store holds, and read by
 * `payload()` when the relay rebuilds the event, so that a record whose payload went bad becomes a dead record instead
 * of stopping the relay.
 *
 * A type is 1 to 120 lowercase letters and digits, in words joined by `.`, `_` or `-`, such as `sets.set_completed`; an
 * ordering or idempotency key is 1 to 190 visible ASCII characters, such as `set:42:holder:7`.
 */
final readonly class OutboxRecord
{
    /** The shape of an event type. */
    public const string TYPE_PATTERN = '/^(?=.{1,120}$)[a-z0-9]+(?:[._-][a-z0-9]+)*$/D';

    /** The shape of an ordering or idempotency key. */
    public const string KEY_PATTERN = '/^[\x21-\x7E]{1,190}$/D';

    /** The namespace of the exceptions whose message an error keeps. */
    public const string OWN_EXCEPTIONS = 'Phil\\Outbox\\Exception\\';

    /** The longest error kept, in bytes. */
    public const int MAX_ERROR_BYTES = 500;

    /**
     * @param string                 $payloadJson the JSON of the payload, as the store keeps it
     * @param DateTimeImmutable      $availableAt when the relay may try the record
     * @param int                    $attempts    the failed attempts so far
     * @param string|null            $lastError   the class and code of the exception of the last failed attempt, see `error()`
     * @param DateTimeImmutable|null $settledAt   when the record was delivered or declared dead
     */
    public function __construct(
        public RecordId $id,
        public string $type,
        public string $payloadJson,
        public ?string $orderingKey,
        public ?string $idempotencyKey,
        public DateTimeImmutable $occurredAt,
        public DateTimeImmutable $availableAt,
        public RecordState $state = RecordState::Pending,
        public int $attempts = 0,
        public ?string $lastError = null,
        public ?DateTimeImmutable $settledAt = null,
    ) {
    }

    /**
     * A new pending record, checked against the bounds of a type and of the keys.
     *
     * @throws InvalidRecord when the type or a key is out of bounds
     */
    public static function pending(RecordId $id, string $type, Payload $payload, ?string $orderingKey, ?string $idempotencyKey, DateTimeImmutable $occurredAt): self
    {
        if (1 !== preg_match(self::TYPE_PATTERN, $type)) {
            throw InvalidRecord::because(\sprintf('"%s" is not an event type: 1 to 120 lowercase letters and digits, in words joined by ".", "_" or "-".', mb_strimwidth($type, 0, 130, '…')));
        }

        foreach (['ordering' => $orderingKey, 'idempotency' => $idempotencyKey] as $kind => $key) {
            if (null !== $key && 1 !== preg_match(self::KEY_PATTERN, $key)) {
                throw InvalidRecord::because(\sprintf('"%s" is not an %s key: 1 to 190 visible ASCII characters.', mb_strimwidth($key, 0, 200, '…'), $kind));
            }
        }

        return new self($id, $type, $payload->toJson(), $orderingKey, $idempotencyKey, $occurredAt, $occurredAt);
    }

    /**
     * The class and code of an exception, such as `RuntimeException (code 550)`, cut to the longest error kept, on a
     * character boundary.
     *
     * The message is kept only for the `OutboxException`s of this package, `Phil\Outbox\Exception\…`, whose text names types,
     * keys and settings, never a value of the application. The message of any other exception can quote personal
     * data — a mail server refusing an address, a database quoting a duplicate value — and an error is kept until the
     * record is purged or discarded: it is never stored.
     */
    public static function error(Throwable $throwable): string
    {
        $error = \sprintf('%s (code %s)', $throwable::class, $throwable->getCode());

        if (str_starts_with($throwable::class, self::OWN_EXCEPTIONS)) {
            $error .= ': '.$throwable->getMessage();
        }

        return mb_strcut(mb_scrub($error, 'UTF-8'), 0, self::MAX_ERROR_BYTES, 'UTF-8');
    }

    /**
     * The payload, read from its JSON.
     *
     * @throws InvalidRecord when the JSON kept is not a payload
     */
    public function payload(): Payload
    {
        return Payload::fromJson($this->payloadJson);
    }
}
