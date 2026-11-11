<?php

declare(strict_types=1);

namespace Phil\Checkin;

use DateTimeImmutable;
use Phil\Checkin\Exception\InvalidCheckin;

/**
 * What a code stands for, as the application that issued it describes it: a ticket, a registration, a membership
 * card, a badge. The library only reads what the check-in needs — which checkpoints it opens, from when to when, how
 * many entries it allows and whether it is revoked — and a label the staff sees, such as the holder's name.
 */
final readonly class Credential
{
    public const int MAX_ID_LENGTH = 128;

    public const int MAX_LABEL_LENGTH = 200;

    public const int MAX_ENTRIES = 1_000_000;

    public const string ID_PATTERN = '/^[\x21-\x7E]{1,128}$/D';

    private const string LABEL_PATTERN = '/^\P{Cc}{1,200}$/Du';

    /**
     * @param list<CheckpointId> $checkpoints
     */
    private function __construct(
        public string $id,
        public ?string $label,
        public array $checkpoints,
        public ?DateTimeImmutable $validFrom,
        public ?DateTimeImmutable $validUntil,
        public ?int $maxEntries,
        public CredentialStatus $status,
    ) {
    }

    /**
     * @param string             $id          the application's identifier of the credential, printable ASCII, at most 128 bytes
     * @param ?string            $label       what the staff sees: a name, a category of ticket; null shows nothing
     * @param list<CheckpointId> $checkpoints the checkpoints it opens; none opens them all
     * @param ?int               $maxEntries  how many times it lets its holder in; null without limit
     *
     * @throws InvalidCheckin
     */
    public static function of(
        string $id,
        ?string $label = null,
        array $checkpoints = [],
        ?DateTimeImmutable $validFrom = null,
        ?DateTimeImmutable $validUntil = null,
        ?int $maxEntries = 1,
        CredentialStatus $status = CredentialStatus::Active,
    ): self {
        if (1 !== preg_match(self::ID_PATTERN, $id)) {
            throw InvalidCheckin::because(\sprintf('A credential identifier is 1 to %d printable ASCII characters.', self::MAX_ID_LENGTH));
        }

        if (null !== $label && 1 !== preg_match(self::LABEL_PATTERN, $label)) {
            throw InvalidCheckin::because(\sprintf('A credential label is valid UTF-8 without control characters, 1 to %d characters.', self::MAX_LABEL_LENGTH));
        }

        if ($validFrom instanceof DateTimeImmutable && $validUntil instanceof DateTimeImmutable && $validFrom->getTimestamp() > $validUntil->getTimestamp()) {
            throw InvalidCheckin::because('A credential cannot close before it opens.');
        }

        if (null !== $maxEntries && ($maxEntries < 1 || $maxEntries > self::MAX_ENTRIES)) {
            throw InvalidCheckin::because(\sprintf('A credential allows 1 to %d entries, or any number.', self::MAX_ENTRIES));
        }

        return new self($id, $label, $checkpoints, $validFrom, $validUntil, $maxEntries, $status);
    }

    /**
     * Whether it opens a checkpoint: one it lists, or any when it lists none.
     */
    public function opens(CheckpointId $checkpoint): bool
    {
        if ([] === $this->checkpoints) {
            return true;
        }

        return array_any($this->checkpoints, static fn (CheckpointId $opened): bool => $opened->equals($checkpoint));
    }
}
