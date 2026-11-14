<?php

declare(strict_types=1);

namespace Phil\PrivacyBundle\Tests\Functional\App;

use Override;
use Phil\PrivacyBundle\Application\Contract\GuestLinkSender;
use Phil\PrivacyBundle\Domain\GuestRequest\GuestRequest;

/**
 * The links of the requests without an account, kept instead of sent.
 */
final class RecordingGuestLinks implements GuestLinkSender
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
