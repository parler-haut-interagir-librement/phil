<?php

declare(strict_types=1);

namespace Phil\MentionsBundle\Domain\Model;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;
use Phil\MentionsBundle\Domain\Exception\InvalidMention;
use Phil\MentionsBundle\Domain\ValueObject\MemberIdentifier;
use Phil\Resource\ResourceReference;

/**
 * A member mentioned in a subject — a comment, a proposal, a page — and notified of it: who mentioned them and when.
 * A subject mentions a member once; the row stays while the text keeps the mention, so that an edit notifies only
 * the members it adds.
 */
#[ORM\Entity]
#[ORM\Table(name: self::TABLE)]
#[ORM\UniqueConstraint(name: 'phil_mention_subject_member', columns: ['subject', 'member'])]
#[ORM\Index(name: 'phil_mention_member', columns: ['member'])]
final class RecordedMention
{
    public const string TABLE = 'phil_mention';

    /** The longest reference kept, a kind of 64 bytes, a colon and an identifier of 255. */
    public const int REFERENCE_LENGTH = 320;

    private function __construct(
        #[ORM\Id]
        #[ORM\Column(name: 'id', length: 36)]
        public readonly string $id,
        #[ORM\Column(name: 'subject', length: self::REFERENCE_LENGTH)]
        private readonly string $subject,
        #[ORM\Column(name: 'member', length: self::REFERENCE_LENGTH)]
        private readonly string $member,
        #[ORM\Column(name: 'author', length: self::REFERENCE_LENGTH)]
        private readonly string $author,
        #[ORM\Column(name: 'created_at')]
        private readonly DateTimeImmutable $createdAt,
    ) {
    }

    /**
     * @throws InvalidMention when the identifier is not 1 to 36 characters, or the member or the author is named by an
     *                        email address
     */
    public static function record(string $id, ResourceReference $subject, ResourceReference $member, ResourceReference $author, DateTimeImmutable $now): self
    {
        if ('' === $id || \strlen($id) > 36) {
            throw InvalidMention::because('A mention is identified by 1 to 36 characters.');
        }

        return new self($id, $subject->toString(), MemberIdentifier::checked($member->toString()), MemberIdentifier::checked($author->toString()), new DateTimeImmutable('@'.$now->getTimestamp()));
    }

    public function subject(): ResourceReference
    {
        return ResourceReference::fromString($this->subject);
    }

    public function member(): ResourceReference
    {
        return ResourceReference::fromString($this->member);
    }

    public function author(): ResourceReference
    {
        return ResourceReference::fromString($this->author);
    }

    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }
}
