<?php

declare(strict_types=1);

namespace Phil\PagedMedia\Gotenberg\Read;

/**
 * How much a violation weighs: a fatal one makes the PDF non-compliant, a warning, raised by the softer Factur-X rules
 * only, does not.
 */
enum Severity: string
{
    case Fatal = 'fatal';
    case Warning = 'warning';
}
