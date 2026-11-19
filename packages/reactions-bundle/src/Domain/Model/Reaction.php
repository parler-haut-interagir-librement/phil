<?php

declare(strict_types=1);

namespace Phil\ReactionsBundle\Domain\Model;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;
use Phil\ReactionsBundle\Domain\Exception\InvalidReaction;
use Phil\ReactionsBundle\Domain\ValueObject\ReactionDefinition;
use Phil\Resource\ResourceReference;

/**
 * One reaction of one member to one content: a member gives each code at most once per content, and as many codes as
 * the site allows; changing the only one keeps the row.
 *
 * The member is a stable identifier, never a name or an address: one that contains `@` is refused. The mapping gives the
 * schema tools the table, its unique constraint, which also serves the lookups by content, and the index that lets a
 * counter be read from the index alone. The moment is kept in UTC, whatever the time zone of the server or of the
 * connection.
 */
#[ORM\Entity]
#[ORM\Table(name: self::TABLE)]
#[ORM\UniqueConstraint(name: 'phil_reaction_member', columns: ['content', 'member_id', 'code'])]
#[ORM\Index(name: 'phil_reaction_count', columns: ['content', 'code'])]
#[ORM\Index(name: 'phil_reaction_of_member', columns: ['member_id'])]
final class Reaction
{
    public const string TABLE = 'phil_reaction';

    private const int MAX_MEMBER_BYTES = 255;

    private function __construct(
        #[ORM\Id]
        #[ORM\Column(name: 'id', length: 36)]
        public readonly string $id,
        #[ORM\Column(name: 'content', length: 320)]
        public readonly string $content,
        #[ORM\Column(name: 'member_id', length: 255)]
        public readonly string $member,
        #[ORM\Column(name: 'code', length: 64)]
        private string $code,
        #[ORM\Column(name: 'reacted_at', type: 'datetime_utc_immutable')]
        private DateTimeImmutable $reactedAt,
    ) {
    }

    /**
     * @throws InvalidReaction when the member or the code cannot be used
     */
    public static function of(string $id, ResourceReference $content, string $member, string $code, DateTimeImmutable $now): self
    {
        self::member($member);

        return new self($id, $content->toString(), $member, ReactionDefinition::code($code), self::second($now));
    }

    /**
     * Checks that a member identifier can be kept: 1 to 255 bytes, and no `@`, so that an email address never stands for
     * a member. The identifier is left out of the message, which may reach a log.
     *
     * @throws InvalidReaction when it cannot
     */
    public static function member(string $member): string
    {
        if ('' === $member || \strlen($member) > self::MAX_MEMBER_BYTES) {
            throw InvalidReaction::because(\sprintf('A member identifier is 1 to %d bytes.', self::MAX_MEMBER_BYTES));
        }

        if (str_contains($member, '@')) {
            throw InvalidReaction::because('A member identifier is a stable identifier the site gives, never an email address: it cannot contain "@".');
        }

        return $member;
    }

    /**
     * A reaction read back from a store.
     */
    public static function restored(string $id, string $content, string $member, string $code, DateTimeImmutable $reactedAt): self
    {
        return new self($id, $content, $member, $code, $reactedAt);
    }

    public function code(): string
    {
        return $this->code;
    }

    public function reactedAt(): DateTimeImmutable
    {
        return $this->reactedAt;
    }

    /**
     * @throws InvalidReaction when it is not a code
     */
    public function changeTo(string $code, DateTimeImmutable $now): void
    {
        $this->code = ReactionDefinition::code($code);
        $this->reactedAt = self::second($now);
    }

    /**
     * Stores keep seconds: the reaction holds what a store gives back.
     */
    private static function second(DateTimeImmutable $moment): DateTimeImmutable
    {
        return $moment->setTimestamp($moment->getTimestamp());
    }
}
