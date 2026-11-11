<?php

declare(strict_types=1);

namespace Phil\WellKnownBundle\Application\Attribute;

use Attribute;

/**
 * Serves a `WellKnownDocument` at a well-known address: `path` is a name under `/.well-known/`, such as `matrix/server`,
 * or an absolute path such as `/nodeinfo/2.1`; `route` names its route, `phil_well_known_` and the path by default.
 */
#[Attribute(Attribute::TARGET_CLASS)]
final readonly class AsWellKnownDocument
{
    public function __construct(
        public string $path,
        public ?string $route = null,
    ) {
    }
}
