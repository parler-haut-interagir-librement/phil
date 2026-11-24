<?php

declare(strict_types=1);

namespace Phil\ReviewsBundle\Tests\Unit\Domain;

use DateTimeImmutable;
use Phil\ReviewsBundle\Domain\Exception\DuplicateReview;
use Phil\ReviewsBundle\Domain\Exception\InvalidReview;
use Phil\ReviewsBundle\Domain\Exception\ReviewRefused;
use Phil\ReviewsBundle\Domain\Status\ContestOutcome;
use Phil\ReviewsBundle\Domain\Texts;
use Phil\ReviewsBundle\Domain\ValueObject\BayesianPrior;
use Phil\ReviewsBundle\Domain\ValueObject\Compensation;
use Phil\ReviewsBundle\Domain\ValueObject\Contest;
use Phil\ReviewsBundle\Domain\ValueObject\CriterionRating;
use Phil\ReviewsBundle\Domain\ValueObject\MemberId;
use Phil\ReviewsBundle\Domain\ValueObject\RatingSummary;
use Phil\ReviewsBundle\Domain\ValueObject\Response;
use Phil\ReviewsBundle\Domain\ValueObject\ReviewContent;
use Phil\ReviewsBundle\Domain\ValueObject\ReviewId;
use Phil\ReviewsBundle\Domain\ValueObject\SubjectRef;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;

#[CoversClass(Texts::class)]
#[CoversClass(SubjectRef::class)]
#[CoversClass(MemberId::class)]
#[CoversClass(ReviewId::class)]
#[CoversClass(ReviewContent::class)]
#[CoversClass(Compensation::class)]
#[CoversClass(Response::class)]
#[CoversClass(Contest::class)]
#[CoversClass(BayesianPrior::class)]
#[CoversClass(RatingSummary::class)]
#[CoversClass(CriterionRating::class)]
#[CoversClass(InvalidReview::class)]
#[CoversClass(ReviewRefused::class)]
#[CoversClass(DuplicateReview::class)]
final class ValueObjectsTest extends TestCase
{
    public function testATextIsTrimmedAndEmptyIsNothing(): void
    {
        self::assertSame("Fun\nindeed", Texts::optional("  Fun\nindeed\t ", 20, 'A text'));
        self::assertNull(Texts::optional(" \n ", 20, 'A text'));
        self::assertNull(Texts::optional(null, 20, 'A text'));
        self::assertSame('éèêëà', Texts::optional('éèêëà', 5, 'A text'));
        self::assertSame("Tab\tand\r\nbreak", Texts::optional("Tab\tand\r\nbreak", 20, 'A text'));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function refusedTexts(): iterable
    {
        yield 'too long' => ['abcdef', 'A text holds 5 characters at most.'];
        yield 'invalid UTF-8' => ["a\xC3", 'A text is text without control characters.'];
        yield 'NUL' => ["a\0b", 'A text is text without control characters.'];
        yield 'escape' => ["a\x1Bb", 'A text is text without control characters.'];
        yield 'delete' => ["a\x7Fb", 'A text is text without control characters.'];
        yield 'vertical tab' => ["a\x0Bb", 'A text is text without control characters.'];
        yield 'form feed' => ["a\x0Cb", 'A text is text without control characters.'];
        yield 'shift out' => ["a\x0Eb", 'A text is text without control characters.'];
        yield 'unit separator' => ["a\x1Fb", 'A text is text without control characters.'];
        yield 'backspace' => ["a\x08b", 'A text is text without control characters.'];
    }

    #[DataProvider('refusedTexts')]
    public function testATextIsRefused(string $text, string $message): void
    {
        $this->expectExceptionObject(new InvalidReview($message));
        Texts::optional($text, 5, 'A text');
    }

    public function testARequiredTextIsNotEmpty(): void
    {
        self::assertSame('x', Texts::required(' x ', 5, 'A text'));
        $this->expectExceptionObject(new InvalidReview('A text is not empty.'));
        Texts::required('  ', 5, 'A text');
    }

    public function testACode(): void
    {
        self::assertSame('a', Texts::code('a', 'a code'));
        self::assertSame('a-b_9', Texts::code('a-b_9', 'a code'));
        self::assertSame('a'.str_repeat('b', 63), Texts::code('a'.str_repeat('b', 63), 'a code'));

        foreach (['', 'A', '9a', 'a.b', 'a b', 'a'.str_repeat('b', 64), "a\n"] as $refused) {
            try {
                Texts::code($refused, 'a code');
                self::fail(\sprintf('"%s" was taken as a code.', $refused));
            } catch (InvalidReview $invalid) {
                self::assertSame(\sprintf('"%s" is not a code: a lowercase letter, then up to 63 lowercase letters, digits, "-" and "_".', $refused), $invalid->getMessage());
            }
        }
    }

    public function testAnIdentifier(): void
    {
        self::assertSame(str_repeat('x', 10), Texts::identifier(str_repeat('x', 10), 10, 'An id'));
        self::assertSame('é', Texts::identifier('é', 2, 'An id'));

        foreach (['', str_repeat('x', 11), 'a b', "a\x7F", "\xC3", 'éé'.str_repeat('x', 7)] as $refused) {
            try {
                Texts::identifier($refused, 10, 'An id');
                self::fail(\sprintf('"%s" was taken as an identifier.', $refused));
            } catch (InvalidReview $invalid) {
                self::assertSame('An id is 1 to 10 bytes of visible characters.', $invalid->getMessage());
            }
        }
    }

    public function testASubject(): void
    {
        $subject = SubjectRef::of('board.game-2_x', 'catan/1');

        self::assertSame('board.game-2_x', $subject->type);
        self::assertSame('catan/1', $subject->id);
        self::assertSame('board.game-2_x:catan/1', (string) $subject);
        self::assertTrue($subject->equals(SubjectRef::of('board.game-2_x', 'catan/1')));
        self::assertFalse($subject->equals(SubjectRef::of('board.game-2_x', 'catan/2')));
        self::assertFalse($subject->equals(SubjectRef::of('game', 'catan/1')));
        self::assertSame(190, \strlen(SubjectRef::of('game', str_repeat('x', 190))->id));
        self::assertSame(64, \strlen(SubjectRef::of('g'.str_repeat('x', 63), 'a')->type));
    }

    public function testAMalformedSubjectIsRefused(): void
    {
        foreach ([['Game', 'a'], ['1game', 'a'], ['g'.str_repeat('x', 64), 'a'], ['game', ''], ['game', str_repeat('x', 191)], ['game', 'a b']] as [$type, $id]) {
            try {
                SubjectRef::of($type, $id);
                self::fail(\sprintf('"%s:%s" was taken as a subject.', $type, $id));
            } catch (InvalidReview) {
                self::addToAssertionCount(1);
            }
        }

        $this->expectExceptionObject(new InvalidReview('"Game" is not a subject type: a lowercase letter, then up to 63 lowercase letters, digits, ".", "-" and "_".'));
        SubjectRef::of('Game', 'a');
    }

    public function testAMember(): void
    {
        self::assertSame('ada-42', MemberId::fromString('ada-42')->value);
        self::assertTrue(MemberId::fromString('ada')->equals(MemberId::fromString('ada')));
        self::assertFalse(MemberId::fromString('ada')->equals(MemberId::fromString('bob')));
        self::assertSame(190, \strlen(MemberId::fromString(str_repeat('a', 190))->value));

        try {
            MemberId::fromString(str_repeat('a', 191));
            self::fail('A long identifier was taken.');
        } catch (InvalidReview $invalidReview) {
            self::assertSame('A member identifier is 1 to 190 bytes of visible characters.', $invalidReview->getMessage());
        }

        $this->expectExceptionObject(new InvalidReview('A member identifier is not an email address.'));
        MemberId::fromString('ada@example.org');
    }

    public function testAReviewIdentifier(): void
    {
        $id = ReviewId::generate();

        self::assertMatchesRegularExpression('/^[0-9a-f]{8}-[0-9a-f]{4}-7[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/', $id->value);
        self::assertTrue($id->equals(ReviewId::fromString($id->value)));
        self::assertFalse($id->equals(ReviewId::generate()));

        foreach (['nope', strtoupper($id->value)] as $refused) {
            try {
                ReviewId::fromString($refused);
                self::fail('A malformed identifier was taken.');
            } catch (InvalidReview $invalid) {
                self::assertSame('A review identifier is a UUID in lowercase.', $invalid->getMessage());
            }
        }
    }

    public function testAContentIsNormalised(): void
    {
        $content = new ReviewContent(10, ['rules' => 1, 'art' => 10], ' Title ', ' Body ', ['quick', 'family', 'quick']);

        self::assertSame(10, $content->overall);
        self::assertSame(['art' => 10, 'rules' => 1], $content->criteria);
        self::assertSame('Title', $content->title);
        self::assertSame('Body', $content->body);
        self::assertSame(['quick', 'family'], $content->superlatives);
        self::assertNull(new ReviewContent(1)->title);
    }

    /**
     * @return iterable<string, array{callable(): ReviewContent, string}>
     */
    public static function refusedContents(): iterable
    {
        yield 'overall zero' => [static fn (): ReviewContent => new ReviewContent(0), 'The overall score is from 1 to 10.'];
        yield 'overall eleven' => [static fn (): ReviewContent => new ReviewContent(11), 'The overall score is from 1 to 10.'];
        yield 'criterion zero' => [static fn (): ReviewContent => new ReviewContent(3, ['rules' => 0]), 'The score of "rules" is from 1 to 10.'];
        yield 'criterion not an integer' => [static fn (): ReviewContent => new ReviewContent(3, ['rules' => '3']), 'The scores of the criteria are integers by code.'];
        yield 'criterion without a code' => [static fn (): ReviewContent => new ReviewContent(3, [4]), 'The scores of the criteria are integers by code.'];
        yield 'too many criteria' => [static fn (): ReviewContent => new ReviewContent(3, array_combine(array_map(static fn (int $i): string => 'c'.$i, range(1, 21)), array_fill(0, 21, 3))), 'A review rates 20 criteria at most.'];
        yield 'four superlatives' => [static fn (): ReviewContent => new ReviewContent(3, [], null, null, ['a', 'b', 'c', 'd']), 'A review gives 3 superlatives at most.'];
        yield 'long title' => [static fn (): ReviewContent => new ReviewContent(3, [], str_repeat('x', 151)), 'A title holds 150 characters at most.'];
        yield 'long body' => [static fn (): ReviewContent => new ReviewContent(3, [], null, str_repeat('x', 20_001)), 'A review holds 20000 characters at most.'];
        yield 'malformed superlative' => [static fn (): ReviewContent => new ReviewContent(3, [], null, null, ['Nope']), '"Nope" is not a superlative: a lowercase letter, then up to 63 lowercase letters, digits, "-" and "_".'];
    }

    /**
     * @param callable(): ReviewContent $content
     */
    #[DataProvider('refusedContents')]
    public function testAContentIsRefused(callable $content, string $message): void
    {
        $this->expectExceptionObject(new InvalidReview($message));
        $content();
    }

    public function testTwentyCriteriaAndThreeSuperlativesAreTaken(): void
    {
        $content = new ReviewContent(3, array_combine(array_map(static fn (int $i): string => 'c'.$i, range(1, 20)), array_fill(0, 20, 3)), null, str_repeat('x', 20_000), ['a', 'b', 'c']);

        self::assertCount(20, $content->criteria);
        self::assertCount(3, $content->superlatives);
    }

    public function testACompensation(): void
    {
        self::assertFalse(Compensation::none()->received);
        self::assertNull(Compensation::none()->note);
        self::assertTrue(Compensation::received()->received);
        self::assertSame('A free copy', Compensation::received(' A free copy ')->note);
        self::assertSame('A free copy', Compensation::of(true, 'A free copy')->note);
        self::assertNull(Compensation::received('A free copy')->withoutNote()->note);
        self::assertTrue(Compensation::received('A free copy')->withoutNote()->received);
        self::assertFalse(Compensation::of(false, '  ')->received);

        try {
            Compensation::received(str_repeat('x', 201));
            self::fail('A long note was taken.');
        } catch (InvalidReview $invalidReview) {
            self::assertSame('What was received holds 200 characters at most.', $invalidReview->getMessage());
        }

        $this->expectExceptionObject(new InvalidReview('What was received is told only of a review given against a compensation.'));
        Compensation::of(false, 'A free copy');
    }

    public function testAResponseAndAContest(): void
    {
        $at = new DateTimeImmutable('2026-11-21 UTC');
        self::assertSame('Thanks', new Response(' Thanks ', null, $at)->text);

        $contest = new Contest(' Evidence ', $at);
        self::assertSame('Evidence', $contest->evidence);
        self::assertTrue($contest->isOpen());
        $settled = $contest->settle(ContestOutcome::Rejected, $at->modify('+1 day'));
        self::assertFalse($settled->isOpen());
        self::assertSame(ContestOutcome::Rejected, $settled->outcome);
        self::assertEquals($at->modify('+1 day'), $settled->settledAt);
        self::assertSame('Evidence', $settled->evidence);
        self::assertEquals($at, $settled->filedAt);

        try {
            new Response(str_repeat('x', 5001), null, $at);
            self::fail('A long response was taken.');
        } catch (InvalidReview $invalidReview) {
            self::assertSame('A response holds 5000 characters at most.', $invalidReview->getMessage());
        }

        $this->expectExceptionObject(new InvalidReview('The evidence of a contest is not empty.'));
        new Contest(' ', $at);
    }

    public function testTheWeightedAverage(): void
    {
        $prior = new BayesianPrior(5, 3.0, 3);

        self::assertSame(3.0, $prior->weigh(0, 0));
        self::assertEqualsWithDelta((3.0 * 5 + 10) / 7, $prior->weigh(2, 10), 1e-9);
        self::assertSame(4.0, new BayesianPrior(0, 3.0, 0)->weigh(2, 8));
        self::assertSame(3.0, new BayesianPrior(0, 3.0, 0)->weigh(0, 0));
        self::assertFalse($prior->shows(2));
        self::assertTrue($prior->shows(3));
        self::assertFalse(new BayesianPrior(0, 3.0, 0)->shows(0));
        self::assertTrue(new BayesianPrior(0, 3.0, 0)->shows(1));
        self::assertSame(3.0, BayesianPrior::neutral(5, 2, 1)->mean);
        self::assertSame(5.5, BayesianPrior::neutral(10, 2, 1)->mean);
        self::assertSame(2, BayesianPrior::neutral(10, 2, 1)->count);
        self::assertSame(1, BayesianPrior::neutral(10, 2, 1)->minimum);
        self::assertSame(10_000, new BayesianPrior(10_000, 1.0, 10_000)->count);
        self::assertSame(10.0, new BayesianPrior(0, 10.0, 0)->mean);
    }

    /**
     * @return iterable<string, array{int, float, int, string}>
     */
    public static function refusedPriors(): iterable
    {
        $counts = 'The counts of a weighted average are from 0 to 10000.';
        $mean = 'The neutral value of a weighted average is from 1 to 10.';

        yield 'negative count' => [-1, 3.0, 0, $counts];
        yield 'large count' => [10_001, 3.0, 0, $counts];
        yield 'negative minimum' => [0, 3.0, -1, $counts];
        yield 'large minimum' => [0, 3.0, 10_001, $counts];
        yield 'low mean' => [0, 0.99, 0, $mean];
        yield 'high mean' => [0, 10.01, 0, $mean];
        yield 'infinite mean' => [0, \INF, 0, $mean];
        yield 'no number' => [0, \NAN, 0, $mean];
    }

    #[DataProvider('refusedPriors')]
    public function testAPriorIsRefused(int $count, float $mean, int $minimum, string $message): void
    {
        $this->expectExceptionObject(new InvalidReview($message));
        new BayesianPrior($count, $mean, $minimum);
    }

    public function testASummary(): void
    {
        $subject = SubjectRef::of('game', 'catan');
        $empty = RatingSummary::empty($subject);

        self::assertNull($empty->mean());
        self::assertTrue($empty->allFromSite());
        self::assertFalse($empty->suspended);
        self::assertTrue(RatingSummary::empty($subject, true)->suspended);

        $summary = new RatingSummary($subject, 4, 14, 3.5, ['rules' => new CriterionRating('rules', 4.5, 2)], ['quick' => 2, 'family' => 3, 'gorgeous' => 2, 'thinky' => 0, 'odd' => 2], 1);
        self::assertSame(3.5, $summary->mean());
        self::assertFalse($summary->allFromSite());
        self::assertSame(['family' => 3, 'gorgeous' => 2, 'quick' => 2], $summary->topSuperlatives(['gorgeous', 'quick', 'family', 'thinky'], 3));
        self::assertSame(['family' => 3, 'quick' => 2, 'gorgeous' => 2, 'odd' => 2], $summary->topSuperlatives(['quick', 'gorgeous'], 10));
        self::assertSame([], $summary->topSuperlatives([], 0));
        self::assertSame([], $summary->topSuperlatives([], -1));

        $suspended = $summary->withSuspended(true);
        self::assertTrue($suspended->suspended);
        self::assertSame(4, $suspended->count);
        self::assertSame(14, $suspended->sum);
        self::assertSame(3.5, $suspended->weighted);
        self::assertSame(1, $suspended->foreign);
        self::assertSame($summary->criteria, $suspended->criteria);
        self::assertSame($summary->superlatives, $suspended->superlatives);
        self::assertSame($subject, $suspended->subject);
    }

    public function testTheExceptions(): void
    {
        self::assertSame('x', InvalidReview::because('x')->getMessage());
        self::assertSame('y', ReviewRefused::because('y')->getMessage());
        $previous = new RuntimeException('unique');
        $duplicate = DuplicateReview::of('game:catan', $previous);
        self::assertSame('The member already reviewed game:catan.', $duplicate->getMessage());
        self::assertSame($previous, $duplicate->getPrevious());
        self::assertSame(0, $duplicate->getCode());
    }

    public function testASummaryStartsEmptyAndNotSuspended(): void
    {
        $summary = new RatingSummary(SubjectRef::of('game', 'catan'));

        self::assertSame([0, 0, 0, false], [$summary->count, $summary->sum, $summary->foreign, $summary->suspended]);
    }

    public function testTheSuperlativesOfAReviewAreEachOnceInTheOrderGiven(): void
    {
        self::assertSame(['quick', 'family'], new ReviewContent(3, [], null, null, ['quick', 'quick', 'family'])->superlatives);
    }
}
