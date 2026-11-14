<?php

declare(strict_types=1);

namespace Phil\PrivacyBundle\Tests\Unit\Domain\Declaration\Fixture;

use Phil\PrivacyBundle\Domain\Attribute\PersonalData;

abstract class BaseModel
{
    #[PersonalData('ip', retention: 'R-009')]
    private string $createdFrom = '';

    public function createdFrom(): string
    {
        return $this->createdFrom;
    }
}
