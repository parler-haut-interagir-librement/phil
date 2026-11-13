<?php

declare(strict_types=1);

namespace Phil\PagedMedia\Gotenberg\Exception;

/**
 * A route that does not exist (404): the engine that serves it is not in the Gotenberg image, or its routes are disabled.
 */
final class RouteUnavailable extends GotenbergFailure
{
}
