<?php

declare(strict_types=1);

namespace Phil\PrivacyBundle\Infrastructure\LegalNotices;

use Override;
use Phil\LegalNotices\Field;
use Phil\LegalNotices\SiteIdentity;
use Phil\PrivacyBundle\Application\Contract\NoticeOrganisationSource;
use Phil\PrivacyBundle\Domain\Incident\NoticeOrganisation;

/**
 * Who gives the notices of an incident, read from the identity of the site of phil/legal-notices: the publisher's name,
 * its registration (where a Québec business puts its enterprise number, the NEQ), and the privacy officer as the person
 * to contact.
 */
final readonly class SiteIdentityOrganisation implements NoticeOrganisationSource
{
    public function __construct(
        private SiteIdentity $identity,
    ) {
    }

    #[Override]
    public function organisation(): NoticeOrganisation
    {
        return new NoticeOrganisation(
            $this->identity->get(Field::PublisherName),
            $this->identity->get(Field::PublisherRegistration),
            $this->identity->get(Field::DpoName),
            $this->identity->get(Field::DpoContact),
        );
    }
}
