<?php

declare(strict_types=1);

namespace Phil\CommerceSyndicationBundle\Domain\Partner;

/**
 * What a partner application is to this one.
 */
enum PartnerRole: string
{
    /** It supplies products this application resells, and ships the orders forwarded to it. */
    case Supplier = 'supplier';

    /** It resells products of this application, and forwards their orders here to be shipped. */
    case Reseller = 'reseller';
}
