<?php

declare(strict_types=1);

namespace Phil\PrivacyBundle\Tests\Unit\Application\Register\Fixture;

use Phil\PrivacyBundle\Domain\Attribute\PersonalData;

final class Ledger
{
    public function __construct(
        #[PersonalData('b_kind', retention: 'R-100')]
        public string $alpha = '',
        #[PersonalData('d_kind', retention: 'R-404')]
        public string $lost = '',
    ) {
    }
}
