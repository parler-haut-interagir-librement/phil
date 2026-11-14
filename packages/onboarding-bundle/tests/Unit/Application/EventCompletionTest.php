<?php

declare(strict_types=1);

namespace Phil\OnboardingBundle\Tests\Unit\Application;

use Phil\OnboardingBundle\Application\Contract\NamesOnboardingUser;
use Phil\OnboardingBundle\Application\EventCompletion;
use Phil\OnboardingBundle\Domain\Flow\StepState;
use Phil\OnboardingBundle\Tests\Unit\Fixture\FixedUser;
use Phil\OnboardingBundle\Tests\Unit\Fixture\Kit;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use stdClass;

#[CoversClass(EventCompletion::class)]
final class EventCompletionTest extends TestCase
{
    public function testAnEventCompletesItsStepsForTheCurrentUser(): void
    {
        $kit = new Kit();
        $listener = new EventCompletion($kit->catalog, $kit->onboarding, new FixedUser('ada'));

        $listener(new stdClass(), 'app.published');

        self::assertSame(StepState::Completed, $kit->repository->find('ada', 'author')?->stateOf('publish'));
        self::assertSame(StepState::Completed, $kit->repository->find('ada', 'other')?->stateOf('first'));
    }

    public function testAnEventNamesItsUser(): void
    {
        $kit = new Kit();
        $listener = new EventCompletion($kit->catalog, $kit->onboarding, new FixedUser('ada'));

        $listener($this->event('bob'), 'app.published');
        $listener($this->event(null), 'app.published');

        self::assertSame(StepState::Completed, $kit->repository->find('bob', 'author')?->stateOf('publish'));
        self::assertSame([], $kit->repository->ofUser('ada'));
    }

    public function testNobodysEventAndOtherEventsCompleteNothing(): void
    {
        $kit = new Kit();
        new EventCompletion($kit->catalog, $kit->onboarding, new FixedUser())(new stdClass(), 'app.published');
        $user = new FixedUser('ada');
        new EventCompletion($kit->catalog, $kit->onboarding, $user)(new stdClass(), 'app.other');

        self::assertSame([], $kit->repository->ofUser('ada'));
        self::assertSame([], $kit->events->events);
    }

    public function testAProgressThatCannotBeKeptIsPassedOver(): void
    {
        $kit = new Kit();
        new EventCompletion($kit->catalog, $kit->onboarding, new FixedUser(str_repeat('a', 321)))(new stdClass(), 'app.published');

        $kit->repository->racing = true;
        new EventCompletion($kit->catalog, $kit->onboarding, new FixedUser('ada'))(new stdClass(), 'app.published');

        self::assertSame([], $kit->events->events);
    }

    private function event(?string $user): NamesOnboardingUser
    {
        return new readonly class($user) implements NamesOnboardingUser {
            public function __construct(
                private ?string $user,
            ) {
            }

            public function onboardingUser(): ?string
            {
                return $this->user;
            }
        };
    }
}
