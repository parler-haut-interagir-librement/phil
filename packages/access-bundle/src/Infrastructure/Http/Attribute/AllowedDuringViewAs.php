<?php

declare(strict_types=1);

namespace Phil\AccessBundle\Infrastructure\Http\Attribute;

use Attribute;

/**
 * A controller the guard of "view as this member" lets through with any method: one that changes nothing the member
 * viewed owns, such as the forms that start and stop the view.
 */
#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD)]
final readonly class AllowedDuringViewAs
{
}
