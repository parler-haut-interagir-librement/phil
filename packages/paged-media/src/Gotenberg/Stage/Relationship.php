<?php

declare(strict_types=1);

namespace Phil\PagedMedia\Gotenberg\Stage;

/**
 * How an embedded file relates to the document; giving one makes it a PDF/A-3 associated file.
 */
enum Relationship: string
{
    case Source = 'Source';
    case Data = 'Data';
    case Alternative = 'Alternative';
    case Supplement = 'Supplement';
    case Unspecified = 'Unspecified';
}
