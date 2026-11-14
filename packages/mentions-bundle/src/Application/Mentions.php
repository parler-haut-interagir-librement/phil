<?php

declare(strict_types=1);

namespace Phil\MentionsBundle\Application;

use Phil\MentionsBundle\Application\Extraction\TextMentions;
use Phil\MentionsBundle\Application\Extraction\UjoMentions;
use Phil\MentionsBundle\Domain\Exception\InvalidMention;
use Phil\MentionsBundle\Domain\Exception\MentionsRefused;
use Phil\MentionsBundle\Domain\Model\RecordedMention;
use Phil\Resource\ResourceReference;
use Phil\Ujo\Document;

/**
 * What the application calls when it saves a text that may mention members — a comment, a proposal, a page —, and when
 * it deletes it:
 *
 * ```php
 * $mentions->inText(ResourceReference::of('comment', $comment->id), $author, $comment->body, markdown: true, context: $page);
 * $mentions->inDocument(ResourceReference::of('page', $page->id), $author, $document);
 * $mentions->forget(ResourceReference::of('comment', $comment->id));
 * ```
 */
final readonly class Mentions
{
    public function __construct(
        private MentionRecorder $recorder,
        private TextMentions $text = new TextMentions(),
        private MentionLimits $limits = new MentionLimits(),
    ) {
    }

    /**
     * Records the handles of a plain text or of Markdown.
     *
     * @param ?ResourceReference $context the content whose visibility counts, the subject itself when null
     *
     * @throws MentionsRefused when the text is too long or mentions too many members
     */
    public function inText(ResourceReference $subject, ResourceReference $author, string $text, bool $markdown = false, ?ResourceReference $context = null): MentionChanges
    {
        $this->limits->checkLength($text);

        return $this->recorder->record($subject, $author, $this->text->find($text, $markdown), $context);
    }

    /**
     * Records the mention marks of a UJO (Universal JSON Output) document, decoded or normalized.
     *
     * @param ?ResourceReference $context the content whose visibility counts, the subject itself when null
     *
     * @throws MentionsRefused when the document mentions too many members
     */
    public function inDocument(ResourceReference $subject, ResourceReference $author, Document $document, ?ResourceReference $context = null): MentionChanges
    {
        return $this->recorder->record($subject, $author, UjoMentions::find($document), $context);
    }

    /**
     * The members a subject mentions, in the order they were first mentioned.
     *
     * @return list<ResourceReference>
     */
    public function mentionedIn(ResourceReference $subject): array
    {
        return $this->recorder->mentionedIn($subject);
    }

    /**
     * The mentions of a member, the latest first, at most `$limit` — from 1 to 500.
     *
     * @return list<RecordedMention>
     *
     * @throws InvalidMention when the limit is out of its range
     */
    public function mentioning(ResourceReference $member, int $limit): array
    {
        return $this->recorder->mentioning($member, $limit);
    }

    /**
     * Forgets the mentions of a deleted subject.
     */
    public function forget(ResourceReference $subject): void
    {
        $this->recorder->forget($subject);
    }
}
