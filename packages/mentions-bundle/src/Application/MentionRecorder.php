<?php

declare(strict_types=1);

namespace Phil\MentionsBundle\Application;

use Closure;
use Phil\MentionsBundle\Application\Contract\MemberDirectory;
use Phil\MentionsBundle\Application\Event\MemberMentioned;
use Phil\MentionsBundle\Domain\Contract\MentionRepository;
use Phil\MentionsBundle\Domain\Exception\InvalidMention;
use Phil\MentionsBundle\Domain\Exception\MentionsRefused;
use Phil\MentionsBundle\Domain\Model\RecordedMention;
use Phil\MentionsBundle\Domain\ValueObject\Mention;
use Phil\Resource\ResourceReference;
use Psr\Clock\ClockInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Uid\Uuid;

/**
 * Keeps the members a subject mentions in step with its text. Each time the text is saved, its mentions are resolved
 * through the directory; the author and the members who may not see the content are left out; the members not stored
 * yet are added and announced with `MemberMentioned`, and the members the text no longer mentions are removed. Saving
 * the same text again changes nothing and announces nothing; a member that another request saving the same subject at
 * once has just added is announced by that request only.
 */
final readonly class MentionRecorder
{
    /** @var Closure(): string */
    private Closure $ids;

    /**
     * @param ?Closure(): string $ids the identifiers of new mentions, UUID v7 by default
     */
    public function __construct(
        private MentionRepository $repository,
        private MemberDirectory $directory,
        private ClockInterface $clock,
        private MentionLimits $limits = new MentionLimits(),
        private ?EventDispatcherInterface $events = null,
        ?Closure $ids = null,
    ) {
        $this->ids = $ids ?? static fn (): string => Uuid::v7()->toRfc4122();
    }

    /**
     * Records the mentions of a subject's text, as found by `TextMentions` or `UjoMentions`.
     *
     * @param list<Mention>      $mentions
     * @param ?ResourceReference $context  the content whose visibility counts, the subject itself when null
     *
     * @throws MentionsRefused when the text mentions more members than the limits allow
     */
    public function record(ResourceReference $subject, ResourceReference $author, array $mentions, ?ResourceReference $context = null): MentionChanges
    {
        $unique = [];

        foreach ($mentions as $mention) {
            $unique[$mention->key()] ??= $mention;
        }

        $this->limits->checkCount(\count($unique));
        $context ??= $subject;
        $wanted = [];

        foreach ($this->resolve($unique) as $key => $member) {
            if (!$member->reference->equals($author) && $this->directory->maySee($member->reference, $context)) {
                $wanted[$key] = $member;
            }
        }

        $stored = [];

        foreach ($this->repository->ofSubject($subject) as $recorded) {
            $stored[$recorded->member()->toString()] = $recorded;
        }

        $added = [];

        foreach ($wanted as $key => $member) {
            if (!isset($stored[$key])) {
                $added[] = $member;
            }
        }

        $removed = array_values(array_diff_key($stored, $wanted));

        if ([] === $added && [] === $removed) {
            return new MentionChanges();
        }

        $now = $this->clock->now();
        $inserted = [];

        foreach ($this->repository->change(
            array_map(fn (Member $member): RecordedMention => RecordedMention::record(($this->ids)(), $subject, $member->reference, $author, $now), $added),
            $removed,
        ) as $recorded) {
            $inserted[$recorded->member()->toString()] = $recorded;
        }

        // A member another request recorded meanwhile is neither reported nor announced twice.
        $added = array_values(array_filter($added, static fn (Member $member): bool => isset($inserted[$member->reference->toString()])));

        foreach ($added as $member) {
            $this->events?->dispatch(new MemberMentioned($subject, $member->reference, $author, $context, $member->recipient));
        }

        return new MentionChanges($added, array_map(static fn (RecordedMention $recorded): ResourceReference => $recorded->member(), $removed));
    }

    /**
     * The members a subject mentions, in the order they were first mentioned.
     *
     * @return list<ResourceReference>
     */
    public function mentionedIn(ResourceReference $subject): array
    {
        return array_map(static fn (RecordedMention $recorded): ResourceReference => $recorded->member(), $this->repository->ofSubject($subject));
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
        if ($limit < 1 || $limit > 500) {
            throw InvalidMention::because('The mentions of a member are read 1 to 500 at a time.');
        }

        return $this->repository->ofMember($member, $limit);
    }

    /**
     * Forgets the mentions of a subject, as it is deleted; nobody is notified.
     */
    public function forget(ResourceReference $subject): void
    {
        $this->repository->forget($subject);
    }

    /**
     * The members of the mentions, by reference, in the order of their first mention; one call to the directory for
     * the handles, one for the references.
     *
     * @param array<string, Mention> $mentions
     *
     * @return array<string, Member>
     */
    private function resolve(array $mentions): array
    {
        $handles = [];
        $references = [];

        foreach ($mentions as $mention) {
            if ($mention->isMark()) {
                $references[] = $mention->member;
            } else {
                $handles[] = $mention->handle;
            }
        }

        $byKey = [];

        foreach ([] === $handles ? [] : $this->directory->resolve($handles) as $member) {
            $byKey['handle:'.mb_strtolower($member->handle)] = $member;
        }

        foreach ([] === $references ? [] : $this->directory->members($references) as $member) {
            $byKey['member:'.$member->reference->toString()] = $member;
        }

        $members = [];

        foreach ($mentions as $mention) {
            $member = $byKey[$mention->key()] ?? null;

            if ($member instanceof Member) {
                $members[$member->reference->toString()] = $member;
            }
        }

        return $members;
    }
}
