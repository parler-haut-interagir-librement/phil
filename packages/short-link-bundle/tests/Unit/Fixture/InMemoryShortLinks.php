<?php

declare(strict_types=1);

namespace Phil\ShortLinkBundle\Tests\Unit\Fixture;

use DateTimeImmutable;
use Override;
use Phil\ShortLinkBundle\Domain\Contract\ShortLinkRepository;
use Phil\ShortLinkBundle\Domain\Enum\LinkState;
use Phil\ShortLinkBundle\Domain\Enum\VisitorClass;
use Phil\ShortLinkBundle\Domain\Model\ShortLink;
use ReflectionProperty;

/**
 * The links in memory, numbered as they are saved; a visit is counted as the database counts it, when the link is in
 * force at that moment.
 */
final class InMemoryShortLinks implements ShortLinkRepository
{
    /** @var array<int, ShortLink> */
    public array $links = [];

    /** @var list<string> the codes looked up, in order */
    public array $lookups = [];

    /** Whether the next visit is lost to a racing request. */
    public bool $loseNextRace = false;

    /** @var int the visits asked to be counted, whatever the answer */
    public int $visitAttempts = 0;

    /** @var int the links saved, a link saved twice counted twice */
    public int $saves = 0;

    private int $next = 1;

    #[Override]
    public function find(int $id): ?ShortLink
    {
        return $this->links[$id] ?? null;
    }

    #[Override]
    public function findByCode(string $code): ?ShortLink
    {
        $this->lookups[] = $code;

        foreach ($this->links as $link) {
            if ($link->code() === $code) {
                return $link;
            }
        }

        return null;
    }

    #[Override]
    public function isTaken(string $code): bool
    {
        return $this->findByCode($code) instanceof ShortLink;
    }

    #[Override]
    public function save(ShortLink $link): void
    {
        ++$this->saves;

        if (null === $link->id()) {
            new ReflectionProperty(ShortLink::class, 'id')->setValue($link, $this->next++);
        }

        $this->links[(int) $link->id()] = $link;
    }

    #[Override]
    public function remove(int $id): bool
    {
        if (!isset($this->links[$id])) {
            return false;
        }

        unset($this->links[$id]);

        return true;
    }

    #[Override]
    public function recordVisit(int $id, DateTimeImmutable $now, VisitorClass $visitor = VisitorClass::Human): bool
    {
        ++$this->visitAttempts;
        $link = $this->links[$id] ?? null;

        if ($this->loseNextRace || !$link instanceof ShortLink || LinkState::Active !== $link->stateAt($now)) {
            $this->loseNextRace = false;

            return false;
        }

        if (!$visitor->isHuman()) {
            new ReflectionProperty(ShortLink::class, 'robotVisits')->setValue($link, $link->robotVisits() + 1);

            return true;
        }

        new ReflectionProperty(ShortLink::class, 'visits')->setValue($link, $link->visits() + 1);
        new ReflectionProperty(ShortLink::class, 'lastVisitAt')->setValue($link, $now);

        return true;
    }

    #[Override]
    public function page(string $search, int $offset, int $limit): array
    {
        return \array_slice(array_reverse($this->matching($search)), $offset, $limit);
    }

    #[Override]
    public function count(string $search): int
    {
        return \count($this->matching($search));
    }

    /**
     * @return list<ShortLink>
     */
    private function matching(string $search): array
    {
        return array_values(array_filter($this->links, static fn (ShortLink $link): bool => '' === $search || str_contains($link->code().' '.$link->label().' '.$link->target(), $search)));
    }
}
