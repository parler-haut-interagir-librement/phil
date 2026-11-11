<?php

declare(strict_types=1);

namespace Phil\OpenBadges\Status;

use DateTimeImmutable;
use Phil\OpenBadges\Achievement;
use Phil\OpenBadges\Context;
use Phil\OpenBadges\Credentials;
use Phil\OpenBadges\Exception\InvalidBadge;
use Phil\OpenBadges\Exception\InvalidCredential;
use Phil\OpenBadges\Issuer;

/**
 * Builds the `BitstringStatusListCredential` the issuer publishes at a status list's URL, and reads a credential's
 * status from it once the caller has verified it.
 */
final readonly class StatusListIssuer
{
    public function __construct(
        private Issuer $issuer,
    ) {
    }

    /**
     * The status list credential, unsecured.
     *
     * @return array<string, mixed>
     */
    public function credential(string $url, StatusList $list, string $purpose, DateTimeImmutable $validFrom, ?DateTimeImmutable $validUntil = null): array
    {
        Achievement::checkUrl($url, 'status list');

        if (!\in_array($purpose, StatusListEntry::PURPOSES, true)) {
            throw InvalidBadge::because(\sprintf('The purpose of a status is "revocation" or "suspension"; "%s" is not.', $purpose));
        }

        return [
            '@context' => [Context::CREDENTIALS_V2],
            'id' => $url,
            'type' => ['VerifiableCredential', 'BitstringStatusListCredential'],
            'issuer' => $this->issuer->id,
            ...Credentials::period($validFrom, $validUntil),
            'credentialSubject' => [
                'id' => $url.'#list',
                'type' => 'BitstringStatusList',
                'statusPurpose' => $purpose,
                'encodedList' => $list->encode(),
            ],
        ];
    }

    /**
     * Whether the entry's purpose applies — the credential is revoked, or suspended —, read from its status list
     * credential, verified beforehand by the caller.
     *
     * @param array<array-key, mixed> $statusListCredential
     *
     * @throws InvalidCredential when the list is not the entry's, or not issued by this issuer
     */
    public function applies(StatusListEntry $entry, array $statusListCredential): bool
    {
        $subject = $statusListCredential['credentialSubject'] ?? null;
        $issuer = $statusListCredential['issuer'] ?? null;

        if (($statusListCredential['id'] ?? null) !== $entry->statusListCredential || (\is_array($issuer) ? $issuer['id'] ?? null : $issuer) !== $this->issuer->id) {
            throw InvalidCredential::because("The status list is not the one of the credential, or not its issuer's.");
        }

        if (!\is_array($subject) || ($subject['statusPurpose'] ?? null) !== $entry->purpose || !\is_string($subject['encodedList'] ?? null)) {
            throw InvalidCredential::because(\sprintf('The status list does not hold the statuses of "%s".', $entry->purpose));
        }

        return StatusList::decode($subject['encodedList'])->isSet($entry->index);
    }
}
