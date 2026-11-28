<?php

declare(strict_types=1);

namespace Phil\CodeOfConduct\Event;

/**
 * A new version of the code of a scope was published. It names the scope and the number only; a listener tells the members when the change is major.
 */
final readonly class CodeVersionPublished
{
    public function __construct(
        public string $scope,
        public int $version,
    ) {
    }
}
