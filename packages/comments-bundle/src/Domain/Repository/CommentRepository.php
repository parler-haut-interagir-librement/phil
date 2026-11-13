<?php

declare(strict_types=1);

namespace Phil\CommentsBundle\Domain\Repository;

use Phil\CommentsBundle\Domain\Model\Comment;

/**
 * Where the comments of threads are kept.
 */
interface CommentRepository
{
    public function get(string $id): ?Comment;

    /**
     * The comments of these threads, oldest first.
     *
     * @param list<string> $threads
     *
     * @return list<Comment>
     */
    public function ofThreads(array $threads): array;

    /**
     * Keeps a new comment, or the changes made to one.
     */
    public function save(Comment $comment): void;

    public function remove(Comment $comment): void;
}
