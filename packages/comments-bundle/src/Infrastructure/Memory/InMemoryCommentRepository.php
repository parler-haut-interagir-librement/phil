<?php

declare(strict_types=1);

namespace Phil\CommentsBundle\Infrastructure\Memory;

use Override;
use Phil\CommentsBundle\Domain\Model\Comment;
use Phil\CommentsBundle\Domain\Repository\CommentRepository;
use Symfony\Contracts\Service\ResetInterface;

/**
 * The comments in memory, for tests and examples. The bundle tags the store `kernel.reset`: it is emptied between two
 * requests, under PHP-FPM as in a worker, so that nothing a request writes reaches the next one, nor another member. A
 * test that reads what an earlier request wrote uses the Doctrine store on SQLite.
 */
final class InMemoryCommentRepository implements CommentRepository, ResetInterface
{
    /** @var array<string, Comment> */
    private array $comments = [];

    #[Override]
    public function get(string $id): ?Comment
    {
        return $this->comments[$id] ?? null;
    }

    #[Override]
    public function ofThreads(array $threads): array
    {
        $found = array_filter($this->comments, static fn (Comment $comment): bool => \in_array($comment->thread, $threads, true));
        usort($found, static function (Comment $a, Comment $b): int {
            $byTime = $a->createdAt()->getTimestamp() <=> $b->createdAt()->getTimestamp();

            return 0 === $byTime ? strcmp($a->id, $b->id) : $byTime;
        });

        return $found;
    }

    #[Override]
    public function save(Comment $comment): void
    {
        $this->comments[$comment->id] = $comment;
    }

    #[Override]
    public function remove(Comment $comment): void
    {
        unset($this->comments[$comment->id]);
    }

    #[Override]
    public function reset(): void
    {
        $this->comments = [];
    }
}
