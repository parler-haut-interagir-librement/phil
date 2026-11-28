<?php

declare(strict_types=1);

namespace Phil\ProductBundles\Exception;

use Throwable;

/**
 * Every exception of the library. Its message names the bundle, the component or the field at fault, never a price.
 */
interface ProductBundlesException extends Throwable
{
}
