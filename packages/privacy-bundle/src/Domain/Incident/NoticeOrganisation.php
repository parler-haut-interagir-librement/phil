<?php

declare(strict_types=1);

namespace Phil\PrivacyBundle\Domain\Incident;

/**
 * Who gives a notice of a confidentiality incident: the organisation's name, its Québec enterprise number (NEQ), and
 * the person to contact about the incident with how to reach them. A value not known is null, and the notice marks its
 * rubric to complete.
 */
final readonly class NoticeOrganisation
{
    public function __construct(
        public ?string $name = null,
        public ?string $enterpriseNumber = null,
        public ?string $contactName = null,
        public ?string $contactDetails = null,
    ) {
    }
}
