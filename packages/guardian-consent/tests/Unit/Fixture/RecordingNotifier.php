<?php

declare(strict_types=1);

namespace Phil\GuardianConsent\Tests\Unit\Fixture;

use Override;
use Phil\GuardianConsent\ConfirmationToken;
use Phil\GuardianConsent\ConsentRequest;
use Phil\GuardianConsent\Contract\Notifier;
use Phil\GuardianConsent\MemberId;

/**
 * A notifier that keeps what it is asked to send.
 */
final class RecordingNotifier implements Notifier
{
    /** @var list<array{ConsentRequest, ConfirmationToken}> */
    public array $confirmations = [];

    /** @var list<string> */
    public array $handoverNotices = [];

    #[Override]
    public function sendConfirmation(ConsentRequest $request, ConfirmationToken $token): void
    {
        $this->confirmations[] = [$request, $token];
    }

    #[Override]
    public function sendHandoverNotice(MemberId $member): void
    {
        $this->handoverNotices[] = $member->value;
    }

    /**
     * The text of the last token sent.
     */
    public function lastToken(): string
    {
        $last = end($this->confirmations);

        return false === $last ? '' : $last[1]->toString();
    }
}
