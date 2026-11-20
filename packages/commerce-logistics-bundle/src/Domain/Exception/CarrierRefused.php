<?php

declare(strict_types=1);

namespace Phil\CommerceLogisticsBundle\Domain\Exception;

use DomainException;
use Phil\DddBundle\Domain\Exception\DomainError;
use Throwable;

/**
 * A carrier that cannot be asked, or that refused or failed to answer, or a cost the module refuses to record.
 */
final class CarrierRefused extends DomainException implements DomainError
{
    public static function unknownCarrier(string $carrier): self
    {
        return new self(\sprintf('No carrier is known as "%s".', $carrier));
    }

    /**
     * The carrier answered with an error, or not at all. The reason is the carrier's, never its credentials.
     */
    public static function unavailable(string $carrier, string $reason, ?Throwable $previous = null): self
    {
        return new self(\sprintf('The carrier %s did not answer: %s', $carrier, $reason), 0, $previous);
    }

    public static function alreadyRecorded(string $shipment): self
    {
        return new self(\sprintf('The cost of the shipment %s is already recorded.', $shipment));
    }

    public static function otherCurrency(string $orderNumber, string $currency): self
    {
        return new self(\sprintf("A parcel of %s cost %s, a currency other than the order's.", $orderNumber, $currency));
    }
}
