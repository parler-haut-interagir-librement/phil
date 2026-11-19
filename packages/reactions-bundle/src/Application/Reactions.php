<?php

declare(strict_types=1);

namespace Phil\ReactionsBundle\Application;

use DateTimeImmutable;
use Phil\ReactionsBundle\Application\Contract\ReactionAccess;
use Phil\ReactionsBundle\Application\Contract\ReactionThrottle;
use Phil\ReactionsBundle\Application\Event\ReactionAdded;
use Phil\ReactionsBundle\Application\Event\ReactionRemoved;
use Phil\ReactionsBundle\Application\Exception\ReactionLimitReached;
use Phil\ReactionsBundle\Application\Exception\ReactionRefused;
use Phil\ReactionsBundle\Application\Exception\TooManyReactions;
use Phil\ReactionsBundle\Application\Exception\UnknownReaction;
use Phil\ReactionsBundle\Domain\Exception\AlreadyReacted;
use Phil\ReactionsBundle\Domain\Model\Reaction;
use Phil\ReactionsBundle\Domain\Repository\ReactionRepository;
use Phil\Resource\ResourceReference;
use Psr\Clock\ClockInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Uid\Uuid;

/**
 * The reactions of members. Both changes say the state they want rather than toggling, so a double click or a form sent
 * twice changes nothing more.
 *
 * With one reaction per member, a new one replaces the one they had. With several, each code is given at most once and
 * no more than the cap; when two requests race past the cap, or past the single reaction, the oldest reactions are kept
 * and the newer ones removed, so both requests leave the same rows.
 */
final readonly class Reactions
{
    /** The attribute the voters of the application answer, about a {@see ReactionSubject}. */
    public const string REACT = 'phil_reactions.react';

    public function __construct(
        private ReactionRepository $repository,
        private ReactionCatalog $catalog,
        private ReactionAccess $access,
        private ReactionThrottle $throttle,
        private ClockInterface $clock,
        private ?EventDispatcherInterface $events = null,
        private ReactionsPerMember $perMember = new ReactionsPerMember(),
    ) {
    }

    /**
     * Gives this reaction to the content: with one reaction per member, it replaces the one they had.
     *
     * @return bool whether anything changed
     *
     * @throws UnknownReaction      when the site does not offer the code
     * @throws ReactionRefused      when the access refuses
     * @throws TooManyReactions     when the member has to wait
     * @throws ReactionLimitReached when the member already gave as many reactions as the site allows
     */
    public function react(ResourceReference $content, string $member, string $code): bool
    {
        $definition = $this->catalog->definition($code) ?? throw UnknownReaction::code($code);

        if (!$this->access->canReact(new ReactionSubject($content, $member, $definition))) {
            throw ReactionRefused::byAccess($code);
        }

        $this->throttle->consume($member);
        $now = $this->clock->now();
        $given = $this->repository->of($content, $member);

        foreach ($given as $reaction) {
            if ($code === $reaction->code()) {
                return false;
            }
        }

        if ($this->perMember->replaces() && [] !== $given) {
            return $this->replace($content, $given[0], $code, $now);
        }

        if (!$this->perMember->admitsAnother(\count($given))) {
            throw ReactionLimitReached::of($this->perMember->cap);
        }

        $reaction = Reaction::of(Uuid::v7()->toRfc4122(), $content, $member, $code, $now);

        try {
            $this->repository->add($reaction);
        } catch (AlreadyReacted) {
            return false;
        }

        if (!$this->keptWithin($content, $member, $reaction)) {
            return false;
        }

        $this->events?->dispatch(new ReactionAdded($content->toString(), $member, $code, $now));

        return true;
    }

    /**
     * Takes back the member's reaction with this code to the content, or every reaction of theirs to it when no code is
     * given.
     *
     * @return bool whether there was one
     *
     * @throws TooManyReactions when the member has to wait
     */
    public function withdraw(ResourceReference $content, string $member, ?string $code = null): bool
    {
        $this->throttle->consume($member);
        $withdrawn = false;

        foreach ($this->repository->of($content, $member) as $reaction) {
            if (null !== $code && $code !== $reaction->code()) {
                continue;
            }

            $this->repository->remove($reaction);
            $this->events?->dispatch(new ReactionRemoved($content->toString(), $member, $reaction->code(), $this->clock->now()));
            $withdrawn = true;
        }

        return $withdrawn;
    }

    /**
     * Removes every reaction of a content that no longer exists.
     */
    public function eraseContent(ResourceReference $content): void
    {
        $this->repository->eraseContent($content);
    }

    private function replace(ResourceReference $content, Reaction $reaction, string $code, DateTimeImmutable $now): bool
    {
        $previous = $reaction->code();
        $reaction->changeTo($code, $now);
        $this->repository->save($reaction);
        $this->events?->dispatch(new ReactionRemoved($content->toString(), $reaction->member, $previous, $now));
        $this->events?->dispatch(new ReactionAdded($content->toString(), $reaction->member, $code, $now));

        return true;
    }

    /**
     * Removes what a race left beyond the cap, the newest first, and says whether the reaction just added is kept.
     */
    private function keptWithin(ResourceReference $content, string $member, Reaction $added): bool
    {
        $given = $this->repository->of($content, $member);
        $kept = true;

        foreach (\array_slice($given, $this->perMember->cap) as $extra) {
            $this->repository->remove($extra);
            $kept = $kept && $extra->id !== $added->id;
        }

        return $kept;
    }
}
