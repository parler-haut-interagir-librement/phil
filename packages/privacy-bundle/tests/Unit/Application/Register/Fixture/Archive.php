<?php

declare(strict_types=1);

namespace Phil\PrivacyBundle\Tests\Unit\Application\Register\Fixture;

use Phil\PrivacyBundle\Domain\Attribute\PersonalData;

final class Archive
{
    public function __construct(
        #[PersonalData('a_kind', retention: 'R-100')]
        public string $zone = '',
        #[PersonalData('c_kind', retention: 'R-100')]
        public string $beta = '',
    ) {
    }
}
