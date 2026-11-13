<?php

declare(strict_types=1);

namespace Phil\Automations\Engine;

use Phil\Automations\Exception\InvalidPayload;

/**
 * What a trigger says of an event that matches: the key that names this occurrence of the event — the same event
 * delivered twice gives the same key, so that it starts one run —, the subject the run is about, and the identifiers
 * the steps read.
 */
final readonly class TriggerMatch
{
    public const int MAX_KEY_BYTES = 255;

    public Payload $payload;

    /**
     * @throws InvalidPayload when the key or the subject is empty, too long or holds control characters
     */
    public function __construct(
        public string $eventKey,
        public ?string $subject = null,
        ?Payload $payload = null,
    ) {
        $this->payload = $payload ?? Payload::empty();

        foreach (['event key' => $eventKey, 'subject' => $subject ?? 'x'] as $what => $value) {
            if ('' === $value || \strlen($value) > self::MAX_KEY_BYTES || 1 === preg_match('/[\x00-\x1F\x7F]/', $value)) {
                throw InvalidPayload::because(\sprintf('The %s of a match is 1 to %d bytes, without control characters.', $what, self::MAX_KEY_BYTES));
            }
        }
    }
}
