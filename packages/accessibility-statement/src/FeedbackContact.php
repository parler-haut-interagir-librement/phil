<?php

declare(strict_types=1);

namespace Phil\AccessibilityStatement;

use Phil\AccessibilityStatement\Exception\InvalidStatement;

/**
 * How a reader reports a barrier or asks for the content in another format: an email, a telephone, a page such as a
 * contact form, a postal address — at least one —, and the days within which an answer comes. An email is checked as
 * PHP's filter checks it, at most 254 bytes.
 */
final readonly class FeedbackContact
{
    public function __construct(
        public ?string $email = null,
        public ?string $phone = null,
        public ?string $url = null,
        public ?LocalizedText $postalAddress = null,
        public ?int $responseDays = null,
    ) {
        if (null === $email && null === $phone && null === $url && !$postalAddress instanceof LocalizedText) {
            throw InvalidStatement::because('feedback_channel', 'The feedback contact gives an email, a telephone, a page or a postal address.');
        }

        if (null !== $email && false === filter_var($email, \FILTER_VALIDATE_EMAIL)) {
            throw InvalidStatement::because('feedback_email', 'The feedback email is not an email address.');
        }

        if (null !== $phone && 1 !== preg_match('/^\+?[0-9][0-9 ().-]{2,31}$/D', $phone)) {
            throw InvalidStatement::because('feedback_phone', 'The feedback telephone is not a telephone number.');
        }

        if (null !== $url) {
            Address::check($url, 'feedback page');
        }

        if (null !== $responseDays && ($responseDays < 1 || $responseDays > 365)) {
            throw InvalidStatement::because('feedback_response_days', \sprintf('An answer comes within 1 to 365 days; got %d.', $responseDays));
        }
    }

    /**
     * The telephone as a `tel:` address: its digits, and the `+` of an international number.
     */
    public function phoneUri(): ?string
    {
        return null === $this->phone ? null : 'tel:'.preg_replace('/[^0-9+]/', '', $this->phone);
    }
}
