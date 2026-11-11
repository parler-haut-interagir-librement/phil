<?php

declare(strict_types=1);

namespace Phil\BounceParser;

use Phil\BounceParser\Mime\Headers;

/**
 * The header fields of the message a report speaks of, as the report returned them, so that the application finds the
 * message it sent: its `Message-ID`, its `Feedback-ID`, or a field of its own.
 */
final readonly class OriginalMessage
{
    public function __construct(
        public Headers $headers,
    ) {
    }

    /**
     * The `Message-ID` without its angle brackets.
     */
    public function messageId(): ?string
    {
        $value = $this->headers->get('Message-ID');

        return null === $value ? null : trim($value, " \t<>");
    }

    /**
     * The `Feedback-ID` some mailbox providers aggregate complaints by.
     */
    public function feedbackId(): ?string
    {
        return $this->headers->get('Feedback-ID');
    }

    public function header(string $name): ?string
    {
        return $this->headers->get($name);
    }
}
