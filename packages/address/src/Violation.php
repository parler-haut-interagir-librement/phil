<?php

declare(strict_types=1);

namespace Phil\Address;

/**
 * What is wrong with an address: a field missing, a field its country does not use, a postal code that is not its
 * country's or its subdivision's, a subdivision the country does not have.
 */
enum Violation: string
{
    case Missing = 'missing';
    case Unused = 'unused';
    case InvalidPostalCode = 'invalid_postal_code';
    case UnknownSubdivision = 'unknown_subdivision';
}
