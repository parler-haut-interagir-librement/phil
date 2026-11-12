<?php

declare(strict_types=1);

namespace Phil\ContentBundle\Domain\Dimension;

/**
 * The stage of a dimension row.
 *
 * `Draft` is what editors change; `Live` is what readers are served. Publishing copies a draft row onto its
 * live counterpart, so the two never share a row, and an edit in progress never reaches a reader.
 */
enum Stage: string
{
    case Draft = 'draft';
    case Live = 'live';
}
