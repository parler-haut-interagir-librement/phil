<?php

declare(strict_types=1);

namespace Phil\OpenBadgesBundle\Infrastructure\Http;

use Phil\OpenBadges\Exception\InvalidCredential;
use Phil\OpenBadges\Recipient;
use Phil\OpenBadges\Status\StatusListEntry;
use Phil\OpenBadges\Verification\CredentialVerifier;
use Phil\OpenBadgesBundle\Application\Contract\StatusLists;
use Phil\OpenBadgesBundle\Application\StatusListContent;
use Phil\OpenBadgesBundle\Infrastructure\Routing\StatusListUrls;
use Psr\Clock\ClockInterface;
use SensitiveParameter;

/**
 * Checks a credential shown to the verification page: its proof and period through the verifier, its status when its
 * list is one of the application's — read from the lists themselves, nothing fetched —, and, when the visitor gives
 * one, whether the identity is the recipient's.
 */
final readonly class CredentialCheck
{
    public function __construct(
        private CredentialVerifier $verifier,
        private ClockInterface $clock,
        private StatusListUrls $urls,
        private ?StatusLists $lists = null,
    ) {
    }

    public function check(string $credential, #[SensitiveParameter] string $identity = ''): CheckResult
    {
        try {
            $verified = $this->verifier->verify($credential, $this->clock->now());
            $status = $verified->status();
        } catch (InvalidCredential $invalidCredential) {
            return CheckResult::refused($invalidCredential->getMessage());
        }

        $subject = $verified->credential['credentialSubject'] ?? null;

        return new CheckResult(
            $verified,
            $this->status($status),
            '' === trim($identity) ? null : \is_array($subject) && Recipient::identifies($subject, $identity),
        );
    }

    /**
     * Whether the credential's status applies — revoked or suspended —, or null when it has none or the list is not
     * the application's.
     */
    private function status(?StatusListEntry $entry): ?bool
    {
        $list = $entry instanceof StatusListEntry ? $this->urls->listOf($entry->statusListCredential) : null;
        $content = null === $list ? null : $this->lists?->find($list);

        if (!$entry instanceof StatusListEntry || !$content instanceof StatusListContent || $content->purpose !== $entry->purpose) {
            return null;
        }

        return \in_array($entry->index, $content->set, true);
    }
}
