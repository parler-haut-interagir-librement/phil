<?php

declare(strict_types=1);

namespace Phil\CommentsBundle\Domain\Model;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;
use Phil\CommentsBundle\Domain\Exception\InvalidComment;
use Phil\CommentsBundle\Domain\Status\SuggestionState;
use Phil\CommentsBundle\Domain\Status\ThreadStatus;
use Phil\CommentsBundle\Domain\ValueObject\Anchor;
use Phil\CommentsBundle\Domain\ValueObject\Member;
use Phil\CommentsBundle\Domain\ValueObject\Suggestion;
use Phil\Resource\ResourceReference;

/**
 * A discussion about one passage of a resource's text, or about the whole page: where the passage is, who opened it,
 * whether it is resolved, and, when it proposes one, the text that would replace the passage and the decision taken
 * on it. The comments of the thread are kept apart, each with the thread's identifier.
 *
 * A thread on a passage follows it: when the text changes, it is moved to where the passage is found again, or
 * detached — orphaned — when the passage has disappeared, and attached again if it comes back. A thread on the page
 * has no passage, so it never moves nor is orphaned, and proposes no replacement. Times are UTC, to the second.
 */
#[ORM\Entity]
#[ORM\Table(name: self::TABLE)]
#[ORM\Index(columns: ['subject'])]
final class Thread
{
    public const string TABLE = 'phil_comment_thread';

    public const int MAX_SUGGESTION = 2000;

    #[ORM\Column(name: 'block', length: 64, nullable: true)]
    private ?string $block;

    #[ORM\Column(name: 'field', length: 1039, nullable: true)]
    private ?string $field;

    #[ORM\Column(name: 'start_offset', nullable: true)]
    private ?int $start;

    #[ORM\Column(name: 'quote', type: 'text', nullable: true)]
    private ?string $quote;

    #[ORM\Column(name: 'prefix', length: 255)]
    private string $prefix;

    #[ORM\Column(name: 'suffix', length: 255)]
    private string $suffix;

    #[ORM\Column(name: 'detached')]
    private bool $detached = false;

    #[ORM\Column(name: 'status', length: 16, enumType: ThreadStatus::class)]
    private ThreadStatus $status = ThreadStatus::Open;

    #[ORM\Column(name: 'suggestion_state', length: 16, nullable: true, enumType: SuggestionState::class)]
    private ?SuggestionState $suggestionState;

    #[ORM\Column(name: 'resolved_by', length: 320, nullable: true)]
    private ?string $resolvedBy = null;

    #[ORM\Column(name: 'resolved_at', nullable: true)]
    private ?DateTimeImmutable $resolvedAt = null;

    private function __construct(
        #[ORM\Id]
        #[ORM\Column(name: 'id', length: 36)]
        public readonly string $id,
        #[ORM\Column(name: 'subject', length: 320)]
        private readonly string $subject,
        ?Anchor $anchor,
        #[ORM\Column(name: 'author', length: 320)]
        private readonly string $author,
        #[ORM\Column(name: 'created_at')]
        private readonly DateTimeImmutable $createdAt,
        #[ORM\Column(name: 'suggestion', type: 'text', nullable: true)]
        private readonly ?string $suggestion,
    ) {
        $this->place($anchor);
        $this->suggestionState = null === $suggestion ? null : SuggestionState::Pending;
    }

    /**
     * A new thread, open, on a passage or, when `$anchor` is `null`, on the whole page; on a passage, it proposes a
     * replacement when `$suggestion` is a text — empty to propose its removal.
     *
     * @throws InvalidComment when the identifier is not 1 to 36 characters, the suggestion longer than 2000 or made on
     *                        the page, or the author named by an email address
     */
    public static function open(string $id, ResourceReference $subject, ?Anchor $anchor, ResourceReference $author, DateTimeImmutable $now, ?string $suggestion = null): self
    {
        if ('' === $id || \strlen($id) > 36) {
            throw InvalidComment::because('A thread is identified by 1 to 36 characters.');
        }

        if (!$anchor instanceof Anchor && null !== $suggestion) {
            throw InvalidComment::because('A thread on the whole page has no passage to replace: it suggests nothing.');
        }

        if (null !== $suggestion && (!mb_check_encoding($suggestion, 'UTF-8') || mb_strlen($suggestion) > self::MAX_SUGGESTION)) {
            throw InvalidComment::because(\sprintf('A suggestion is a UTF-8 text of at most %d characters.', self::MAX_SUGGESTION));
        }

        return new self($id, $subject->toString(), $anchor, Member::reference($author), self::second($now), $suggestion);
    }

    public function subject(): ResourceReference
    {
        return ResourceReference::fromString($this->subject);
    }

    /**
     * Where the passage is; `null` for a thread on the whole page.
     */
    public function anchor(): ?Anchor
    {
        return null === $this->start ? null : Anchor::of($this->block, $this->field, $this->start, $this->quote ?? '', $this->prefix, $this->suffix);
    }

    /**
     * Whether the thread is about the whole page rather than a passage.
     */
    public function isOnPage(): bool
    {
        return null === $this->quote;
    }

    public function author(): ResourceReference
    {
        return ResourceReference::fromString($this->author);
    }

    public function isBy(ResourceReference $author): bool
    {
        return $this->author === $author->toString();
    }

    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function status(): ThreadStatus
    {
        return $this->status;
    }

    public function isDetached(): bool
    {
        return $this->detached;
    }

    /**
     * The proposed replacement of the passage and the decision on it, `null` when the thread proposes none.
     */
    public function suggestion(): ?Suggestion
    {
        return $this->suggestionState instanceof SuggestionState ? new Suggestion($this->suggestion ?? '', $this->suggestionState) : null;
    }

    public function resolvedBy(): ?ResourceReference
    {
        return null === $this->resolvedBy ? null : ResourceReference::fromString($this->resolvedBy);
    }

    public function resolvedAt(): ?DateTimeImmutable
    {
        return $this->resolvedAt;
    }

    /**
     * Resolves the thread; resolving it again changes nothing.
     *
     * @throws InvalidComment when the actor is named by an email address
     */
    public function resolve(ResourceReference $by, DateTimeImmutable $now): void
    {
        if (ThreadStatus::Resolved === $this->status) {
            return;
        }

        $resolvedBy = Member::reference($by);
        $this->status = ThreadStatus::Resolved;
        $this->resolvedBy = $resolvedBy;
        $this->resolvedAt = self::second($now);
    }

    /**
     * Opens the thread again; a decision taken on its suggestion stands.
     */
    public function reopen(): void
    {
        $this->status = ThreadStatus::Open;
        $this->resolvedBy = null;
        $this->resolvedAt = null;
    }

    /**
     * Accepts the suggestion, once the passage has been replaced, and resolves the thread.
     *
     * @throws InvalidComment when the thread has no suggestion waiting for a decision
     */
    public function accept(ResourceReference $by, DateTimeImmutable $now): void
    {
        $this->decide(SuggestionState::Accepted, $by, $now);
    }

    /**
     * Rejects the suggestion and resolves the thread.
     *
     * @throws InvalidComment when the thread has no suggestion waiting for a decision
     */
    public function reject(ResourceReference $by, DateTimeImmutable $now): void
    {
        $this->decide(SuggestionState::Rejected, $by, $now);
    }

    /**
     * Puts the thread on the passage where it was found again, attached.
     *
     * @throws InvalidComment when the thread is on the whole page
     */
    public function moveTo(Anchor $anchor): void
    {
        $this->onPassage();
        $this->place($anchor);
        $this->detached = false;
    }

    /**
     * Detaches — orphans — the thread from a passage that has disappeared; its last place is kept.
     *
     * @throws InvalidComment when the thread is on the whole page
     */
    public function detach(): void
    {
        $this->onPassage();
        $this->detached = true;
    }

    private function onPassage(): void
    {
        if ($this->isOnPage()) {
            throw InvalidComment::because('A thread on the whole page has no passage to follow.');
        }
    }

    private function decide(SuggestionState $state, ResourceReference $by, DateTimeImmutable $now): void
    {
        if (SuggestionState::Pending !== $this->suggestionState) {
            throw InvalidComment::because('The thread has no suggestion waiting for a decision.');
        }

        $this->suggestionState = $state;
        $this->resolve($by, $now);
    }

    private function place(?Anchor $anchor): void
    {
        $this->block = $anchor?->block;
        $this->field = $anchor?->field;
        $this->start = $anchor?->start;
        $this->quote = $anchor?->quote;
        $this->prefix = $anchor->prefix ?? '';
        $this->suffix = $anchor->suffix ?? '';
    }

    private static function second(DateTimeImmutable $moment): DateTimeImmutable
    {
        return new DateTimeImmutable('@'.$moment->getTimestamp());
    }
}
