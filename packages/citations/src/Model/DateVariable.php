<?php

declare(strict_types=1);

namespace Phil\Citations\Model;

/**
 * A date of a document: the date variables of CSL 1.0.2.
 */
enum DateVariable: string
{
    case Accessed = 'accessed';
    case AvailableDate = 'available-date';
    case EventDate = 'event-date';
    case Issued = 'issued';
    case OriginalDate = 'original-date';
    case Submitted = 'submitted';
}
