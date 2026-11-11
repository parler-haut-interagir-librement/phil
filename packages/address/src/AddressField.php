<?php

declare(strict_types=1);

namespace Phil\Address;

/**
 * The parts of an address, as address formats name them.
 */
enum AddressField: string
{
    case GivenName = 'givenName';
    case AdditionalName = 'additionalName';
    case FamilyName = 'familyName';
    case Organization = 'organization';
    case AddressLine1 = 'addressLine1';
    case AddressLine2 = 'addressLine2';
    case AddressLine3 = 'addressLine3';
    /** A neighbourhood, a suburb, a district within a city. */
    case DependentLocality = 'dependentLocality';
    /** A city, a town, a post town. */
    case Locality = 'locality';
    /** A state, a province, a region, a prefecture. */
    case AdministrativeArea = 'administrativeArea';
    case PostalCode = 'postalCode';
    /** The CEDEX of France, a sorting code of some countries. */
    case SortingCode = 'sortingCode';

    /**
     * The fields that name the recipient rather than the place.
     *
     * @return list<self>
     */
    public static function recipient(): array
    {
        return [self::GivenName, self::AdditionalName, self::FamilyName, self::Organization];
    }
}
