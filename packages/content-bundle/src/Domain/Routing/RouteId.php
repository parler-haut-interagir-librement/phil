<?php

declare(strict_types=1);

namespace Phil\ContentBundle\Domain\Routing;

use Phil\DddBundle\Domain\Identifier\Identifier;

/**
 * The surrogate identifier of a stored route, generated when the route is built. A route is found by its
 * slug or by its content, never by this identifier.
 */
final readonly class RouteId extends Identifier
{
}
