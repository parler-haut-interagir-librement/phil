<?php

declare(strict_types=1);

namespace Phil\MentionsBundle\Infrastructure\Comments;

use Phil\CommentsBundle\Application\Event\CommentDeleted;
use Phil\CommentsBundle\Application\Event\CommentWritten;
use Phil\MentionsBundle\Domain\Exception\MentionsRefused;

/**
 * The bridge to `phil/comments-bundle`, registered only when both bundles are installed: each comment written or
 * edited has its mentions recorded — the members it mentions for the first time notified —, and a deleted comment has
 * its mentions forgotten. The comment is saved before it is read, so a comment that mentions more members than the
 * limit allows stays as written and notifies nobody.
 */
final readonly class RecordCommentMentions
{
    public function __construct(
        private CommentMentions $mentions,
    ) {
    }

    public function written(CommentWritten $event): void
    {
        try {
            $this->mentions->comment($event->comment, $event->thread);
        } catch (MentionsRefused) {
            $this->mentions->forget($event->comment->id);
        }
    }

    public function deleted(CommentDeleted $event): void
    {
        $this->mentions->forget($event->comment);
    }
}
