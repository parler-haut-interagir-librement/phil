<?php

declare(strict_types=1);

namespace Phil\PagedMedia\Gotenberg\Exception;

use Phil\PagedMedia\Exception\PagedMediaException;

/**
 * Implemented by every exception of the Gotenberg adapter: a request refused before it is sent, and an answer of
 * Gotenberg that is not a success.
 */
interface GotenbergException extends PagedMediaException
{
}
