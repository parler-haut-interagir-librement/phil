<?php

declare(strict_types=1);

namespace Phil\AccessBundle\Infrastructure\Http\Attribute;

use Attribute;

/**
 * A controller the guard of "view as this member" refuses whatever the method: a `GET` that changes something, or
 * that hands out what only the admin should take — an export, a download, a link that signs in.
 */
#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD)]
final readonly class RefusedDuringViewAs
{
}
