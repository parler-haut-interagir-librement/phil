<?php

declare(strict_types=1);

namespace Phil\OnboardingBundle\Tests\Unit\Application;

use Override;
use Phil\OnboardingBundle\Application\Answer;
use Phil\OnboardingBundle\Application\Event\FlowFinished;
use Phil\OnboardingBundle\Application\Event\QuestionAnswered;
use Phil\OnboardingBundle\Application\Event\StepCompleted;
use Phil\OnboardingBundle\Application\Exception\StepRefused;
use Phil\OnboardingBundle\Application\Exception\UnknownFlow;
use Phil\OnboardingBundle\Application\NoAnswerGrants;
use Phil\OnboardingBundle\Application\Onboarding;
use Phil\OnboardingBundle\Application\StepProgress;
use Phil\OnboardingBundle\Domain\Exception\InvalidAnswer;
use Phil\OnboardingBundle\Domain\Exception\InvalidProgress;
use Phil\OnboardingBundle\Domain\Flow\Choice;
use Phil\OnboardingBundle\Domain\Flow\StepState;
use Phil\OnboardingBundle\Tests\Unit\Fixture\Kit;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\DependencyInjection\ServiceLocator;

#[CoversClass(Onboarding::class)]
#[CoversClass(Answer::class)]
#[CoversClass(NoAnswerGrants::class)]
#[CoversClass(QuestionAnswered::class)]
#[CoversClass(StepRefused::class)]
#[CoversClass(StepProgress::class)]
final class AnswerTest extends TestCase
{
    private Kit $kit;

    #[Override]
    protected function setUp(): void
    {
        $this->kit = new Kit();
    }

    public function testAnAnswerIsGrantedThenTheStepCompleted(): void
    {
        $progress = $this->kit->onboarding->answer('ada', 'member', 'topics', ['events', 'news']);

        [$answer] = $this->kit->grants->answers;
        self::assertSame(['ada', 'member', 'topics'], [$answer->user, $answer->flow, $answer->step]);
        self::assertSame(['news', 'events'], $answer->choices);
        self::assertSame(['ROLE_EVENTS'], $answer->roles);
        self::assertSame(['letter.news', 'letter.events'], $answer->subscriptions);
        self::assertSame(StepState::Completed, $progress->step('topics')?->state);
        self::assertSame(StepState::Completed, $this->kit->repository->find('ada', 'member')?->stateOf('topics'));
        self::assertEquals([new QuestionAnswered('ada', 'member', 'topics', ['news', 'events']), new StepCompleted('ada', 'member', 'topics')], $this->kit->events->events);
        $topics = $progress->step('topics');
        $level = $progress->step('level');
        self::assertInstanceOf(StepProgress::class, $topics);
        self::assertInstanceOf(StepProgress::class, $level);
        self::assertFalse($topics->isAnswerable());
        self::assertTrue($level->isAnswerable());
    }

    public function testTheGrantsOfSeveralChoicesAreListedOnceEach(): void
    {
        $answer = new Answer('ada', 'member', 'topics', [
            new Choice('a', 'A', ['r1'], ['s1']),
            new Choice('b', 'B', ['r1', 'r2'], ['s1', 's2']),
        ]);

        self::assertSame(['r1', 'r2'], $answer->roles);
        self::assertSame(['s1', 's2'], $answer->subscriptions);
    }

    public function testTheLastAnswerFinishesTheFlow(): void
    {
        $this->kit->onboarding->answer('ada', 'member', 'topics', ['quiet']);
        $this->kit->events->events = [];

        $progress = $this->kit->onboarding->answer('ada', 'member', 'level', ['new']);

        self::assertTrue($progress->isFinished());
        self::assertEquals([new QuestionAnswered('ada', 'member', 'level', ['new']), new StepCompleted('ada', 'member', 'level'), new FlowFinished('ada', 'member')], $this->kit->events->events);
        self::assertSame([[], []], [$this->kit->grants->answers[0]->roles, $this->kit->grants->answers[0]->subscriptions]);
        self::assertSame(['ROLE_NEW'], $this->kit->grants->answers[1]->roles);
    }

    public function testAnAnsweredOrSkippedStepIsNotAnsweredAgain(): void
    {
        $this->kit->onboarding->answer('ada', 'member', 'topics', ['news']);
        $this->kit->onboarding->skip('linus', 'member', 'topics');

        foreach (['ada', 'linus'] as $user) {
            try {
                $this->kit->onboarding->answer($user, 'member', 'topics', ['events']);
                self::fail('Accepted.');
            } catch (StepRefused $stepRefused) {
                self::assertSame('The step "topics" of the flow "member" is no longer pending.', $stepRefused->getMessage());
            }
        }

        self::assertCount(1, $this->kit->grants->answers);
    }

    public function testAStepWithoutAQuestionIsNotAnswered(): void
    {
        $this->expectExceptionObject(StepRefused::notAQuestion('author', 'profile'));

        $this->kit->onboarding->answer('ada', 'author', 'profile', ['news']);
    }

    public function testAQuestionIsNotTicked(): void
    {
        $this->expectExceptionObject(StepRefused::notManual('member', 'topics'));

        $this->kit->onboarding->markDone('ada', 'member', 'topics');
    }

    public function testAnUnknownStep(): void
    {
        $this->expectExceptionObject(UnknownFlow::step('member', 'nothing'));

        $this->kit->onboarding->answer('ada', 'member', 'nothing', ['news']);
    }

    public function testABadAnswerGrantsAndRecordsNothing(): void
    {
        try {
            $this->kit->onboarding->answer('ada', 'member', 'level', ['new', 'old']);
            self::fail('Accepted.');
        } catch (InvalidAnswer $invalidAnswer) {
            self::assertSame('This question takes one choice.', $invalidAnswer->getMessage());
        }

        self::assertSame([], $this->kit->grants->answers);
        self::assertNull($this->kit->repository->find('ada', 'member'));
        self::assertSame([], $this->kit->events->events);
    }

    public function testAGrantThatFailsLeavesTheQuestionToAnswer(): void
    {
        $this->kit->grants->failing = true;

        try {
            $this->kit->onboarding->answer('ada', 'member', 'topics', ['news']);
            self::fail('Accepted.');
        } catch (RuntimeException $runtimeException) {
            self::assertSame('The directory is down.', $runtimeException->getMessage());
        }

        self::assertNull($this->kit->repository->find('ada', 'member'));
        self::assertSame([], $this->kit->events->events);
    }

    public function testAUserThatCannotBeKeptIsRefusedBeforeTheGrant(): void
    {
        try {
            $this->kit->onboarding->answer('', 'member', 'topics', ['news']);
            self::fail('Accepted.');
        } catch (InvalidProgress) {
        }

        self::assertSame([], $this->kit->grants->answers);
    }

    public function testWithoutGrantsNothingIsGranted(): void
    {
        $onboarding = new Onboarding($this->kit->catalog, $this->kit->repository, new ServiceLocator([]), $this->kit->clock);

        self::assertTrue($onboarding->answer('ada', 'member', 'topics', ['news'])->step('topics')?->isCompleted());
        new NoAnswerGrants()->grant(new Answer('ada', 'member', 'topics', []));
        self::assertSame([], new Answer('ada', 'member', 'topics', [])->roles);
    }
}
