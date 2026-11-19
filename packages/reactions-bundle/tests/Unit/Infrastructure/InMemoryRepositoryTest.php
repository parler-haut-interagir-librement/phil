<?php

declare(strict_types=1);

namespace Phil\ReactionsBundle\Tests\Unit\Infrastructure;

use DateTimeImmutable;
use Override;
use Phil\ReactionsBundle\Domain\Exception\AlreadyReacted;
use Phil\ReactionsBundle\Domain\Model\Reaction;
use Phil\ReactionsBundle\Domain\Repository\ReactionRepository;
use Phil\ReactionsBundle\Infrastructure\Memory\InMemoryReactionRepository;
use Phil\Resource\ResourceReference;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(InMemoryReactionRepository::class)]
#[CoversClass(AlreadyReacted::class)]
final class InMemoryRepositoryTest extends RepositoryTestCase
{
    /**
     * The store is emptied between two requests: what a member wrote reaches neither the next request nor another member.
     */
    public function testAResetEmptiesTheStore(): void
    {
        $store = new InMemoryReactionRepository();
        $content = ResourceReference::of('article', '1');
        $store->add(Reaction::of('r1', $content, 'ada', 'like', new DateTimeImmutable()));
        $store->add(Reaction::of('r2', $content, 'linus', 'love', new DateTimeImmutable()));

        $store->reset();

        self::assertSame([], $store->ofMember('ada'));
        self::assertSame([], $store->counts(['article:1']));
        $store->add(Reaction::of('r1', $content, 'ada', 'like', new DateTimeImmutable()));
        self::assertCount(1, $store->of($content, 'ada'));
    }

    #[Override]
    protected function store(): ReactionRepository
    {
        return new InMemoryReactionRepository();
    }
}
