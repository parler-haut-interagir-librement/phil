<?php

declare(strict_types=1);

namespace Phil\CoursesBundle\Tests\Functional\App;

use Override;
use Phil\CoursesBundle\Application\Contract\CourseCheckout;
use Phil\CoursesBundle\Domain\Model\CatalogueEntry;
use Phil\CoursesBundle\Domain\ValueObject\LearnerId;

/**
 * The cart of a store, at the path the `$address` names: `/shop/cart` by default, with the reference added and the
 * learner as its query.
 */
final class CartCheckout implements CourseCheckout
{
    public string $address = '/shop/cart';

    #[Override]
    public function checkout(CatalogueEntry $entry, LearnerId $learner): string
    {
        return $this->address.'?'.http_build_query(['add' => $entry->sellable(), 'for' => $learner->value]);
    }
}
