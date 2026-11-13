<?php

declare(strict_types=1);

namespace Phil\PagedMedia\Gotenberg\Exception;

/**
 * A conversion that failed inside Gotenberg (500), or a page that answered an error status (409).
 */
final class ConversionFailed extends GotenbergFailure
{
}
