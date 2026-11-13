<?php

declare(strict_types=1);

namespace Phil\CommentsBundle\Infrastructure\Http;

use DateTimeImmutable;
use DateTimeInterface;
use Phil\CommentsBundle\Application\AuthorDirectory;
use Phil\CommentsBundle\Application\CommentPolicy;
use Phil\CommentsBundle\Application\Discussion;
use Phil\CommentsBundle\Domain\Model\Comment;
use Phil\Resource\ResourceReference;

/**
 * A thread and its comments as the endpoints answer them, for one viewer: its anchor, `null` for a thread on the whole
 * page, authors by reference and name, times in ISO 8601, and what the viewer may do with the thread and each comment.
 */
final readonly class DiscussionJson
{
    public function __construct(
        private CommentPolicy $policy,
        private ?AuthorDirectory $names = null,
    ) {
    }

    /**
     * @return array{id: string, subject: string, anchor: ?array<string, mixed>, status: string, detached: bool, suggestion: ?array{text: string, state: string}, author: array{ref: string, name: string}, createdAt: string, resolvedBy: ?array{ref: string, name: string}, resolvedAt: ?string, may: array{resolve: bool, decide: bool, reply: bool}, comments: list<array<string, mixed>>}
     */
    public function of(Discussion $discussion, ResourceReference $viewer): array
    {
        $thread = $discussion->thread;
        $resolvedBy = $thread->resolvedBy();
        $mayComment = $this->policy->mayComment($viewer, $thread->subject());

        return [
            'id' => $thread->id,
            'subject' => $thread->subject()->toString(),
            'anchor' => $thread->anchor()?->toArray(),
            'status' => $thread->status()->value,
            'detached' => $thread->isDetached(),
            'suggestion' => $thread->suggestion()?->toArray(),
            'author' => $this->author($thread->author()),
            'createdAt' => $this->time($thread->createdAt()),
            'resolvedBy' => $resolvedBy instanceof ResourceReference ? $this->author($resolvedBy) : null,
            'resolvedAt' => $this->time($thread->resolvedAt()),
            'may' => [
                'resolve' => $this->policy->mayResolve($viewer, $thread),
                'decide' => $this->policy->mayDecide($viewer, $thread),
                'reply' => $mayComment,
            ],
            'comments' => array_map(fn (Comment $comment): array => [
                'id' => $comment->id,
                'author' => $this->author($comment->author()),
                'body' => $comment->body(),
                'createdAt' => $this->time($comment->createdAt()),
                'editedAt' => $this->time($comment->editedAt()),
                'mine' => $comment->isBy($viewer),
                'may' => ($comment->isBy($viewer) && $mayComment) || $this->policy->mayModerate($viewer, $comment),
            ], $discussion->comments),
        ];
    }

    /**
     * @return array{ref: string, name: string}
     */
    private function author(ResourceReference $author): array
    {
        return ['ref' => $author->toString(), 'name' => $this->names?->name($author) ?? $author->toString()];
    }

    /**
     * @return ($moment is null ? null : string)
     */
    private function time(?DateTimeImmutable $moment): ?string
    {
        return $moment?->format(DateTimeInterface::ATOM);
    }
}
