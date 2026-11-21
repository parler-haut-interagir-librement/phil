<?php

declare(strict_types=1);

namespace Phil\NewsletterBundle\Domain\ValueObject;

use DateTimeImmutable;
use Phil\NewsletterBundle\Domain\Exception\InvalidNewsletterValue;

/**
 * Who approved a campaign for sending, and when: the user identifier of the approver, as the application's security
 * gives it.
 */
final readonly class CampaignApproval
{
    /**
     * @throws InvalidNewsletterValue when the approver is blank or longer than 191 characters
     */
    public function __construct(
        public string $approver,
        public DateTimeImmutable $approvedAt,
    ) {
        if ('' === trim($approver) || mb_strlen($approver) > 191) {
            throw InvalidNewsletterValue::because('The approver of a campaign is not blank and holds at most 191 characters.');
        }
    }
}
