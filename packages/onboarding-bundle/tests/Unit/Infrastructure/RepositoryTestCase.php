<?php

declare(strict_types=1);

namespace Phil\OnboardingBundle\Tests\Unit\Infrastructure;

use DateTimeImmutable;
use Phil\OnboardingBundle\Domain\Flow\StepState;
use Phil\OnboardingBundle\Domain\Model\Progress;
use Phil\OnboardingBundle\Domain\Repository\ProgressRepository;
use PHPUnit\Framework\TestCase;

/**
 * What every store of the progress keeps, read back as the store gives it.
 */
abstract class RepositoryTestCase extends TestCase
{
    abstract protected function store(): ProgressRepository;

    protected function reread(ProgressRepository $store, string $user, string $flow): ?Progress
    {
        return $store->find($user, $flow);
    }

    public function testAProgressIsKeptAndChanged(): void
    {
        $store = $this->store();
        $progress = Progress::start('ada@example.com', 'author', new DateTimeImmutable('2026-11-08 10:00:00 UTC'));
        $progress->complete('profile', new DateTimeImmutable('2026-11-08 10:01:00 UTC'));

        $store->save($progress);

        $read = $this->reread($store, 'ada@example.com', 'author');
        self::assertInstanceOf(Progress::class, $read);
        self::assertSame(StepState::Completed, $read->stateOf('profile'));
        self::assertSame('2026-11-08T10:01:00+00:00', $read->timeOf('profile')?->format(\DATE_ATOM));

        $read->skip('page', new DateTimeImmutable('2026-11-08 10:02:00 UTC'));
        $read->dismiss(new DateTimeImmutable('2026-11-08 10:03:00 UTC'));

        $store->save($read);

        $again = $this->reread($store, 'ada@example.com', 'author');
        self::assertInstanceOf(Progress::class, $again);
        self::assertSame(StepState::Skipped, $again->stateOf('page'));
        self::assertTrue($again->isDismissed());
        self::assertSame('2026-11-08T10:03:00+00:00', $again->updatedAt()->format(\DATE_ATOM));
        self::assertNull($this->reread($store, 'ada@example.com', 'other'));
        self::assertNull($this->reread($store, 'bob', 'author'));
    }

    public function testTheProgressOfAUserIsListedAndRemoved(): void
    {
        $store = $this->store();
        $now = new DateTimeImmutable('2026-11-08 10:00:00 UTC');
        $store->save(Progress::start('ada', 'author', $now));
        $store->save(Progress::start('ada', 'reader', $now));
        $store->save(Progress::start('bob', 'author', $now));

        $flows = array_map(static fn (Progress $progress): string => $progress->flow, $store->ofUser('ada'));
        sort($flows);
        self::assertSame(['author', 'reader'], $flows);
        self::assertSame([], $store->ofUser('eve'));

        $store->removeAllOf('ada');

        self::assertSame([], $store->ofUser('ada'));
        self::assertNull($store->find('ada', 'author'));
        self::assertNotNull($store->find('bob', 'author'));
        self::assertCount(1, $store->ofUser('bob'));
    }
}
