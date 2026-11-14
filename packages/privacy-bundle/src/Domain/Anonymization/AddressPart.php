<?php

declare(strict_types=1);

namespace Phil\PrivacyBundle\Domain\Anonymization;

/**
 * Which part of a postal address a column holds: the `part` option of the `address` anonymizer.
 */
enum AddressPart: string
{
    case Line = 'line';
    case City = 'city';
    case Region = 'region';
    case PostalCode = 'postal_code';
    case Country = 'country';
    case Full = 'full';
}
