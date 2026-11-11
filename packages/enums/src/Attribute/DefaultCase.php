<?php

declare(strict_types=1);

namespace Phil\Enums\Attribute;

use Attribute;

/**
 * Marks the case `default()` returns: the one a new record starts with, declared once on the enum rather than in
 * every entity and form. An enum marks at most one.
 */
#[Attribute(Attribute::TARGET_CLASS_CONSTANT)]
final readonly class DefaultCase
{
}
