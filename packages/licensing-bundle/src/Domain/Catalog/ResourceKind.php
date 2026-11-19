<?php

declare(strict_types=1);

namespace Phil\LicensingBundle\Domain\Catalog;

/**
 * What an attributed resource is. The attribution page lists the resources in the order of these cases.
 */
enum ResourceKind: string
{
    /** A text source: a system reference document, rules, a setting, quotations. */
    case Text = 'text';

    case Image = 'image';

    case Map = 'map';

    case Font = 'font';

    case Other = 'other';
}
