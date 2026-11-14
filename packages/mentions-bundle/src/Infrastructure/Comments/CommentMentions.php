<?php

declare(strict_types=1);

namespace Phil\MentionsBundle\Infrastructure\Comments;

use Phil\CommentsBundle\Application\Discussion;
use Phil\CommentsBundle\Domain\Model\Comment;
use Phil\CommentsBundle\Domain\Model\Thread;
use Phil\MentionsBundle\Application\MentionChanges;
use Phil\MentionsBundle\Application\Mentions;
use Phil\MentionsBundle\Domain\Exception\MentionsRefused;
use Phil\Resource\ResourceReference;

/**
 * The mentions of `phil/comments-bundle` comments: each comment is a subject `comment:<id>`, written by its author,
 * whose visibility is that of the resource its thread is on. Recording a whole discussion after each change is safe:
 * only the members a comment mentions for the first time are notified.
 *
 * ```php
 * $discussion = $comments->reply($thread, $author, $body);
 * $commentMentions->discussion($discussion);
 * ```
 */
final readonly class CommentMentions
{
    public const string KIND = 'comment';

    public function __construct(
        private Mentions $mentions,
        private bool $markdown = false,
    ) {
    }

    /**
     * @throws MentionsRefused when a comment mentions too many members
     */
    public function discussion(Discussion $discussion): void
    {
        foreach ($discussion->comments as $comment) {
            $this->comment($comment, $discussion->thread);
        }
    }

    /**
     * @throws MentionsRefused when the comment mentions too many members
     */
    public function comment(Comment $comment, Thread $thread): MentionChanges
    {
        return $this->mentions->inText(self::subject($comment->id), $comment->author(), $comment->body(), $this->markdown, $thread->subject());
    }

    /**
     * Forgets the mentions of a deleted comment.
     */
    public function forget(string $comment): void
    {
        $this->mentions->forget(self::subject($comment));
    }

    public static function subject(string $comment): ResourceReference
    {
        return ResourceReference::of(self::KIND, $comment);
    }
}
