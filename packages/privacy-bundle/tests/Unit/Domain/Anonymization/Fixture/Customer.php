<?php

declare(strict_types=1);

namespace Phil\PrivacyBundle\Tests\Unit\Domain\Anonymization\Fixture;

use Phil\PrivacyBundle\Domain\Attribute\Anonymize;
use Phil\PrivacyBundle\Domain\Attribute\PersonalData;
use Phil\PrivacyBundle\Tests\Unit\Domain\Declaration\Fixture\BaseModel;

final class Customer extends BaseModel
{
    #[PersonalData('email')]
    #[Anonymize('email', options: ['domain' => 'example.org'])]
    public string $email = '';

    public string $plan = 'free';

    #[Anonymize('date_shift')]
    private string $birthDate = '';

    public function birthDate(): string
    {
        return $this->birthDate;
    }
}
