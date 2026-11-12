<?php

declare(strict_types=1);

namespace Phil\TranslationBundle\Domain\Model;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;
use Phil\TranslationBundle\Domain\Exception\InvalidRecord;
use Phil\TranslationMemory\LanguageTag;
use Phil\TranslationMemory\Segment;

/**
 * A comment on one segment of a document in one target language: a question to the author, a choice explained, a
 * proposal discussed. A segment holds one target; its comments hold the rest of the conversation.
 */
#[ORM\Entity]
#[ORM\Table(name: self::TABLE)]
#[ORM\Index(name: 'phil_translation_comment_segment', columns: ['document', 'target_language', 'segment_key'])]
final class CommentRecord
{
    public const string TABLE = 'phil_translation_comment';

    /** The longest comment, in characters. */
    public const int MAX_BODY = 4000;

    #[ORM\Id]
    #[ORM\Column(name: 'id')]
    #[ORM\GeneratedValue]
    private ?int $id = null;

    private function __construct(
        #[ORM\Column(name: 'document', length: SegmentRecord::MAX_DOCUMENT)]
        private readonly string $document,
        #[ORM\Column(name: 'target_language', length: 35)]
        private readonly string $targetLanguage,
        #[ORM\Column(name: 'segment_key', length: Segment::MAX_KEY)]
        private readonly string $segmentKey,
        #[ORM\Column(name: 'author', length: SegmentRecord::MAX_PERSON, nullable: true)]
        private readonly ?string $author,
        #[ORM\Column(name: 'body', type: 'text')]
        private readonly string $body,
        #[ORM\Column(name: 'created_at')]
        private readonly DateTimeImmutable $createdAt,
    ) {
    }

    /**
     * @throws InvalidRecord when the document, the key or the author is out of bounds, or the body is empty, longer
     *                       than 4000 characters or not UTF-8
     */
    public static function of(string $document, LanguageTag $targetLanguage, string $key, ?string $author, string $body, DateTimeImmutable $now): self
    {
        $body = trim($body);

        if ('' === $document || \strlen($document) > SegmentRecord::MAX_DOCUMENT || '' === $key || \strlen($key) > Segment::MAX_KEY) {
            throw InvalidRecord::because('A comment belongs to a segment of a document, each referred to by 1 to 255 bytes.');
        }

        if (null !== $author && ('' === $author || \strlen($author) > SegmentRecord::MAX_PERSON)) {
            throw InvalidRecord::because(\sprintf('A person is referred to by 1 to %d bytes.', SegmentRecord::MAX_PERSON));
        }

        if ('' === $body || !mb_check_encoding($body, 'UTF-8') || mb_strlen($body) > self::MAX_BODY) {
            throw InvalidRecord::because(\sprintf('A comment is 1 to %d characters of UTF-8.', self::MAX_BODY));
        }

        return new self($document, $targetLanguage->value, $key, $author, $body, $now);
    }

    /**
     * The identifier the store gives the comment; `null` until it is kept.
     */
    public function id(): ?int
    {
        return $this->id;
    }

    /**
     * Gives the comment its identifier, for stores that do not number rows themselves.
     *
     * @internal
     */
    public function number(int $id): void
    {
        $this->id ??= $id;
    }

    public function document(): string
    {
        return $this->document;
    }

    public function targetLanguage(): LanguageTag
    {
        return LanguageTag::of($this->targetLanguage);
    }

    public function key(): string
    {
        return $this->segmentKey;
    }

    public function author(): ?string
    {
        return $this->author;
    }

    public function body(): string
    {
        return $this->body;
    }

    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }
}
