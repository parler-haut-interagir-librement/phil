<?php

declare(strict_types=1);

namespace Phil\PollBundle\Tests\Unit\Infrastructure\Twig;

use Phil\Poll\Counting\ChoiceCount;
use Phil\Poll\CountingMethods;
use Phil\PollBundle\Application\PollDraft;
use Phil\PollBundle\Application\Polls;
use Phil\PollBundle\Application\PollViews;
use Phil\PollBundle\Domain\Status\ResultsVisibility;
use Phil\PollBundle\Domain\ValueObject\PollOption;
use Phil\PollBundle\Infrastructure\Memory\InMemoryPollRepository;
use Phil\PollBundle\Infrastructure\Memory\InMemoryVoteRepository;
use Phil\PollBundle\Infrastructure\Twig\PollTwig;
use Phil\PollBundle\Tests\Unit\Fixture\Policy;
use Phil\Resource\ResourceReference;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use Symfony\Component\Clock\MockClock;
use Twig\Attribute\AsTwigFunction;

#[CoversClass(PollTwig::class)]
final class PollTwigTest extends TestCase
{
    public function testTheViewerIsAReferenceTextOrNobody(): void
    {
        $clock = new MockClock('2026-11-08 10:00:00 UTC');
        $policy = new Policy();
        $methods = new CountingMethods([new ChoiceCount()]);
        $polls = new Polls(new InMemoryPollRepository(), new InMemoryVoteRepository(), $clock, $policy, $methods);
        $twig = new PollTwig(new PollViews($polls, $policy, $methods, $clock));
        $poll = $polls->create(new PollDraft('Tea or coffee?', [PollOption::of('tea', 'Tea'), PollOption::of('coffee', 'Coffee')], results: ResultsVisibility::AfterVoting), ResourceReference::of('user', 'ada'));
        $polls->cast($poll->id, ResourceReference::of('user', 'bob'), ['tea']);

        self::assertTrue($twig->ballot($poll->id, 'user:bob')->voted);
        self::assertTrue($twig->ballot($poll->id, ResourceReference::of('user', 'bob'))->voted);
        self::assertFalse($twig->ballot($poll->id)->voted);
        self::assertNotNull($twig->results($poll->id, 'user:bob'));
        self::assertNotNull($twig->results($poll->id, ResourceReference::of('user', 'bob')));
        self::assertNull($twig->results($poll->id));
    }

    public function testTheFunctionsAreNamedForTwig(): void
    {
        self::assertSame('phil_poll_ballot', new ReflectionMethod(PollTwig::class, 'ballot')->getAttributes(AsTwigFunction::class)[0]->newInstance()->name);
        self::assertSame('phil_poll_results', new ReflectionMethod(PollTwig::class, 'results')->getAttributes(AsTwigFunction::class)[0]->newInstance()->name);
    }
}
