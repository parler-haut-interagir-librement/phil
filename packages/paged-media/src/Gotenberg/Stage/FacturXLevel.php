<?php

declare(strict_types=1);

namespace Phil\PagedMedia\Gotenberg\Stage;

/**
 * The conformance level an invoice XML declares, which a Factur-X remediation names again.
 */
enum FacturXLevel: string
{
    case Minimum = 'MINIMUM';
    case BasicWl = 'BASIC WL';
    case Basic = 'BASIC';
    case En16931 = 'EN 16931';
    case Extended = 'EXTENDED';
    case XRechnung = 'XRECHNUNG';
}
