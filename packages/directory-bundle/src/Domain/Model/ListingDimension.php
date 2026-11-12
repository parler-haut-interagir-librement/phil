<?php

declare(strict_types=1);

namespace Phil\DirectoryBundle\Domain\Model;

use Doctrine\ORM\Mapping as ORM;
use Phil\ContentBundle\Domain\Capability\BodyCapable;
use Phil\ContentBundle\Domain\Capability\BodyTrait;
use Phil\ContentBundle\Domain\Capability\ExcerptCapable;
use Phil\ContentBundle\Domain\Capability\ExcerptTrait;
use Phil\ContentBundle\Domain\Capability\RoutableCapable;
use Phil\ContentBundle\Domain\Capability\RoutableTrait;
use Phil\ContentBundle\Domain\Capability\SeoCapable;
use Phil\ContentBundle\Domain\Capability\SeoTrait;
use Phil\ContentBundle\Domain\Capability\WorkflowCapable;
use Phil\ContentBundle\Domain\Capability\WorkflowTrait;
use Phil\ContentBundle\Domain\Content\AbstractDimensionContent;
use Phil\DirectoryBundle\Domain\Capability\AddressCapable;
use Phil\DirectoryBundle\Domain\Capability\AddressTrait;
use Phil\DirectoryBundle\Domain\Capability\ContactCapable;
use Phil\DirectoryBundle\Domain\Capability\ContactTrait;
use Phil\DirectoryBundle\Domain\Capability\EditedByCapable;
use Phil\DirectoryBundle\Domain\Capability\EditedByTrait;
use Phil\DirectoryBundle\Domain\Capability\LocationCapable;
use Phil\DirectoryBundle\Domain\Capability\LocationTrait;
use Phil\DirectoryBundle\Domain\Capability\RelatedCapable;
use Phil\DirectoryBundle\Domain\Capability\RelatedTrait;

/**
 * A row of a listing, in a stage, a version and maybe a locale: its name and summary (the excerpt), its slug, its
 * description (the body), its SEO, how to reach it, its address and its place on a map, the listings it is related to, and who wrote the version.
 */
#[ORM\Entity]
#[ORM\Table(name: 'phil_directory_listing_dimension')]
final class ListingDimension extends AbstractDimensionContent implements WorkflowCapable, SeoCapable, ExcerptCapable, RoutableCapable, BodyCapable, ContactCapable, AddressCapable, LocationCapable, RelatedCapable, EditedByCapable
{
    use AddressTrait;
    use BodyTrait;
    use ContactTrait;
    use EditedByTrait;
    use ExcerptTrait;
    use LocationTrait;
    use RelatedTrait;
    use RoutableTrait;
    use SeoTrait;
    use WorkflowTrait;
}
