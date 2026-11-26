<?php

declare(strict_types=1);

namespace {{namespace}};

use Phil\DddBundle\Application\Bus\Command;

/**
 * {{subject}}, through the command bus. A command carries identifiers and scalars only, and returns nothing.
 *
 * @see {{handler}}
 */
final readonly class {{class}} implements Command
{
}
