<?php

declare(strict_types=1);

namespace Phil\MentionsBundle\Tests\Unit\Fixture;

use Closure;
use Override;
use Phil\MentionsBundle\Domain\Contract\MentionRepository;
use Phil\Resource\ResourceReference;

/**
 * A store that counts the changes it is asked for, and hands everything to another, after what another request
 * writes meanwhile.
 */
final class CountingRepository implements MentionRepository
{
    public int $changes = 0;

    /** @var ?Closure(MentionRepository): void what another request writes just before the change */
    public ?Closure $meanwhile = null;

    public function __construct(
        private readonly MentionRepository $inner,
    ) {
    }

    #[Override]
    public function ofSubject(ResourceReference $subject): array
    {
        return $this->inner->ofSubject($subject);
    }

    #[Override]
    public function ofMember(ResourceReference $member, int $limit): array
    {
        return $this->inner->ofMember($member, $limit);
    }

    #[Override]
    public function change(array $added, array $removed): array
    {
        ++$this->changes;

        if ($this->meanwhile instanceof Closure) {
            ($this->meanwhile)($this->inner);
        }

        return $this->inner->change($added, $removed);
    }

    #[Override]
    public function forget(ResourceReference $subject): void
    {
        $this->inner->forget($subject);
    }
}
