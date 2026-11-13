<?php

declare(strict_types=1);

namespace Phil\CommentsBundle\Tests\Unit\Fixture;

use Override;
use Phil\CommentsBundle\Domain\Model\Comment;
use Phil\CommentsBundle\Domain\Repository\CommentRepository;
use Phil\CommentsBundle\Infrastructure\Memory\InMemoryCommentRepository;

/**
 * Comments in memory, with the identifiers of those saved.
 */
final class RecordingComments implements CommentRepository
{
    /** @var list<string> */
    public array $saved = [];

    private readonly InMemoryCommentRepository $comments;

    public function __construct()
    {
        $this->comments = new InMemoryCommentRepository();
    }

    #[Override]
    public function get(string $id): ?Comment
    {
        return $this->comments->get($id);
    }

    #[Override]
    public function ofThreads(array $threads): array
    {
        return $this->comments->ofThreads($threads);
    }

    #[Override]
    public function save(Comment $comment): void
    {
        $this->saved[] = $comment->id;
        $this->comments->save($comment);
    }

    #[Override]
    public function remove(Comment $comment): void
    {
        $this->comments->remove($comment);
    }
}
