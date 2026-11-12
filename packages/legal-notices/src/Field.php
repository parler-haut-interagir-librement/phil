<?php

declare(strict_types=1);

namespace Phil\LegalNotices;

/**
 * A field of the identity of a site: who publishes it, who hosts it, how to reach them, who answers for personal
 * information. Each is a placeholder of the same name in the body of a document, `${publisher_name}`.
 */
enum Field: string
{
    case SiteName = 'site_name';

    case PublisherName = 'publisher_name';

    case PublisherForm = 'publisher_form';

    case PublisherAddress = 'publisher_address';

    case PublisherRegistration = 'publisher_registration';

    case PublicationDirector = 'publication_director';

    case ContactEmail = 'contact_email';

    case ContactPhone = 'contact_phone';

    case HostName = 'host_name';

    case HostAddress = 'host_address';

    case HostContact = 'host_contact';

    case DpoName = 'dpo_name';

    case DpoContact = 'dpo_contact';

    /**
     * Whether the value may hold several lines: a postal address does.
     */
    public function isMultiline(): bool
    {
        return self::PublisherAddress === $this || self::HostAddress === $this;
    }
}
