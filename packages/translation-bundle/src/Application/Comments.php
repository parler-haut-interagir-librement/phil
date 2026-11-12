<?php

declare(strict_types=1);

namespace Phil\TranslationBundle\Application;

use DateTimeImmutable;
use Phil\TranslationBundle\Application\Contract\Translators;
use Phil\TranslationBundle\Application\Exception\NotFound;
use Phil\TranslationBundle\Domain\Exception\InvalidRecord;
use Phil\TranslationBundle\Domain\Model\CommentRecord;
use Phil\TranslationBundle\Domain\Repository\Contract\CommentRepository;
use Phil\TranslationMemory\LanguageTag;
use Psr\Clock\ClockInterface;

/**
 * The comments on segments: a segment holds one target, and the conversation about it — questions, choices, other
 * proposals — goes into its comments, signed by the current user.
 */
final readonly class Comments
{
    public function __construct(
        private CommentRepository $comments,
        private ClockInterface $clock,
        private ?Translators $translators = null,
    ) {
    }

    /**
     * @throws InvalidRecord when the body is empty or too long, or the segment out of bounds
     */
    public function add(string $document, LanguageTag $targetLanguage, string $key, string $body): CommentRecord
    {
        $record = CommentRecord::of($document, $targetLanguage, $key, $this->translators?->current(), $body, new DateTimeImmutable('@'.$this->clock->now()->getTimestamp()));
        $this->comments->save($record);

        return $record;
    }

    /**
     * The comments on the segments of a document, by segment key, oldest first.
     *
     * @return array<string, list<CommentRecord>>
     */
    public function forDocument(string $document, LanguageTag $targetLanguage): array
    {
        $bySegment = [];

        foreach ($this->comments->forDocument($document, $targetLanguage) as $record) {
            $bySegment[$record->key()][] = $record;
        }

        return $bySegment;
    }

    /**
     * Removes a comment of a document in a target language.
     *
     * @throws NotFound when the document has no such comment in that language
     */
    public function remove(string $document, LanguageTag $targetLanguage, int $id): void
    {
        $record = $this->comments->get($id);

        if (!$record instanceof CommentRecord || $record->document() !== $document || !$record->targetLanguage()->equals($targetLanguage)) {
            throw NotFound::comment($id);
        }

        $this->comments->remove($record);
    }
}
