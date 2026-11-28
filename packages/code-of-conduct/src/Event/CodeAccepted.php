<?php

declare(strict_types=1);

namespace Phil\CodeOfConduct\Event;

/**
 * A member accepted a version of the code of a scope. It names the scope, the member and the number only.
 */
final readonly class CodeAccepted
{
    public function __construct(
        public string $scope,
        public string $member,
        public int $version,
    ) {
    }
}
