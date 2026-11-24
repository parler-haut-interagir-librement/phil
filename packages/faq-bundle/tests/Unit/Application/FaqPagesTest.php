<?php

declare(strict_types=1);

namespace Phil\FaqBundle\Tests\Unit\Application;

use Doctrine\ORM\EntityManager;
use Override;
use Phil\FaqBundle\Application\FaqEditor;
use Phil\FaqBundle\Application\FaqPages;
use Phil\FaqBundle\Application\QueryNormalizer;
use Phil\FaqBundle\Application\View\FaqPage;
use Phil\FaqBundle\Application\View\QuestionView;
use Phil\FaqBundle\Application\View\SectionView;
use Phil\FaqBundle\Domain\ValueObject\Locale;
use Phil\FaqBundle\Infrastructure\Doctrine\DoctrineFaqRepository;
use Phil\FaqBundle\Tests\Fixtures\Database;
use Phil\FaqBundle\Tests\Fixtures\Faq;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\Clock\MockClock;

#[CoversClass(FaqPages::class)]
#[CoversClass(FaqPage::class)]
#[CoversClass(SectionView::class)]
#[CoversClass(QuestionView::class)]
final class FaqPagesTest extends TestCase
{
    private EntityManager $entityManager;

    private DoctrineFaqRepository $repository;

    private FaqEditor $editor;

    #[Override]
    protected function setUp(): void
    {
        $this->entityManager = Database::entityManager();
        $this->repository = new DoctrineFaqRepository($this->entityManager);
        $this->editor = Faq::editor($this->entityManager, new MockClock('2026-11-21 10:00:00 UTC'));
        $en = Locale::fromString('en');
        $fr = Locale::fromString('fr');

        $orders = $this->editor->addSection($en, 'Orders', 'orders');
        $this->editor->retitleSection($orders->id, $fr, 'Commandes');
        $pay = $this->editor->addQuestion($orders->id, $en, 'How do I pay?', 'By **card** or transfer.');
        $this->editor->rewriteQuestion($pay->id, $fr, 'Comment payer ?', 'Par **carte** ou virement.', published: true);
        $this->editor->addQuestion($orders->id, $en, 'Where is my order?', 'In the *Café* of the post office.');
        $this->editor->addQuestion($orders->id, $en, 'Draft?', 'Hidden.', published: false);

        $returns = $this->editor->addSection($en, 'Returns', 'returns');
        $this->editor->addQuestion($returns->id, $en, 'Can I return a game?', 'Within 30 days.');

        $this->editor->addSection($en, 'Empty', 'empty');
    }

    public function testThePageHoldsThePublishedQuestionsInOrder(): void
    {
        $page = $this->pages()->page(Locale::fromString('en'));

        self::assertSame('en', $page->locale);
        self::assertSame(['orders' => 'Orders', 'returns' => 'Returns'], array_column(array_map(static fn (SectionView $one): array => [$one->key, $one->title], $page->sections), 1, 0));
        self::assertSame(['how-do-i-pay', 'where-is-my-order', 'can-i-return-a-game'], array_map(static fn (QuestionView $one): string => $one->anchor, $page->questions()));
        self::assertSame([3, 3, false, false, '', ''], [$page->total, $page->found(), $page->isSearch(), $page->hasNoResult(), $page->query, $page->terms]);

        $pay = $page->questions()[0];
        self::assertSame(['en', 'How do I pay?', '<p>By <strong>card</strong> or transfer.</p>', 'how do i pay by card or transfer', '2026-11-21', 0, 0], [$pay->locale, $pay->question, $pay->answerHtml, $pay->searchText, $pay->updatedAt->format('Y-m-d'), $pay->helpfulYes, $pay->helpfulNo]);
    }

    public function testALocaleFallsBackToItsLanguageAndASectionToItsKey(): void
    {
        $page = $this->pages()->page(Locale::fromString('fr_CA'));

        self::assertSame(['orders' => 'Commandes'], array_column(array_map(static fn (SectionView $one): array => [$one->key, $one->title], $page->sections), 1, 0));
        self::assertSame(['fr', 'Comment payer ?'], [$page->questions()[0]->locale, $page->questions()[0]->question]);
        self::assertSame(1, $page->total);

        $this->editor->retitleSection($this->repository->sectionByKey('returns')->id ?? '', Locale::fromString('de'), 'Rückgaben');
        $question = $this->repository->questionByAnchor('can-i-return-a-game');
        $this->editor->rewriteQuestion($question->id ?? '', Locale::fromString('de'), 'Rückgabe?', 'Ja.', published: true);
        $german = $this->pages()->page(Locale::fromString('de'));
        self::assertSame(['Rückgaben'], array_map(static fn (SectionView $one): string => $one->title, $german->sections));

        $this->editor->rewriteQuestion($this->repository->questionByAnchor('how-do-i-pay')->id ?? '', Locale::fromString('es'), '¿Cómo pago?', 'Con tarjeta.', published: true);
        self::assertSame(['orders'], array_map(static fn (SectionView $one): string => $one->title, $this->pages()->page(Locale::fromString('es'))->sections));
    }

    public function testASearchKeepsTheQuestionsHoldingEveryWordAccentsAndCaseAside(): void
    {
        $page = $this->pages()->page(Locale::fromString('en'), '  CAFE   post! ');

        self::assertSame(['where-is-my-order'], array_map(static fn (QuestionView $one): string => $one->anchor, $page->questions()));
        self::assertSame(['CAFE   post!', 'cafe post', 3, 1, true, false], [$page->query, $page->terms, $page->total, $page->found(), $page->isSearch(), $page->hasNoResult()]);
        self::assertSame(['orders'], array_map(static fn (SectionView $one): string => $one->key, $page->sections));
    }

    public function testASearchThatFindsNothingSaysSo(): void
    {
        $page = $this->pages()->page(Locale::fromString('en'), 'refund policy');

        self::assertSame([[], 0, true, 3], [$page->sections, $page->found(), $page->hasNoResult(), $page->total]);
        self::assertSame([], $page->questions());
    }

    public function testASearchOfSymbolsShowsEverything(): void
    {
        $page = $this->pages()->page(Locale::fromString('en'), '?!');

        self::assertSame(['?!', '', false, 3], [$page->query, $page->terms, $page->hasNoResult(), $page->found()]);
    }

    public function testTheSearchAsTypedIsCut(): void
    {
        $page = $this->pages()->page(Locale::fromString('en'), str_repeat('é', 150));

        self::assertSame(100, mb_strlen($page->query));
        self::assertSame(str_repeat('e', 100), $page->terms);
    }

    public function testAPageHoldsAtMostTheConfiguredNumberOfQuestions(): void
    {
        $page = $this->pages(2)->page(Locale::fromString('en'));

        self::assertSame(['how-do-i-pay', 'where-is-my-order'], array_map(static fn (QuestionView $one): string => $one->anchor, $page->questions()));
        self::assertSame(2, $page->total);
        self::assertSame(3, $this->pages(3)->page(Locale::fromString('en'))->total);
    }

    public function testTheVotesAreShown(): void
    {
        $question = $this->repository->questionByAnchor('how-do-i-pay') ?? throw new RuntimeException();
        $this->repository->countVote($question->id, true);
        $this->repository->countVote($question->id, false);
        $this->repository->countVote($question->id, false);

        $this->entityManager->clear();

        $view = $this->pages()->page(Locale::fromString('en'))->questions()[0];
        self::assertSame([$question->id, 1, 2], [$view->id, $view->helpfulYes, $view->helpfulNo]);
    }

    /**
     * @param positive-int $max
     */
    private function pages(int $max = 500): FaqPages
    {
        return new FaqPages($this->repository, new QueryNormalizer(), $max);
    }

    public function testTheSearchAsTypedIsTrimmedBeforeAndAfterItsCut(): void
    {
        self::assertSame(str_repeat('a', 100), $this->pages()->page(Locale::fromString('en'), '   '.str_repeat('a', 100).'b')->query);
        self::assertSame(str_repeat('a', 99), $this->pages()->page(Locale::fromString('en'), str_repeat('a', 99).' b')->query);
    }

    public function testAWordDoesNotRunFromTheQuestionIntoItsAnswer(): void
    {
        $gifts = $this->editor->addSection(Locale::fromString('en'), 'Gifts', 'gifts');
        $this->editor->addQuestion($gifts->id, Locale::fromString('en'), 'Gift cards', 'Wrap them.');

        self::assertSame([], $this->pages()->page(Locale::fromString('en'), 'cardswrap')->questions());
        self::assertCount(1, $this->pages()->page(Locale::fromString('en'), 'cards wrap')->questions());
    }

    public function testAPageHoldsFiveHundredQuestionsByDefault(): void
    {
        self::assertSame(500, FaqPages::DEFAULT_MAX_QUESTIONS);
    }
}
