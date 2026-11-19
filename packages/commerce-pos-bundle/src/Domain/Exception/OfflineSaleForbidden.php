<?php

declare(strict_types=1);

namespace Phil\CommercePosBundle\Domain\Exception;

use DomainException;
use Phil\DddBundle\Domain\Exception\DomainError;

/**
 * The application's `OfflineSaleGate` refused an offline sale: whoever sent it may not import it.
 */
final class OfflineSaleForbidden extends DomainException implements DomainError
{
    public static function sale(string $id, string $session): self
    {
        return new self(\sprintf('The offline sale %s may not be imported into the session %s by whoever sent it.', $id, $session));
    }
}
