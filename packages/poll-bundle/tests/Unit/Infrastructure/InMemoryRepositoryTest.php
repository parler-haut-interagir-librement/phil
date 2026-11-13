<?php

declare(strict_types=1);

namespace Phil\PollBundle\Tests\Unit\Infrastructure;

use DateTimeImmutable;
use Override;
use Phil\Poll\Ballot;
use Phil\PollBundle\Domain\Model\Participation;
use Phil\PollBundle\Domain\Model\Vote;
use Phil\PollBundle\Infrastructure\Memory\InMemoryPollRepository;
use Phil\PollBundle\Infrastructure\Memory\InMemoryVoteRepository;
use Phil\Resource\ResourceReference;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(InMemoryPollRepository::class)]
#[CoversClass(InMemoryVoteRepository::class)]
final class InMemoryRepositoryTest extends RepositoryTestCase
{
    public function testAResetEmptiesTheStoresForTheNextRequest(): void
    {
        $polls = new InMemoryPollRepository();
        $votes = new InMemoryVoteRepository();
        $ada = ResourceReference::of('user', 'ada');
        $polls->save($this->poll('p1', '2026-11-08 10:00:00', null));
        $polls->raiseRevision('p1');

        $votes->add(Vote::named('v1', 'p1', $ada, Ballot::of(['a']), new DateTimeImmutable('2026-11-08 10:00:00 UTC')));
        $votes->add(Vote::anonymous('v2', 'p2', str_repeat('a', 64), Ballot::of(['b'])), Participation::of('p2', $ada));

        $polls->reset();
        $votes->reset();

        self::assertNull($polls->get('p1'));
        self::assertSame(0, $polls->revision('p1'));
        self::assertNull($votes->ofVoter('p1', $ada));
        self::assertFalse($votes->hasParticipated('p2', $ada));
        self::assertSame(0, $votes->count('p1') + $votes->count('p2'));

        $polls->save($this->poll('p1', '2026-11-08 10:00:00', null));
        $polls->raiseRevision('p1');
        self::assertSame(1, $polls->revision('p1'));
    }

    #[Override]
    protected function stores(): array
    {
        return [new InMemoryPollRepository(), new InMemoryVoteRepository()];
    }
}
