<?php

declare(strict_types=1);

namespace Phil\NewsletterBundle\Application;

use Phil\NewsletterBundle\Domain\Exception\InvalidNewsletterValue;

/**
 * Whether a campaign is sent only once approved, the role that approves it, and whether its approver must be someone
 * other than its author.
 */
final readonly class ApprovalPolicy
{
    /**
     * @throws InvalidNewsletterValue when the role is blank
     */
    public function __construct(
        public bool $required = true,
        public string $role = 'ROLE_NEWSLETTER_APPROVER',
        public bool $fourEyes = false,
    ) {
        if ('' === trim($role)) {
            throw InvalidNewsletterValue::because('The role that approves campaigns is not blank.');
        }
    }
}
