<?php

declare(strict_types=1);

namespace Phil\MentionsBundle\Tests\Unit\Infrastructure;

use DateTimeImmutable;
use Override;
use Phil\MentionsBundle\Domain\Contract\MentionRepository;
use Phil\MentionsBundle\Domain\Model\RecordedMention;
use Phil\MentionsBundle\Infrastructure\Memory\InMemoryMentionRepository;
use Phil\Resource\ResourceReference;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(InMemoryMentionRepository::class)]
final class InMemoryRepositoryTest extends RepositoryTestCase
{
    public function testTheStoreIsEmptiedBetweenRequests(): void
    {
        $store = new InMemoryMentionRepository();
        $store->change([RecordedMention::record('a', ResourceReference::of('comment', '1'), ResourceReference::of('user', '1'), ResourceReference::of('user', '9'), new DateTimeImmutable())], []);

        $store->reset();

        self::assertSame([], $store->ofSubject(ResourceReference::of('comment', '1')));
        self::assertSame([], $store->ofMember(ResourceReference::of('user', '1'), 10));
    }

    #[Override]
    protected function store(): MentionRepository
    {
        return new InMemoryMentionRepository();
    }
}
