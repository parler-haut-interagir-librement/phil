<?php

declare(strict_types=1);

namespace Phil\CommentsBundle\Domain\Model;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;
use Phil\CommentsBundle\Domain\Exception\InvalidComment;
use Phil\CommentsBundle\Domain\ValueObject\Member;
use Phil\Resource\ResourceReference;

/**
 * One message of a thread: its author, a plain text — never HTML —, when it was written and last edited.
 */
#[ORM\Entity]
#[ORM\Table(name: self::TABLE)]
#[ORM\Index(columns: ['thread'])]
final class Comment
{
    public const string TABLE = 'phil_comment';

    public const int MAX_BODY = 10000;

    #[ORM\Column(name: 'body', type: 'text')]
    private string $body;

    #[ORM\Column(name: 'edited_at', nullable: true)]
    private ?DateTimeImmutable $editedAt = null;

    private function __construct(
        #[ORM\Id]
        #[ORM\Column(name: 'id', length: 36)]
        public readonly string $id,
        #[ORM\Column(name: 'thread', length: 36)]
        public readonly string $thread,
        #[ORM\Column(name: 'author', length: 320)]
        private readonly string $author,
        string $body,
        #[ORM\Column(name: 'created_at')]
        private readonly DateTimeImmutable $createdAt,
    ) {
        $this->body = $this->checked($body);
    }

    /**
     * @throws InvalidComment when the identifier is not 1 to 36 characters, the body, trimmed, not 1 to 10000, or the
     *                        author named by an email address
     */
    public static function write(string $id, Thread $thread, ResourceReference $author, string $body, DateTimeImmutable $now): self
    {
        if ('' === $id || \strlen($id) > 36) {
            throw InvalidComment::because('A comment is identified by 1 to 36 characters.');
        }

        return new self($id, $thread->id, Member::reference($author), $body, new DateTimeImmutable('@'.$now->getTimestamp()));
    }

    public function author(): ResourceReference
    {
        return ResourceReference::fromString($this->author);
    }

    public function isBy(ResourceReference $author): bool
    {
        return $this->author === $author->toString();
    }

    public function body(): string
    {
        return $this->body;
    }

    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function editedAt(): ?DateTimeImmutable
    {
        return $this->editedAt;
    }

    /**
     * @throws InvalidComment when the body, trimmed, is not 1 to 10000 characters
     */
    public function edit(string $body, DateTimeImmutable $now): void
    {
        $this->body = $this->checked($body);
        $this->editedAt = new DateTimeImmutable('@'.$now->getTimestamp());
    }

    private function checked(string $body): string
    {
        $body = trim($body);

        if ('' === $body || !mb_check_encoding($body, 'UTF-8') || mb_strlen($body) > self::MAX_BODY) {
            throw InvalidComment::because(\sprintf('A comment is a UTF-8 text of 1 to %d characters.', self::MAX_BODY));
        }

        return $body;
    }
}
