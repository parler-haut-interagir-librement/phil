<?php

declare(strict_types=1);

namespace Phil\BounceParser;

/**
 * The `Feedback-Type` of an abuse report (RFC 5965 and RFC 6591).
 */
enum FeedbackType: string
{
    /** Unsolicited mail or some other kind of abuse. */
    case Abuse = 'abuse';
    /** Fraudulent mail, phishing. */
    case Fraud = 'fraud';
    /** Mail that carries a virus. */
    case Virus = 'virus';
    /** Any other feedback, and a type this list does not know. */
    case Other = 'other';
    /** The recipient says the message is not spam. */
    case NotSpam = 'not-spam';
    /** A failure of authentication, SPF or DKIM (RFC 6591): a report about the domain, not the recipient. */
    case AuthFailure = 'auth-failure';

    public static function read(?string $value): self
    {
        return self::tryFrom(strtolower(trim((string) $value))) ?? self::Other;
    }

    /**
     * Whether the report says a person did not want the message.
     */
    public function isComplaint(): bool
    {
        return \in_array($this, [self::Abuse, self::Fraud, self::Virus, self::Other], true);
    }
}
