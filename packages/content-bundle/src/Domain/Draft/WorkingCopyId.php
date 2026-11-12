<?php

declare(strict_types=1);

namespace Phil\ContentBundle\Domain\Draft;

use Phil\DddBundle\Domain\Identifier\Identifier;

/**
 * The identifier of a working copy, a UUIDv7.
 */
final readonly class WorkingCopyId extends Identifier
{
}
