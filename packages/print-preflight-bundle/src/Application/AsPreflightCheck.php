<?php

declare(strict_types=1);

namespace Phil\PrintPreflightBundle\Application;

use Attribute;

/**
 * Adds a `Check` of the application to every preflight, after the built-in checks: an ink limit, a rule of the house.
 * Its findings take the severity and the ignored codes of the profile like the others.
 */
#[Attribute(Attribute::TARGET_CLASS)]
final readonly class AsPreflightCheck
{
}
