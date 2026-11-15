<?php

declare(strict_types=1);

namespace Phil\NewsletterBundle\Application;

use Phil\NewsletterBundle\Domain\Enum\LinkPurpose;

/**
 * What a signed link says, once its signature is checked: what it is for, who or what it is about, one more value, and
 * when it was made.
 */
final readonly class SignedLink
{
    public function __construct(
        public LinkPurpose $purpose,
        public string $subject,
        public string $extra,
        public int $issuedAt,
        public ?int $expiresAt,
    ) {
    }
}
