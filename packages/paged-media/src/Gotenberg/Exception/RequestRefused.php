<?php

declare(strict_types=1);

namespace Phil\PagedMedia\Gotenberg\Exception;

/**
 * A request Gotenberg refuses (400, 405, 413, 415): a form field, a stage or a header missing or invalid, or a stage that failed on a file. The message names it.
 */
final class RequestRefused extends GotenbergFailure
{
}
