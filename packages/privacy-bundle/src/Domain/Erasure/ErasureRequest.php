<?php

declare(strict_types=1);

namespace Phil\PrivacyBundle\Domain\Erasure;

use DateTimeImmutable;
use Phil\PrivacyBundle\Domain\Exception\InvalidPrivacyValue;
use Phil\PrivacyBundle\Domain\Subject\DataSubject;

/**
 * A request to erase a person, from the moment it is opened until every module erased them or an operator closed it.
 *
 * **Who it is about.** A member's request keeps the opaque identifier of the person, never an address. A request made
 * without an account keeps the identifier of the request it came from instead, whose address stays sealed in its own
 * table: the person is resolved in the worker that runs each module, and no message carries them.
 *
 * **What it promised.** The version of the erasure inventory it was opened with is kept, and its modules are its steps.
 *
 * **Who may read it.** Its progress is shown to the person, to an operator, and, for a request made without an
 * account, to whoever holds the secret of its progress link, of which only a SHA-256 hash is kept.
 */
final readonly class ErasureRequest
{
    public const string ID_PATTERN = '/^[0-9a-f]{32}$/D';

    public const string TOKEN_PATTERN = '/^[A-Za-z0-9_-]{43}$/D';

    public function __construct(
        public string $id,
        public ?DataSubject $subject,
        public ?string $guestRequestId,
        public string $inventoryVersion,
        public RequestState $state,
        public DateTimeImmutable $requestedAt,
        public ?DateTimeImmutable $closedAt = null,
        public ?string $accessHash = null,
    ) {
        if (1 !== preg_match(self::ID_PATTERN, $id)) {
            throw InvalidPrivacyValue::because('An erasure request identifier is 32 lowercase hexadecimal characters.');
        }

        if ($subject instanceof DataSubject === (null !== $guestRequestId)) {
            throw InvalidPrivacyValue::because('An erasure request names either a person or the request made without an account it came from.');
        }

        if (null !== $guestRequestId && 1 !== preg_match(self::ID_PATTERN, $guestRequestId)) {
            throw InvalidPrivacyValue::because('A request made without an account is named by 32 lowercase hexadecimal characters.');
        }

        if (1 !== preg_match(ErasureInventory::VERSION_PATTERN, $inventoryVersion)) {
            throw InvalidPrivacyValue::because('The version of an erasure inventory is 16 lowercase hexadecimal characters.');
        }

        if (null !== $accessHash && 1 !== preg_match('/^[0-9a-f]{64}$/D', $accessHash)) {
            throw InvalidPrivacyValue::because('An erasure request keeps the SHA-256 of the secret of its progress link, in hexadecimal.');
        }
    }

    /**
     * A new member's request, running.
     *
     * @param string|null $id the identifier, such as the one of the account deletion it belongs to; drawn at random when null
     */
    public static function forSubject(DataSubject $subject, string $inventoryVersion, DateTimeImmutable $now, ?string $id = null): self
    {
        return new self($id ?? self::newId(), $subject, null, $inventoryVersion, RequestState::Running, $now);
    }

    /**
     * A new request made without an account, running, and the secret of its progress link, which is never kept.
     *
     * @return array{self, string}
     */
    public static function forGuest(string $guestRequestId, string $inventoryVersion, DateTimeImmutable $now): array
    {
        $token = sodium_bin2base64(random_bytes(32), \SODIUM_BASE64_VARIANT_URLSAFE_NO_PADDING);

        return [new self(self::newId(), null, $guestRequestId, $inventoryVersion, RequestState::Running, $now, null, hash('sha256', $token)), $token];
    }

    /**
     * Whether a secret opens the progress of this request, compared in constant time; never for a member's request.
     */
    public function isOpenedBy(string $token): bool
    {
        return null !== $this->accessHash && 1 === preg_match(self::TOKEN_PATTERN, $token) && hash_equals($this->accessHash, hash('sha256', $token));
    }

    /**
     * Whether the request is about this person; never for a request made without an account.
     */
    public function isAbout(DataSubject $subject): bool
    {
        return $this->subject?->equals($subject) ?? false;
    }

    private static function newId(): string
    {
        return sodium_bin2hex(random_bytes(16));
    }
}
