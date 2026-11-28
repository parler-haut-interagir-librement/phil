<?php

declare(strict_types=1);

namespace Phil\CodeOfConduct;

use DateTimeImmutable;

/**
 * A member accepted a version of the code of a scope: which one, the hash of the text they were shown, and when.
 */
final readonly class Acceptance
{
    public function __construct(
        public string $scope,
        public string $member,
        public int $version,
        public string $hash,
        public DateTimeImmutable $acceptedAt,
    ) {
        Identifier::check($scope, 'A scope');
        Identifier::check($member, 'A member');
    }
}
