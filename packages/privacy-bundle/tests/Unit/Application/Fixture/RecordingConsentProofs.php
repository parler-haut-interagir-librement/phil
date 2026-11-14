<?php

declare(strict_types=1);

namespace Phil\PrivacyBundle\Tests\Unit\Application\Fixture;

use Override;
use Phil\PrivacyBundle\Application\Contract\ConsentProofs;
use Phil\PrivacyBundle\Domain\Consent\ConsentChoice;

final class RecordingConsentProofs implements ConsentProofs
{
    /**
     * @var list<ConsentChoice>
     */
    public array $recorded = [];

    #[Override]
    public function record(ConsentChoice $choice): void
    {
        $this->recorded[] = $choice;
    }
}
