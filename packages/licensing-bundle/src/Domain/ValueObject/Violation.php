<?php

declare(strict_types=1);

namespace Phil\LicensingBundle\Domain\ValueObject;

use Phil\LicensingBundle\Domain\Compliance\ViolationCode;

/**
 * One reason a work cannot be published: its code, the license concerned, and the resource concerned by its position
 * among the attributions and its title, or `null` for the work itself.
 */
final readonly class Violation
{
    public function __construct(
        public ViolationCode $code,
        public ?string $license = null,
        public ?int $resource = null,
        public ?string $resourceTitle = null,
    ) {
    }
}
