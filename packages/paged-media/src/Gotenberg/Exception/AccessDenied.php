<?php

declare(strict_types=1);

namespace Phil\PagedMedia\Gotenberg\Exception;

/**
 * Credentials missing or wrong (401), or a URL that Gotenberg's outbound filter blocks (403): a downloaded file, a webhook or a timestamp authority.
 */
final class AccessDenied extends GotenbergFailure
{
}
