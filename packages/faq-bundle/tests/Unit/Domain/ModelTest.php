<?php

declare(strict_types=1);

namespace Phil\FaqBundle\Tests\Unit\Domain;

use DateTimeImmutable;
use Phil\FaqBundle\Domain\Exception\InvalidFaq;
use Phil\FaqBundle\Domain\Model\Question;
use Phil\FaqBundle\Domain\Model\QuestionTranslation;
use Phil\FaqBundle\Domain\Model\Section;
use Phil\FaqBundle\Domain\Model\SectionTranslation;
use Phil\FaqBundle\Domain\Model\SpentVoteToken;
use Phil\FaqBundle\Domain\Model\UnansweredSearch;
use Phil\FaqBundle\Domain\ValueObject\Locale;
use Phil\FaqBundle\Domain\ValueObject\VoteCount;
use Phil\Markup\MarkedUpText;
use Phil\Slug\Slug;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(Section::class)]
#[CoversClass(SectionTranslation::class)]
#[CoversClass(Question::class)]
#[CoversClass(QuestionTranslation::class)]
#[CoversClass(UnansweredSearch::class)]
#[CoversClass(SpentVoteToken::class)]
#[CoversClass(VoteCount::class)]
final class ModelTest extends TestCase
{
    public function testASectionKeepsItsKeyAndMoves(): void
    {
        $section = Section::create('s1', Slug::fromString('orders'), 0);
        $section->moveTo(3);

        self::assertSame(['s1', 'orders', 3], [$section->id, $section->key(), $section->position()]);

        $section->rekey(Slug::fromString('payments'));
        self::assertSame('payments', $section->key());
    }

    public function testASectionKeyHasAMaximum(): void
    {
        Section::create('s1', Slug::fromString(str_repeat('a', 100)), 0);

        $this->expectExceptionObject(InvalidFaq::because('The key of a section is 100 characters or fewer.'));

        Section::create('s1', Slug::fromString(str_repeat('a', 101)), 0);
    }

    public function testASectionCannotGetALongerKey(): void
    {
        $section = Section::create('s1', Slug::fromString('orders'), 0);

        $this->expectException(InvalidFaq::class);

        $section->rekey(Slug::fromString(str_repeat('a', 101)));
    }

    public function testAPositionIsNotNegative(): void
    {
        $section = Section::create('s1', Slug::fromString('orders'), 0);

        $this->expectExceptionObject(InvalidFaq::because('A position is zero or more.'));

        $section->moveTo(-1);
    }

    public function testASectionIsCreatedAtANonNegativePosition(): void
    {
        $this->expectException(InvalidFaq::class);

        Section::create('s1', Slug::fromString('orders'), -1);
    }

    public function testASectionTitleIsTrimmedAndBounded(): void
    {
        $section = Section::create('s1', Slug::fromString('orders'), 0);
        $title = SectionTranslation::of('t1', $section, Locale::fromString('fr-CA'), '  Commandes ');

        self::assertSame(['Commandes', 'fr_CA', $section], [$title->title(), $title->locale, $title->section]);

        $title->retitle(str_repeat('é', 150));
        self::assertSame(str_repeat('é', 150), $title->title());

        $this->expectExceptionObject(InvalidFaq::because('The title of a section is 1 to 150 characters.'));

        $title->retitle(str_repeat('é', 151));
    }

    public function testASectionTitleIsNotBlank(): void
    {
        $this->expectException(InvalidFaq::class);

        SectionTranslation::of('t1', Section::create('s1', Slug::fromString('orders'), 0), Locale::fromString('en'), '   ');
    }

    public function testAQuestionKeepsItsAnchorAndCountsStartAtZero(): void
    {
        $orders = Section::create('s1', Slug::fromString('orders'), 0);
        $payments = Section::create('s2', Slug::fromString('payments'), 1);
        $question = Question::create('q1', $orders, Slug::fromString('how-do-i-pay'), 2);

        self::assertSame(['how-do-i-pay', $orders, 2, 0, 0], [$question->anchor(), $question->section(), $question->position(), $question->helpfulYes(), $question->helpfulNo()]);

        $question->moveTo($payments, 0);
        self::assertSame([$payments, 0], [$question->section(), $question->position()]);

        $question->reanchor(Slug::fromString('paying'));
        self::assertSame('paying', $question->anchor());
    }

    public function testAQuestionAnchorHasAMaximum(): void
    {
        $section = Section::create('s1', Slug::fromString('orders'), 0);
        Question::create('q1', $section, Slug::fromString(str_repeat('a', 100)), 0);

        $this->expectExceptionObject(InvalidFaq::because('The anchor of a question is 100 characters or fewer.'));

        Question::create('q1', $section, Slug::fromString(str_repeat('a', 101)), 0);
    }

    public function testAQuestionCannotGetALongerAnchorNorANegativePlace(): void
    {
        $section = Section::create('s1', Slug::fromString('orders'), 0);
        $question = Question::create('q1', $section, Slug::fromString('a'), 0);

        try {
            $question->reanchor(Slug::fromString(str_repeat('a', 101)));
            self::fail('A longer anchor is refused.');
        } catch (InvalidFaq) {
        }

        $this->expectException(InvalidFaq::class);

        $question->moveTo($section, -1);
    }

    public function testAQuestionIsCreatedAtANonNegativePosition(): void
    {
        $this->expectException(InvalidFaq::class);

        Question::create('q1', Section::create('s1', Slug::fromString('orders'), 0), Slug::fromString('a'), -1);
    }

    public function testATranslationKeepsTheAnswerAndTheSecondOfItsChange(): void
    {
        $now = new DateTimeImmutable('2026-11-21 10:00:00.750 UTC');
        $translation = $this->translation($now);

        self::assertSame(['How do I pay?', 'en', true, '2026-11-21 10:00:00.000000'], [$translation->text(), $translation->locale, $translation->isPublished(), $translation->updatedAt()->format('Y-m-d H:i:s.u')]);
        self::assertEquals(new MarkedUpText('By **card**.', 'markdown', '<p>By <strong>card</strong>.</p>'), $translation->answer());

        $later = new DateTimeImmutable('2026-11-22 08:00:00.250 UTC');
        $translation->rewrite(' Paying? ', new MarkedUpText('Cash', 'text', '<p>Cash</p>'), $later);
        self::assertSame(['Paying?', 'Cash', 'text', '<p>Cash</p>', '2026-11-22 08:00:00.000000'], [$translation->text(), $translation->answer()->source, $translation->answer()->format, $translation->answer()->html, $translation->updatedAt()->format('Y-m-d H:i:s.u')]);
    }

    public function testPublishingChangesTheMomentOnlyWhenTheStateChanges(): void
    {
        $translation = $this->translation(new DateTimeImmutable('2026-11-21 10:00:00 UTC'));

        $translation->publish(true, new DateTimeImmutable('2026-11-23 10:00:00.5 UTC'));
        self::assertSame('2026-11-21', $translation->updatedAt()->format('Y-m-d'));

        $translation->publish(false, new DateTimeImmutable('2026-11-23 10:00:00.5 UTC'));
        self::assertFalse($translation->isPublished());
        self::assertSame('2026-11-23 10:00:00.000000', $translation->updatedAt()->format('Y-m-d H:i:s.u'));
    }

    public function testAWordingIsBounded(): void
    {
        $translation = $this->translation(new DateTimeImmutable());
        $translation->rewrite(str_repeat('é', 300), new MarkedUpText('a', 'text', '<p>a</p>'), new DateTimeImmutable());
        self::assertSame(300, mb_strlen($translation->text()));

        $this->expectExceptionObject(InvalidFaq::because('The wording of a question is 1 to 300 characters.'));

        $translation->rewrite(str_repeat('é', 301), new MarkedUpText('a', 'text', '<p>a</p>'), new DateTimeImmutable());
    }

    public function testABlankWordingIsRefused(): void
    {
        $this->expectException(InvalidFaq::class);

        $this->translation(new DateTimeImmutable())->rewrite(' ', new MarkedUpText('a', 'text', '<p>a</p>'), new DateTimeImmutable());
    }

    /**
     * @return iterable<string, array{MarkedUpText}>
     */
    public static function emptyAnswers(): iterable
    {
        yield 'a blank source' => [new MarkedUpText('  ', 'text', '<p>a</p>')];
        yield 'a blank HTML' => [new MarkedUpText('a', 'text', ' ')];
        yield 'a source too long' => [new MarkedUpText(str_repeat('a', 60_001), 'text', '<p>a</p>')];
    }

    #[DataProvider('emptyAnswers')]
    public function testAnAnswerShowsSomeTextAndIsBounded(MarkedUpText $answer): void
    {
        $this->expectExceptionObject(InvalidFaq::because('The answer of a question is 1 to 60000 bytes, and shows some text.'));

        $this->translation(new DateTimeImmutable())->rewrite('Q?', $answer, new DateTimeImmutable());
    }

    public function testTheLongestAnswerIsKept(): void
    {
        $translation = $this->translation(new DateTimeImmutable());
        $translation->rewrite('Q?', new MarkedUpText(str_repeat('a', 60_000), 'text', '<p>a</p>'), new DateTimeImmutable());

        self::assertSame(60_000, \strlen($translation->answer()->source));
    }

    public function testAnUnansweredSearchIsKnownByTheHashOfItsLanguageAndWords(): void
    {
        $search = UnansweredSearch::restored('fr', 'livraison', 4, new DateTimeImmutable('2026-11-21'));

        self::assertSame([hash('sha256', "fr\0livraison"), 'fr', 'livraison', 4, '2026-11-21'], [$search->id, $search->locale, $search->query, $search->searches, $search->lastSearchedOn->format('Y-m-d')]);
        self::assertNotSame(UnansweredSearch::idOf('en', 'livraison'), $search->id);
    }

    public function testASpentTokenAndAVoteCountHoldTheirValues(): void
    {
        $spent = new SpentVoteToken('abc', new DateTimeImmutable('@100'));
        $count = new VoteCount(3, 1);

        self::assertSame(['abc', 100, 3, 1], [$spent->id, $spent->expiresAt->getTimestamp(), $count->yes, $count->no]);
    }

    private function translation(DateTimeImmutable $now): QuestionTranslation
    {
        $question = Question::create('q1', Section::create('s1', Slug::fromString('orders'), 0), Slug::fromString('how-do-i-pay'), 0);

        return QuestionTranslation::of('t1', $question, Locale::fromString('en'), ' How do I pay? ', new MarkedUpText('By **card**.', 'markdown', '<p>By <strong>card</strong>.</p>'), true, $now);
    }
}
