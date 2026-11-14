<?php

declare(strict_types=1);

namespace Phil\PrivacyBundle\Tests\Unit\Domain\Declaration\Fixture;

use Phil\PrivacyBundle\Domain\Attribute\PersonalData;

final class Member extends BaseModel
{
    public string $status = 'active';

    public function __construct(
        #[PersonalData('email', retention: 'R-002')]
        public string $email = '',
        #[PersonalData('person_name')]
        public string $displayName = '',
    ) {
    }
}
