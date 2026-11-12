<?php

declare(strict_types=1);

namespace Phil\LegalNotices;

/**
 * The documents a site publishes about itself: its legal notice — the imprint that says who publishes and hosts it —
 * and its privacy policy.
 */
enum DocumentType: string
{
    case LegalNotice = 'legal_notice';

    case PrivacyPolicy = 'privacy_policy';

    /**
     * The fields of the identity the document cannot be published without.
     *
     * @return non-empty-list<Field>
     */
    public function requiredFields(): array
    {
        return match ($this) {
            self::LegalNotice => [
                Field::PublisherName,
                Field::PublisherAddress,
                Field::PublicationDirector,
                Field::ContactEmail,
                Field::HostName,
                Field::HostAddress,
                Field::HostContact,
            ],
            self::PrivacyPolicy => [
                Field::PublisherName,
                Field::ContactEmail,
                Field::DpoName,
                Field::DpoContact,
            ],
        };
    }
}
