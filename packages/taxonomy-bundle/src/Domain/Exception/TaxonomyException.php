<?php

declare(strict_types=1);

namespace Phil\TaxonomyBundle\Domain\Exception;

use Throwable;

/**
 * Everything the bundle throws, catchable with a single `catch`.
 */
interface TaxonomyException extends Throwable
{
}
