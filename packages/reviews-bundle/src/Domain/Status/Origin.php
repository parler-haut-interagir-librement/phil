<?php

declare(strict_types=1);

namespace Phil\ReviewsBundle\Domain\Status;

/**
 * Where a review was collected:
 *
 * - `Site`: written on this site, through the bundle;
 * - `Transferred`: brought by the application from another system of its own, such as an earlier version of the site.
 *
 * The bundle never fetches reviews from another platform. The structured data of a subject are given only when all its
 * published reviews were collected on the site.
 */
enum Origin: string
{
    case Site = 'site';
    case Transferred = 'transferred';
}
