<?php

declare(strict_types=1);

namespace Phil\UxKit\Tests\Functional\App;

/**
 * An object of the test application that a provider knows the picture of: a poster, by its name.
 */
final readonly class Poster
{
    public function __construct(public string $name)
    {
    }
}
