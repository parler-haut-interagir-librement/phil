<?php

declare(strict_types=1);

namespace Phil\FaqBundle\Tests\Unit\Infrastructure;

use DateTimeImmutable;
use DateTimeZone;
use Doctrine\ORM\EntityManager;
use Override;
use Phil\FaqBundle\Application\FaqEditor;
use Phil\FaqBundle\Domain\Model\Question;
use Phil\FaqBundle\Domain\Model\Section;
use Phil\FaqBundle\Domain\Model\UnansweredSearch;
use Phil\FaqBundle\Domain\ValueObject\Locale;
use Phil\FaqBundle\Domain\ValueObject\VoteCount;
use Phil\FaqBundle\Infrastructure\Doctrine\DbalSpentVoteTokens;
use Phil\FaqBundle\Infrastructure\Doctrine\DbalUnansweredSearches;
use Phil\FaqBundle\Infrastructure\Doctrine\DoctrineFaqRepository;
use Phil\FaqBundle\Tests\Fixtures\Database;
use Phil\FaqBundle\Tests\Fixtures\Faq;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\Clock\MockClock;

/**
 * The tables of the bundle on one database: the questions in order with their translations, the counts of votes, the
 * searches without answer, the spent tokens, and the moments kept in UTC.
 */
abstract class RepositoryTestCase extends TestCase
{
    private ?EntityManager $entityManager = null;

    #[Override]
    protected function tearDown(): void
    {
        $this->entityManager?->getConnection()->close();
    }

    public function testTheQuestionsComeInTheOrderOfTheirSectionsAndPlaces(): void
    {
        [$repository, $editor] = $this->store();
        $en = Locale::fromString('en');
        $second = $editor->addSection($en, 'Second');
        $first = $editor->addSection($en, 'First');
        $editor->placeSection($first->id, $second->id);
        $b = $editor->addQuestion($first->id, $en, 'B?', 'b');
        $a = $editor->addQuestion($first->id, $en, 'A?', 'a', published: false);
        $c = $editor->addQuestion($second->id, $en, 'C?', 'c');
        $editor->placeQuestion($a->id, $first->id, $b->id);
        $this->entityManager()->clear();

        self::assertSame(['first', 'second'], array_map(static fn (Section $section): string => $section->key(), $repository->sections()));
        self::assertSame(['a', 'b', 'c'], array_map(static fn (Question $question): string => $question->anchor(), $repository->questions()));
        self::assertSame(['a', 'b'], array_map(static fn (Question $question): string => $question->anchor(), $repository->questionsOf($repository->sectionByKey('first') ?? throw new RuntimeException())));
        self::assertSame([$b->id, $c->id], array_keys($repository->questionTranslations(true)));
        self::assertCount(3, $repository->questionTranslations(false));
        self::assertSame('First', $repository->sectionTranslations()[$first->id]['en']->title());
        self::assertSame('c', $repository->questionByAnchor('c')?->anchor());
        self::assertNull($repository->questionByAnchor('d'));
        self::assertNull($repository->question('missing'));
        self::assertNull($repository->section('missing'));
        self::assertNull($repository->sectionByKey('missing'));
    }

    public function testTheMomentOfAChangeIsKeptInUtc(): void
    {
        [$repository, $editor] = $this->store(new MockClock('2026-11-21 05:30:00', 'America/Montreal'));
        $en = Locale::fromString('en');
        $section = $editor->addSection($en, 'Orders');
        $question = $editor->addQuestion($section->id, $en, 'Q?', 'a');
        $this->entityManager()->clear();

        $translation = $repository->questionTranslation($repository->question($question->id) ?? throw new RuntimeException(), 'en');
        self::assertSame('2026-11-21T10:30:00+00:00', $translation?->updatedAt()->setTimezone(new DateTimeZone('UTC'))->format(\DATE_ATOM));
    }

    public function testRemovingASectionRemovesItsQuestionsAndTranslations(): void
    {
        [$repository, $editor] = $this->store();
        $en = Locale::fromString('en');
        $section = $editor->addSection($en, 'Orders');
        $editor->retitleSection($section->id, Locale::fromString('fr'), 'Commandes');
        $editor->addQuestion($section->id, $en, 'Q?', 'a');

        $kept = $editor->addSection($en, 'Kept');
        $editor->addQuestion($kept->id, $en, 'K?', 'k');

        $editor->removeSection($section->id);
        $this->entityManager()->clear();

        self::assertSame(['kept'], array_map(static fn (Section $one): string => $one->key(), $repository->sections()));
        self::assertSame(['k'], array_map(static fn (Question $one): string => $one->anchor(), $repository->questions()));
        self::assertSame([$kept->id], array_keys($repository->sectionTranslations()));
        self::assertCount(1, $repository->questionTranslations(false));
    }

    public function testAVoteAddsToTheTotalsInTheTable(): void
    {
        [$repository, $editor] = $this->store();
        $section = $editor->addSection(Locale::fromString('en'), 'Orders');
        $question = $editor->addQuestion($section->id, Locale::fromString('en'), 'Q?', 'a');

        self::assertEquals(new VoteCount(1, 0), $repository->countVote($question->id, true));
        self::assertEquals(new VoteCount(2, 0), $repository->countVote($question->id, true));
        self::assertEquals(new VoteCount(2, 1), $repository->countVote($question->id, false));
        self::assertNull($repository->countVote('missing', true));

        $this->entityManager()->clear();
        $read = $repository->question($question->id);
        self::assertSame([2, 1], [$read?->helpfulYes(), $read?->helpfulNo()]);
    }

    public function testATransactionKeepsAllOrNothing(): void
    {
        [$repository, $editor] = $this->store();

        self::assertSame(3, $repository->transactional(static fn (): int => 3));

        try {
            $repository->transactional(static function () use ($editor): never {
                $editor->addSection(Locale::fromString('en'), 'Orders');

                throw new RuntimeException('stop');
            });
        } catch (RuntimeException $runtimeException) {
            self::assertSame('stop', $runtimeException->getMessage());
        }

        self::assertSame('0', $this->scalar($this->entityManager()->getConnection()->fetchOne('SELECT COUNT(*) FROM phil_faq_section')));
    }

    public function testASearchWithoutAnswerIsCountedOnce(): void
    {
        $searches = new DbalUnansweredSearches($this->entityManager()->getConnection());
        $searches->record('fr', 'livraison', new DateTimeImmutable('2026-11-20'));
        $searches->record('fr', 'livraison', new DateTimeImmutable('2026-11-21'));
        $searches->record('fr', 'retour', new DateTimeImmutable('2026-11-19'));
        $searches->record('en', 'delivery', new DateTimeImmutable('2026-11-21'));

        $most = $searches->mostFrequent(10);
        self::assertSame([['fr', 'livraison', 2, '2026-11-21'], ['en', 'delivery', 1, '2026-11-21'], ['fr', 'retour', 1, '2026-11-19']], array_map(static fn (UnansweredSearch $one): array => [$one->locale, $one->query, $one->searches, $one->lastSearchedOn->format('Y-m-d')], $most));
        self::assertSame(['livraison'], array_map(static fn (UnansweredSearch $one): string => $one->query, $searches->mostFrequent(1, 'fr')));
        self::assertSame(['delivery'], array_map(static fn (UnansweredSearch $one): string => $one->query, $searches->mostFrequent(5, 'en')));

        $searches->forget('fr', 'livraison');
        self::assertSame(['retour'], array_map(static fn (UnansweredSearch $one): string => $one->query, $searches->mostFrequent(5, 'fr')));
    }

    public function testATokenIsSpentOnceAndForgottenOnceExpired(): void
    {
        $spent = new DbalSpentVoteTokens($this->entityManager()->getConnection());

        self::assertTrue($spent->spend('a', new DateTimeImmutable('@2000')));
        self::assertFalse($spent->spend('a', new DateTimeImmutable('@2000')));
        self::assertTrue($spent->spend('b', new DateTimeImmutable('@3000')));

        self::assertSame(0, $spent->purge(new DateTimeImmutable('@2000')));
        self::assertSame(1, $spent->purge(new DateTimeImmutable('@2001')));
        self::assertTrue($spent->spend('a', new DateTimeImmutable('@4000')));
        self::assertFalse($spent->spend('b', new DateTimeImmutable('@4000')));
    }

    private function scalar(mixed $value): string
    {
        return \is_scalar($value) ? (string) $value : '';
    }

    /**
     * The URL of the database.
     */
    abstract protected function url(): string;

    /**
     * @return array{DoctrineFaqRepository, FaqEditor}
     */
    private function store(?MockClock $clock = null): array
    {
        return [new DoctrineFaqRepository($this->entityManager()), Faq::editor($this->entityManager(), $clock ?? new MockClock('2026-11-21 10:00:00 UTC'))];
    }

    private function entityManager(): EntityManager
    {
        return $this->entityManager ??= Database::entityManager($this->url());
    }

    public function testEachTranslationIsReadForItsOwnSectionOrQuestion(): void
    {
        [$repository, $editor] = $this->store();
        $en = Locale::fromString('en');
        $first = $editor->addSection($en, 'First');
        $second = $editor->addSection($en, 'Second');
        $a = $editor->addQuestion($first->id, $en, 'A?', 'a');
        $b = $editor->addQuestion($second->id, $en, 'B?', 'b');
        $this->entityManager()->clear();

        self::assertSame('Second', $repository->sectionTranslation($repository->section($second->id) ?? throw new RuntimeException(), 'en')?->title());
        self::assertSame('B?', $repository->questionTranslation($repository->question($b->id) ?? throw new RuntimeException(), 'en')?->text());

        $editor->removeQuestion($a->id);
        self::assertSame('1', $this->scalar($this->entityManager()->getConnection()->fetchOne('SELECT COUNT(*) FROM phil_faq_question_translation')));
    }

    public function testADayIsReadBackAtMidnight(): void
    {
        $store = new DbalUnansweredSearches($this->entityManager()->getConnection());
        $store->record('en', 'abc', new DateTimeImmutable('2026-11-22 15:45:00 UTC'));

        self::assertSame('2026-11-22 00:00:00', $store->mostFrequent(1)[0]->lastSearchedOn->format('Y-m-d H:i:s'));
    }
}
