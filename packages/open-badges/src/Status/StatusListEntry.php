<?php

declare(strict_types=1);

namespace Phil\OpenBadges\Status;

use Phil\OpenBadges\Achievement;
use Phil\OpenBadges\Exception\InvalidBadge;
use Phil\OpenBadges\Exception\InvalidCredential;

/**
 * Where a credential's status is kept: a position in a Bitstring Status List, published as a credential at a URL, for
 * a purpose — `revocation`, for good, or `suspension`, for a while. The credential carries it as its
 * `credentialStatus`; the bit at its position says whether the purpose applies.
 */
final readonly class StatusListEntry
{
    public const array PURPOSES = ['revocation', 'suspension'];

    public function __construct(
        public string $statusListCredential,
        public int $index,
        public string $purpose = 'revocation',
    ) {
        Achievement::checkUrl($statusListCredential, 'status list');

        if ($index < 0 || $index >= StatusList::MAX_SIZE) {
            throw InvalidBadge::because(\sprintf('The index in a status list is from 0 to %d.', StatusList::MAX_SIZE - 1));
        }

        if (!\in_array($purpose, self::PURPOSES, true)) {
            throw InvalidBadge::because(\sprintf('The purpose of a status is "revocation" or "suspension"; "%s" is not.', $purpose));
        }
    }

    /**
     * The entry a credential carries.
     *
     * @param array<array-key, mixed> $status
     *
     * @throws InvalidCredential
     */
    public static function fromArray(array $status): self
    {
        $index = $status['statusListIndex'] ?? null;

        if ('BitstringStatusListEntry' !== ($status['type'] ?? null) || !\is_string($status['statusListCredential'] ?? null) || !\is_string($index) || 1 !== preg_match('/^(0|[1-9]\d{0,8})$/D', $index) || !\is_string($status['statusPurpose'] ?? null)) {
            throw InvalidCredential::because('The status of the credential is not a BitstringStatusListEntry.');
        }

        try {
            return new self($status['statusListCredential'], (int) $index, $status['statusPurpose']);
        } catch (InvalidBadge $invalidBadge) {
            throw InvalidCredential::because($invalidBadge->getMessage());
        }
    }

    /**
     * The entry as a `credentialStatus`.
     *
     * @return array{id: string, type: 'BitstringStatusListEntry', statusPurpose: string, statusListIndex: string, statusListCredential: string}
     */
    public function toArray(): array
    {
        return [
            'id' => $this->statusListCredential.'#'.$this->index,
            'type' => 'BitstringStatusListEntry',
            'statusPurpose' => $this->purpose,
            'statusListIndex' => (string) $this->index,
            'statusListCredential' => $this->statusListCredential,
        ];
    }
}
