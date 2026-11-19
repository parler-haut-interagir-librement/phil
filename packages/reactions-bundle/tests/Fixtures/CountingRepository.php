<?php

declare(strict_types=1);

namespace Phil\ReactionsBundle\Tests\Fixtures;

use Override;
use Phil\ReactionsBundle\Domain\Model\Reaction;
use Phil\ReactionsBundle\Domain\Repository\ReactionRepository;
use Phil\Resource\ResourceReference;

/**
 * A store that says which batches it was asked about.
 */
final class CountingRepository implements ReactionRepository
{
    /** @var list<list<string>> */
    public array $counted = [];

    public int $saved = 0;

    /** @var list<array{string, list<string>}> */
    public array $chosenAsked = [];

    /** @var list<array{list<string>, int}> */
    public array $reactorsAsked = [];

    public function __construct(
        private readonly ReactionRepository $inner,
    ) {
    }

    #[Override]
    public function of(ResourceReference $content, string $member): array
    {
        return $this->inner->of($content, $member);
    }

    #[Override]
    public function add(Reaction $reaction): void
    {
        $this->inner->add($reaction);
    }

    #[Override]
    public function save(Reaction $reaction): void
    {
        ++$this->saved;
        $this->inner->save($reaction);
    }

    #[Override]
    public function remove(Reaction $reaction): void
    {
        $this->inner->remove($reaction);
    }

    #[Override]
    public function counts(array $contents): array
    {
        $this->counted[] = $contents;

        return $this->inner->counts($contents);
    }

    #[Override]
    public function chosenBy(string $member, array $contents): array
    {
        $this->chosenAsked[] = [$member, $contents];

        return $this->inner->chosenBy($member, $contents);
    }

    #[Override]
    public function reactors(array $contents, int $perCode): array
    {
        $this->reactorsAsked[] = [$contents, $perCode];

        return $this->inner->reactors($contents, $perCode);
    }

    #[Override]
    public function ofMember(string $member): array
    {
        return $this->inner->ofMember($member);
    }

    #[Override]
    public function eraseMember(string $member): void
    {
        $this->inner->eraseMember($member);
    }

    #[Override]
    public function eraseContent(ResourceReference $content): void
    {
        $this->inner->eraseContent($content);
    }
}
