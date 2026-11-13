<?php

declare(strict_types=1);

namespace Phil\CommentsBundle\Application;

use Closure;
use Phil\CommentsBundle\Application\Event\CommentDeleted;
use Phil\CommentsBundle\Application\Event\CommentWritten;
use Phil\CommentsBundle\Application\Exception\CommentRefused;
use Phil\CommentsBundle\Application\Exception\NotFound;
use Phil\CommentsBundle\Domain\Anchoring\Passage;
use Phil\CommentsBundle\Domain\Anchoring\Reanchoring;
use Phil\CommentsBundle\Domain\Exception\InvalidComment;
use Phil\CommentsBundle\Domain\Model\Comment;
use Phil\CommentsBundle\Domain\Model\Thread;
use Phil\CommentsBundle\Domain\Repository\CommentRepository;
use Phil\CommentsBundle\Domain\Repository\ThreadRepository;
use Phil\CommentsBundle\Domain\ValueObject\Anchor;
use Phil\Resource\ResourceReference;
use Phil\Ujo\Document;
use Psr\Clock\ClockInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Uid\Uuid;

/**
 * Threads of comments on the passages of a resource's text, and on the whole page beside them: opened on a passage,
 * with a suggested replacement or not, or on the page, answered, edited, deleted, resolved and reopened, the
 * suggestion accepted or rejected — each by an author the application's policy allows —, and moved with their
 * passages when the text changes. Each comment written, edited or deleted is told to the event dispatcher, when one
 * is given.
 */
final readonly class Comments
{
    /** @var Closure(): string */
    private Closure $ids;

    /**
     * @param ?Closure(): string $ids the identifiers of new threads and comments, UUID v7 by default
     */
    public function __construct(
        private ThreadRepository $threads,
        private CommentRepository $comments,
        private ClockInterface $clock,
        private CommentPolicy $policy = new DefaultCommentPolicy(),
        ?Closure $ids = null,
        private ?EventDispatcherInterface $events = null,
    ) {
        $this->ids = $ids ?? static fn (): string => Uuid::v7()->toRfc4122();
    }

    /**
     * The threads of a resource with their comments, oldest first.
     *
     * @return list<Discussion>
     *
     * @throws CommentRefused when the reader may not read them
     */
    public function discussions(ResourceReference $subject, ResourceReference $reader): array
    {
        if (!$this->policy->mayRead($reader, $subject)) {
            throw CommentRefused::because('read these comments');
        }

        $threads = $this->threads->ofSubject($subject);
        $comments = $this->comments->ofThreads(array_map(static fn (Thread $thread): string => $thread->id, $threads));

        return array_map(static fn (Thread $thread): Discussion => new Discussion(
            $thread,
            array_values(array_filter($comments, static fn (Comment $comment): bool => $comment->thread === $thread->id)),
        ), $threads);
    }

    /**
     * Opens a thread on a passage with its first comment, proposing a replacement when `$suggestion` is a text.
     *
     * @throws CommentRefused when the author may not comment on the resource
     * @throws InvalidComment when the body or the suggestion cannot be kept
     */
    public function open(ResourceReference $subject, Anchor $anchor, ResourceReference $author, string $body, ?string $suggestion = null): Discussion
    {
        return $this->start($subject, $anchor, $author, $body, $suggestion);
    }

    /**
     * Opens a thread on the whole page, beside those on its passages, with its first comment: the same policy, the
     * same replies, resolve and reopen, no passage to follow and no replacement to suggest.
     *
     * @throws CommentRefused when the author may not comment on the resource
     * @throws InvalidComment when the body cannot be kept
     */
    public function openOnPage(ResourceReference $subject, ResourceReference $author, string $body): Discussion
    {
        return $this->start($subject, null, $author, $body, null);
    }

    /**
     * @throws NotFound       when there is no such thread
     * @throws CommentRefused when the author may not comment on its resource
     * @throws InvalidComment when the body cannot be kept
     */
    public function reply(string $thread, ResourceReference $author, string $body): Discussion
    {
        $found = $this->thread($thread);

        if (!$this->policy->mayComment($author, $found->subject())) {
            throw CommentRefused::because('comment on this resource');
        }

        $comment = Comment::write(($this->ids)(), $found, $author, $body, $this->clock->now());
        $this->comments->save($comment);
        $this->events?->dispatch(new CommentWritten($comment, $found));

        return $this->discussion($found);
    }

    /**
     * Edits a comment, the author's own or one the policy lets them moderate.
     *
     * @throws NotFound       when there is no such comment
     * @throws CommentRefused when it is someone else's and the author may not moderate it
     * @throws InvalidComment when the body cannot be kept
     */
    public function edit(string $comment, ResourceReference $author, string $body): Discussion
    {
        [$found, $thread] = $this->mine($comment, $author, 'edit this comment');
        $found->edit($body, $this->clock->now());
        $this->comments->save($found);
        $this->events?->dispatch(new CommentWritten($found, $thread, true));

        return $this->discussion($thread);
    }

    /**
     * Deletes a comment, the author's own or one the policy lets them moderate; a thread left without comments is
     * deleted with it, and `null` returned.
     *
     * @throws NotFound       when there is no such comment
     * @throws CommentRefused when it is someone else's and the author may not moderate it
     */
    public function delete(string $comment, ResourceReference $author): ?Discussion
    {
        [$found, $thread] = $this->mine($comment, $author, 'delete this comment');
        $this->comments->remove($found);
        $discussion = $this->discussion($thread);
        $empty = [] === $discussion->comments;

        if ($empty) {
            $this->threads->remove($thread);
        }

        $this->events?->dispatch(new CommentDeleted($found->id, $thread, $empty));

        return $empty ? null : $discussion;
    }

    /**
     * @throws NotFound       when there is no such thread
     * @throws CommentRefused when the actor may not resolve it
     */
    public function resolve(string $thread, ResourceReference $actor): Discussion
    {
        return $this->change($thread, fn (Thread $found): bool => $this->policy->mayResolve($actor, $found), 'resolve this thread', fn (Thread $found) => $found->resolve($actor, $this->clock->now()));
    }

    /**
     * @throws NotFound       when there is no such thread
     * @throws CommentRefused when the actor may not reopen it
     */
    public function reopen(string $thread, ResourceReference $actor): Discussion
    {
        return $this->change($thread, fn (Thread $found): bool => $this->policy->mayResolve($actor, $found), 'reopen this thread', static fn (Thread $found) => $found->reopen());
    }

    /**
     * Records that the suggestion was accepted — the passage replaced by the application or the editor — and resolves
     * the thread.
     *
     * @throws NotFound       when there is no such thread
     * @throws CommentRefused when the actor may not decide on its suggestion
     * @throws InvalidComment when it has no suggestion waiting for a decision
     */
    public function accept(string $thread, ResourceReference $actor): Discussion
    {
        return $this->change($thread, fn (Thread $found): bool => $this->policy->mayDecide($actor, $found), 'decide on this suggestion', fn (Thread $found) => $found->accept($actor, $this->clock->now()));
    }

    /**
     * @throws NotFound       when there is no such thread
     * @throws CommentRefused when the actor may not decide on its suggestion
     * @throws InvalidComment when it has no suggestion waiting for a decision
     */
    public function reject(string $thread, ResourceReference $actor): Discussion
    {
        return $this->change($thread, fn (Thread $found): bool => $this->policy->mayDecide($actor, $found), 'decide on this suggestion', fn (Thread $found) => $found->reject($actor, $this->clock->now()));
    }

    /**
     * Replaces the passage of a thread by its suggestion in a UJO document, accepts the suggestion, and returns the
     * document changed, for the application to keep.
     *
     * @throws NotFound       when there is no such thread
     * @throws CommentRefused when the actor may not decide on its suggestion
     * @throws InvalidComment when it has no suggestion waiting for a decision, or its passage is not at its place
     */
    public function applySuggestion(string $thread, ResourceReference $actor, Document $document): Document
    {
        $found = $this->thread($thread);
        $suggestion = $found->suggestion();

        if (!$this->policy->mayDecide($actor, $found)) {
            throw CommentRefused::because('decide on this suggestion');
        }

        if (true !== $suggestion?->isPending()) {
            throw InvalidComment::because('The thread has no suggestion waiting for a decision.');
        }

        $anchor = $found->anchor();

        if (!$anchor instanceof Anchor) {
            throw InvalidComment::because('A thread on the whole page has no passage to replace.');
        }

        $changed = UjoPassages::apply($document, $anchor, $suggestion->text);
        $found->accept($actor, $this->clock->now());
        $this->threads->save($found);

        return $changed;
    }

    /**
     * Moves each thread of a UJO document to where its passage is now, or detaches it.
     *
     * @return array{updated: int, detached: int}
     */
    public function reanchorDocument(ResourceReference $subject, Document $document): array
    {
        return $this->reanchor($subject, UjoPassages::of($document));
    }

    /**
     * Moves each thread of a plain text to where its passage is now, or detaches it.
     *
     * @return array{updated: int, detached: int}
     */
    public function reanchorText(ResourceReference $subject, string $text): array
    {
        return $this->reanchor($subject, [new Passage(null, null, $text)]);
    }

    /**
     * Moves each thread of a resource to where its passage is now in these texts, in reading order — attaching again
     * a detached thread whose passage has come back —, or detaches it, orphaned, when the passage is in none. A thread
     * whose place or context has changed is updated and saved; the others, and those on the whole page, are left as
     * they are.
     *
     * @param list<Passage> $passages
     *
     * @return array{updated: int, detached: int}
     */
    public function reanchor(ResourceReference $subject, array $passages): array
    {
        $updated = 0;
        $detached = 0;

        foreach ($this->threads->ofSubject($subject) as $thread) {
            $old = $thread->anchor();

            if (!$old instanceof Anchor) {
                continue;
            }

            $anchor = Reanchoring::locate($old, $passages);

            if (!$anchor instanceof Anchor) {
                if ($thread->isDetached()) {
                    continue;
                }

                $thread->detach();
                ++$detached;
            } elseif ($thread->isDetached() || $anchor->toArray() !== $old->toArray()) {
                $thread->moveTo($anchor);
                ++$updated;
            } else {
                continue;
            }

            $this->threads->save($thread);
        }

        return ['updated' => $updated, 'detached' => $detached];
    }

    /**
     * @throws NotFound when there is no such thread
     */
    public function thread(string $id): Thread
    {
        return $this->threads->get($id) ?? throw NotFound::thread($id);
    }

    /**
     * @throws CommentRefused when the author may not comment on the resource
     * @throws InvalidComment when the body or the suggestion cannot be kept
     */
    private function start(ResourceReference $subject, ?Anchor $anchor, ResourceReference $author, string $body, ?string $suggestion): Discussion
    {
        if (!$this->policy->mayComment($author, $subject)) {
            throw CommentRefused::because('comment on this resource');
        }

        $now = $this->clock->now();
        $thread = Thread::open(($this->ids)(), $subject, $anchor, $author, $now, $suggestion);
        $comment = Comment::write(($this->ids)(), $thread, $author, $body, $now);
        $this->threads->save($thread);
        $this->comments->save($comment);
        $this->events?->dispatch(new CommentWritten($comment, $thread));

        return new Discussion($thread, [$comment]);
    }

    private function discussion(Thread $thread): Discussion
    {
        return new Discussion($thread, $this->comments->ofThreads([$thread->id]));
    }

    /**
     * @param Closure(Thread): bool $allowed
     * @param Closure(Thread): void $change
     */
    private function change(string $thread, Closure $allowed, string $action, Closure $change): Discussion
    {
        $found = $this->thread($thread);

        if (!$allowed($found)) {
            throw CommentRefused::because($action);
        }

        $change($found);
        $this->threads->save($found);

        return $this->discussion($found);
    }

    /**
     * A comment and its thread, when the author wrote it and may still comment on its resource, or may moderate it.
     *
     * @return array{Comment, Thread}
     */
    private function mine(string $comment, ResourceReference $author, string $action): array
    {
        $found = $this->comments->get($comment) ?? throw NotFound::comment($comment);
        $thread = $this->thread($found->thread);

        if ((!$found->isBy($author) || !$this->policy->mayComment($author, $thread->subject())) && !$this->policy->mayModerate($author, $found)) {
            throw CommentRefused::because($action);
        }

        return [$found, $thread];
    }
}
