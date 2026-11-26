<?php

declare(strict_types=1);

namespace Phil\CoursesBundle\Application\Contract;

use Phil\CoursesBundle\Domain\Model\CatalogueEntry;
use Phil\CoursesBundle\Domain\ValueObject\LearnerId;

/**
 * Where a learner pays for a course a catalogue sells: the application adds `$entry->sellable()` to the learner's cart
 * of its store — an order of phil/commerce-bundle whose customer is the learner's identifier — and answers the address
 * of the cart or of the payment. The learner is enrolled once the order is paid.
 */
interface CourseCheckout
{
    /**
     * @return string the address the learner is sent to, on the application's own site
     */
    public function checkout(CatalogueEntry $entry, LearnerId $learner): string;
}
