<?php

declare(strict_types=1);

namespace {{namespace}};

use Phil\DddBundle\Application\Bus\Query;

/**
 * {{subject}}, through the query bus. Replace null by the type of the answer, here and in the handler.
 *
 * @see {{handler}}
 *
 * @implements Query<null>
 */
final readonly class {{class}} implements Query
{
}
