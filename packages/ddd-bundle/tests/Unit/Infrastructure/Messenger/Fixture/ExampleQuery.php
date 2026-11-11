<?php

declare(strict_types=1);

namespace Phil\DddBundle\Tests\Unit\Infrastructure\Messenger\Fixture;

use Phil\DddBundle\Application\Bus\Query;

/**
 * @implements Query<string>
 */
final readonly class ExampleQuery implements Query
{
}
