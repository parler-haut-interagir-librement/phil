<?php

declare(strict_types=1);

namespace Phil\OnboardingBundle\Tests\Unit\Domain;

use DateTimeImmutable;
use Phil\OnboardingBundle\Domain\Exception\InvalidFlow;
use Phil\OnboardingBundle\Domain\Exception\InvalidProgress;
use Phil\OnboardingBundle\Domain\Flow\StepState;
use Phil\OnboardingBundle\Domain\Model\Progress;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

#[CoversClass(Progress::class)]
#[CoversClass(InvalidProgress::class)]
final class ProgressTest extends TestCase
{
    public function testANewProgressHasEveryStepPending(): void
    {
        $progress = Progress::start('ada@example.com', 'author', new DateTimeImmutable('2026-11-08 12:00:00.75 America/Montreal'));

        self::assertSame(['ada@example.com', 'author'], [$progress->user, $progress->flow]);
        self::assertSame(StepState::Pending, $progress->stateOf('profile'));
        self::assertNull($progress->timeOf('profile'));
        self::assertSame('2026-11-08T17:00:00.000000+00:00', $progress->startedAt()->format('Y-m-d\TH:i:s.uP'));
        self::assertSame('2026-11-08T17:00:00.000000+00:00', $progress->updatedAt()->format('Y-m-d\TH:i:s.uP'));
        self::assertNull($progress->finishedAt());
        self::assertNull($progress->dismissedAt());
        self::assertFalse($progress->isDismissed());
    }

    public function testStepsAreCompletedAndSkipped(): void
    {
        $progress = Progress::start('ada', 'author', new DateTimeImmutable('2026-11-08 10:00:00 UTC'));

        self::assertTrue($progress->skip('profile', new DateTimeImmutable('2026-11-08 10:01:00 UTC')));
        self::assertFalse($progress->skip('profile', new DateTimeImmutable('2026-11-08 10:02:00 UTC')));
        self::assertSame(StepState::Skipped, $progress->stateOf('profile'));
        self::assertSame('2026-11-08T10:01:00+00:00', $progress->timeOf('profile')?->format(\DATE_ATOM));

        self::assertTrue($progress->complete('profile', new DateTimeImmutable('2026-11-08 06:03:00 America/Montreal')));
        self::assertFalse($progress->complete('profile', new DateTimeImmutable('2026-11-08 10:04:00 UTC')));
        self::assertFalse($progress->skip('profile', new DateTimeImmutable('2026-11-08 10:05:00 UTC')));
        self::assertSame(StepState::Completed, $progress->stateOf('profile'));
        self::assertSame('2026-11-08T11:03:00+00:00', $progress->timeOf('profile')->format(\DATE_ATOM));
        self::assertSame('2026-11-08T11:03:00+00:00', $progress->updatedAt()->format(\DATE_ATOM));
        self::assertSame('2026-11-08T10:00:00+00:00', $progress->startedAt()->format(\DATE_ATOM));
    }

    public function testFinishedAndDismissedOnce(): void
    {
        $progress = Progress::start('ada', 'author', new DateTimeImmutable('2026-11-08 10:00:00 UTC'));

        self::assertTrue($progress->finish(new DateTimeImmutable('2026-11-08 10:01:00 UTC')));
        self::assertFalse($progress->finish(new DateTimeImmutable('2026-11-08 10:02:00 UTC')));
        self::assertTrue($progress->dismiss(new DateTimeImmutable('2026-11-08 10:03:00 UTC')));
        self::assertFalse($progress->dismiss(new DateTimeImmutable('2026-11-08 10:04:00 UTC')));

        self::assertSame('2026-11-08T10:01:00+00:00', $progress->finishedAt()?->format(\DATE_ATOM));
        self::assertSame('2026-11-08T10:03:00+00:00', $progress->dismissedAt()?->format(\DATE_ATOM));
        self::assertSame('2026-11-08T10:03:00+00:00', $progress->updatedAt()->format(\DATE_ATOM));
        self::assertTrue($progress->isDismissed());
    }

    public function testResetStartsAgain(): void
    {
        $progress = Progress::start('ada', 'author', new DateTimeImmutable('2026-11-08 10:00:00 UTC'));
        $progress->complete('profile', new DateTimeImmutable('2026-11-08 10:01:00 UTC'));
        $progress->finish(new DateTimeImmutable('2026-11-08 10:01:00 UTC'));
        $progress->dismiss(new DateTimeImmutable('2026-11-08 10:02:00 UTC'));

        $progress->reset(new DateTimeImmutable('2026-11-08 10:05:00 UTC'));

        self::assertSame(StepState::Pending, $progress->stateOf('profile'));
        self::assertNull($progress->finishedAt());
        self::assertNull($progress->dismissedAt());
        self::assertSame('2026-11-08T10:05:00+00:00', $progress->startedAt()->format(\DATE_ATOM));
        self::assertSame('2026-11-08T10:05:00+00:00', $progress->updatedAt()->format(\DATE_ATOM));
    }

    public function testAStoredStepThatCannotBeReadIsPending(): void
    {
        $progress = Progress::start('ada', 'author', new DateTimeImmutable('2026-11-08 10:00:00 UTC'));
        $steps = new ReflectionProperty(Progress::class, 'steps');
        $steps->setValue($progress, ['a' => ['state' => 'lost', 'at' => '2026-11-08T10:00:00+00:00'], 'b' => ['state' => 'completed', 'at' => 'yesterday']]);

        self::assertSame(StepState::Pending, $progress->stateOf('a'));
        self::assertNull($progress->timeOf('a'));
        self::assertSame(StepState::Completed, $progress->stateOf('b'));
        self::assertNull($progress->timeOf('b'));
    }

    public function testTheUserIdentifierFitsItsColumn(): void
    {
        self::assertSame(320, \strlen(Progress::start(str_repeat('a', 320), 'f', new DateTimeImmutable())->user));

        foreach (['', str_repeat('a', 321)] as $user) {
            try {
                Progress::start($user, 'f', new DateTimeImmutable());
                self::fail('The user was accepted.');
            } catch (InvalidProgress $invalidProgress) {
                self::assertSame('A user identifier has 1 to 320 bytes.', $invalidProgress->getMessage());
            }
        }

        $this->expectException(InvalidFlow::class);
        Progress::start('ada', 'Bad flow', new DateTimeImmutable());
    }
}
