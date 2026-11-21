<?php

declare(strict_types=1);

namespace Phil\PrivacyBundle\Application\Exception;

use Phil\PrivacyBundle\Domain\Exception\PrivacyError;
use RuntimeException;

/**
 * Nothing was added to the journal of an incident.
 */
final class JournalRefused extends RuntimeException implements PrivacyError
{
    public static function withoutJournal(): self
    {
        return new self('This register of incidents does not keep a journal (IncidentJournal).');
    }

    public static function unknownIncident(string $id): self
    {
        return new self(\sprintf('The register has no incident "%s".', $id));
    }

    public static function beforeDiscovery(): self
    {
        return new self('An entry of the journal cannot come before the start of the discovery of its incident.');
    }

    public static function nothingDeferred(): self
    {
        return new self('The journal of this incident records no deferral of the notice to lift.');
    }
}
