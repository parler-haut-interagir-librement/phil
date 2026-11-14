<?php

declare(strict_types=1);

namespace Phil\NotificationsBundle\Tests\Unit\Fixture;

use Override;
use Phil\NotificationsBundle\Application\Contract\CurrentRecipient;

final class Current implements CurrentRecipient
{
    public function __construct(
        public ?string $id = '42',
    ) {
    }

    #[Override]
    public function id(): ?string
    {
        return $this->id;
    }
}
