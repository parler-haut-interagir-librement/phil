<?php

declare(strict_types=1);

namespace Phil\LegalNotices;

/**
 * Where a version stands: a draft nobody sees, published — public, and in force from its effective date —, or
 * withdrawn — still listed in the history, never in force again.
 */
enum PublicationState: string
{
    case Draft = 'draft';

    case Published = 'published';

    case Withdrawn = 'withdrawn';
}
