<?php

declare(strict_types=1);

namespace Phil\PagedMedia\Gotenberg;

use DateTimeImmutable;
use Exception;
use Phil\PagedMedia\Gotenberg\Exception\UnexpectedResponse;
use Phil\PagedMedia\Gotenberg\Read\JsonValue;

/**
 * The event Gotenberg posts to a webhook's events URL once the work is done: `webhook.success`, or `webhook.error`
 * with the status and the message the request would have answered.
 */
final readonly class WebhookEvent
{
    public function __construct(
        public bool $success,
        public ?string $correlationId,
        public ?DateTimeImmutable $timestamp,
        public ?int $errorStatus = null,
        public ?string $errorMessage = null,
    ) {
    }

    /**
     * Reads the body of the event request.
     */
    public static function fromJson(string $body): self
    {
        $json = JsonValue::decode($body);
        $event = $json->string('event');

        if (!\in_array($event, ['webhook.success', 'webhook.error'], true)) {
            throw UnexpectedResponse::because(\sprintf('A Gotenberg webhook event is webhook.success or webhook.error; "%s" is not.', $event));
        }

        $error = 'webhook.error' === $event ? $json->members()['error'] ?? null : null;

        return new self(
            'webhook.success' === $event,
            $json->optionalString('correlationId'),
            self::timestamp($json->optionalString('timestamp')),
            $error?->int('status'),
            $error?->string('message'),
        );
    }

    private static function timestamp(?string $timestamp): ?DateTimeImmutable
    {
        if (null === $timestamp) {
            return null;
        }

        try {
            return new DateTimeImmutable($timestamp);
        } catch (Exception $exception) {
            throw UnexpectedResponse::because(\sprintf('A Gotenberg webhook event has a timestamp that is not a date: "%s".', $timestamp), $exception);
        }
    }
}
