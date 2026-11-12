<?php

declare(strict_types=1);

namespace Phil\FormsAutosave\Domain\Model;

use DateTimeImmutable;
use DateTimeZone;
use Doctrine\ORM\Mapping as ORM;
use Phil\FormsAutosave\Domain\Exception\InvalidDraft;
use Phil\FormsAutosave\Domain\ValueObject\DraftKey;

/**
 * A draft kept on the server for one user: the user's keyed hash, the draft's key, a revision that grows by one at
 * each save so that a save replaces only the revision it read, the sealed content, when it was saved and when it
 * expires. A draft is a value; a save returns its next revision.
 *
 * The table holds no user identifier and no field in clear: the owner is a keyed hash, and the content is sealed by the
 * application's sealer. Times are UTC, to the second. The table is mapped for the application's schema tools and
 * migrations; the store of the bundle writes it by plain queries.
 */
#[ORM\Entity]
#[ORM\Table(name: self::TABLE)]
#[ORM\Index(columns: ['expires_at'])]
final class Draft
{
    public const string TABLE = 'phil_form_draft';

    /** The characters and length of an owner's hash. */
    private const string OWNER = '/^[a-f0-9]{64}$/D';

    private function __construct(
        #[ORM\Id]
        #[ORM\Column(name: 'owner', length: 64)]
        private readonly string $owner,
        #[ORM\Id]
        #[ORM\Column(name: 'draft_key', length: 64)]
        private readonly string $key,
        #[ORM\Column(name: 'revision')]
        private readonly int $revision,
        #[ORM\Column(name: 'payload', type: 'text')]
        private readonly string $payload,
        #[ORM\Column(name: 'saved_at')]
        private readonly DateTimeImmutable $savedAt,
        #[ORM\Column(name: 'expires_at')]
        private readonly DateTimeImmutable $expiresAt,
    ) {
    }

    /**
     * The first revision of a draft, saved now and kept for a number of seconds.
     *
     * @throws InvalidDraft when the owner is not a hash, or the duration is not positive
     */
    public static function first(string $owner, DraftKey $key, string $payload, DateTimeImmutable $now, int $seconds): self
    {
        $savedAt = self::second($now);

        return self::restore($owner, $key, 1, $payload, $savedAt, self::until($savedAt, $seconds));
    }

    /**
     * A draft as a store kept it.
     *
     * @throws InvalidDraft when the owner is not a hash, the revision is not positive, or it expires before it was saved
     */
    public static function restore(string $owner, DraftKey $key, int $revision, string $payload, DateTimeImmutable $savedAt, DateTimeImmutable $expiresAt): self
    {
        if (1 !== preg_match(self::OWNER, $owner)) {
            throw InvalidDraft::because('The owner of a draft is a hash of 64 hexadecimal digits.');
        }

        if ($revision < 1) {
            throw InvalidDraft::because('The revision of a draft is positive.');
        }

        if ($expiresAt <= $savedAt) {
            throw InvalidDraft::because('A draft expires after it was saved.');
        }

        return new self($owner, $key->value, $revision, $payload, $savedAt, $expiresAt);
    }

    /**
     * The next revision, with a new content, saved now and kept for a number of seconds from now.
     *
     * @throws InvalidDraft when the duration is not positive
     */
    public function next(string $payload, DateTimeImmutable $now, int $seconds): self
    {
        $savedAt = self::second($now);

        return new self($this->owner, $this->key, $this->revision + 1, $payload, $savedAt, self::until($savedAt, $seconds));
    }

    public function owner(): string
    {
        return $this->owner;
    }

    public function key(): DraftKey
    {
        return DraftKey::of($this->key);
    }

    public function revision(): int
    {
        return $this->revision;
    }

    public function payload(): string
    {
        return $this->payload;
    }

    public function savedAt(): DateTimeImmutable
    {
        return $this->savedAt;
    }

    public function expiresAt(): DateTimeImmutable
    {
        return $this->expiresAt;
    }

    /**
     * Whether the draft has expired at a moment: from its expiry on, it is as if it were not kept.
     */
    public function hasExpired(DateTimeImmutable $now): bool
    {
        return $now >= $this->expiresAt;
    }

    private static function second(DateTimeImmutable $now): DateTimeImmutable
    {
        return new DateTimeImmutable('@'.$now->getTimestamp())->setTimezone(new DateTimeZone('UTC'));
    }

    /**
     * @throws InvalidDraft when the duration is not positive
     */
    private static function until(DateTimeImmutable $savedAt, int $seconds): DateTimeImmutable
    {
        if ($seconds < 1) {
            throw InvalidDraft::because('A draft is kept for a positive number of seconds.');
        }

        return $savedAt->modify(\sprintf('+%d seconds', $seconds));
    }
}
