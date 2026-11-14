<?php

declare(strict_types=1);

namespace Phil\PrivacyBundle\Tests\Unit\Application\Fixture;

use Override;
use Phil\PrivacyBundle\Application\Contract\GuestLinkSender;
use Phil\PrivacyBundle\Domain\GuestRequest\GuestRequest;

final class RecordingLinkSender implements GuestLinkSender
{
    /**
     * @var list<array{GuestRequest, string}>
     */
    public array $sent = [];

    #[Override]
    public function send(GuestRequest $request, string $token): void
    {
        $this->sent[] = [$request, $token];
    }
}
