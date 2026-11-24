<?php

declare(strict_types=1);

namespace Phil\FaqBundle\Tests\Unit\Application;

use DateTimeImmutable;
use Override;
use Phil\FaqBundle\Application\Contract\Throttle;
use Phil\FaqBundle\Application\Exception\AlreadyVoted;
use Phil\FaqBundle\Application\Exception\TooManyRequests;
use Phil\FaqBundle\Application\Exception\UnknownQuestion;
use Phil\FaqBundle\Application\Exception\VoteRefused;
use Phil\FaqBundle\Application\HelpfulVotes;
use Phil\FaqBundle\Application\VoteTokens;
use Phil\FaqBundle\Domain\Model\Question;
use Phil\FaqBundle\Domain\ValueObject\Locale;
use Phil\FaqBundle\Domain\ValueObject\VoteCount;
use Phil\FaqBundle\Infrastructure\Doctrine\DbalSpentVoteTokens;
use Phil\FaqBundle\Infrastructure\Doctrine\DoctrineFaqRepository;
use Phil\FaqBundle\Tests\Fixtures\Database;
use Phil\FaqBundle\Tests\Fixtures\Faq;
use Phil\FaqBundle\Tests\Fixtures\Throttles;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

#[CoversClass(HelpfulVotes::class)]
#[CoversClass(AlreadyVoted::class)]
#[CoversClass(TooManyRequests::class)]
final class HelpfulVotesTest extends TestCase
{
    private MockClock $clock;

    private DoctrineFaqRepository $repository;

    private DbalSpentVoteTokens $spent;

    private VoteTokens $tokens;

    private Question $pay;

    private Question $order;

    #[Override]
    protected function setUp(): void
    {
        $entityManager = Database::entityManager();
        $this->clock = new MockClock('2026-11-21 10:00:00 UTC');
        $this->repository = new DoctrineFaqRepository($entityManager);
        $this->spent = new DbalSpentVoteTokens($entityManager->getConnection());
        $this->tokens = new VoteTokens('secret of the test', $this->clock, 3_600);
        $editor = Faq::editor($entityManager, $this->clock);
        $section = $editor->addSection(Locale::fromString('en'), 'Orders');
        $this->pay = $editor->addQuestion($section->id, Locale::fromString('en'), 'How do I pay?', 'Card.');
        $this->order = $editor->addQuestion($section->id, Locale::fromString('en'), 'Where is my order?', 'Here.');
    }

    public function testAPageTokenVotesOncePerQuestion(): void
    {
        $throttle = new Throttles();
        $votes = $this->votes($throttle);
        $token = $this->tokens->issue();

        self::assertEquals(new VoteCount(1, 0), $votes->vote('how-do-i-pay', true, $token));
        self::assertEquals(new VoteCount(0, 1), $votes->vote('where-is-my-order', false, $token));
        self::assertEquals(new VoteCount(1, 1), $votes->vote('where-is-my-order', true, $this->tokens->issue()));
        self::assertSame([$this->pay->id, $this->order->id, $this->order->id], $throttle->keys);

        $this->expectExceptionObject(AlreadyVoted::once());

        $votes->vote('how-do-i-pay', false, $token);
    }

    public function testAnUnknownQuestionIsRefusedBeforeTheToken(): void
    {
        $this->expectExceptionObject(UnknownQuestion::named('nope'));

        $this->votes()->vote('nope', true, 'not a token');
    }

    public function testAnInvalidTokenIsRefusedAndNothingCounts(): void
    {
        $throttle = new Throttles();

        try {
            $this->votes($throttle)->vote('how-do-i-pay', true, 'forged');
            self::fail('A forged token is refused.');
        } catch (VoteRefused) {
        }

        self::assertSame([], $throttle->keys);
        self::assertEquals(new VoteCount(1, 0), $this->repository->countVote($this->pay->id, true));
    }

    public function testTooManyVotesOnAQuestionAreHeldBack(): void
    {
        $votes = $this->votes(new Throttles(1));
        $votes->vote('how-do-i-pay', true, $this->tokens->issue());

        try {
            $votes->vote('how-do-i-pay', true, $this->tokens->issue());
            self::fail('The second vote waits.');
        } catch (TooManyRequests $tooManyRequests) {
            self::assertSame([7, 'Too many requests: the next one may come in 7 seconds.'], [$tooManyRequests->retryAfter, $tooManyRequests->getMessage()]);
        }

        self::assertEquals(new VoteCount(2, 0), $this->repository->countVote($this->pay->id, true));
    }

    public function testExpiredSpentTokensAreForgottenOnTheWay(): void
    {
        $this->spent->spend('old', new DateTimeImmutable('2026-11-21 09:59:59 UTC'));
        $this->spent->spend('current', new DateTimeImmutable('2026-11-21 10:00:00 UTC'));

        $this->votes()->vote('how-do-i-pay', true, $this->tokens->issue());

        self::assertTrue($this->spent->spend('old', new DateTimeImmutable('2026-11-22')));
        self::assertFalse($this->spent->spend('current', new DateTimeImmutable('2026-11-22')));
    }

    private function votes(?Throttles $throttle = null): HelpfulVotes
    {
        return new HelpfulVotes($this->repository, $this->spent, $this->tokens, $throttle ?? new Throttles(), $this->clock);
    }

    public function testAQuestionRemovedWhileItIsVotedForIsUnknown(): void
    {
        $repository = $this->repository;
        $pay = $this->pay;
        $throttle = new readonly class($repository, $pay) implements Throttle {
            public function __construct(
                private DoctrineFaqRepository $repository,
                private Question $question,
            ) {
            }

            #[Override]
            public function consume(string $key): void
            {
                $this->repository->remove($this->question);
            }
        };
        $votes = new HelpfulVotes($this->repository, $this->spent, $this->tokens, $throttle, $this->clock);

        $this->expectExceptionObject(UnknownQuestion::named('how-do-i-pay'));

        $votes->vote('how-do-i-pay', true, $this->tokens->issue());
    }
}
