<?php

declare(strict_types=1);

namespace Phil\FaqBundle\Tests\Unit\Application;

use Doctrine\ORM\EntityManager;
use Override;
use Phil\FaqBundle\Application\Exception\UnknownQuestion;
use Phil\FaqBundle\Application\Exception\UnknownSection;
use Phil\FaqBundle\Application\FaqEditor;
use Phil\FaqBundle\Domain\Exception\InvalidFaq;
use Phil\FaqBundle\Domain\Model\Question;
use Phil\FaqBundle\Domain\Model\Section;
use Phil\FaqBundle\Domain\ValueObject\Locale;
use Phil\FaqBundle\Infrastructure\Doctrine\DoctrineFaqRepository;
use Phil\FaqBundle\Tests\Fixtures\Database;
use Phil\FaqBundle\Tests\Fixtures\Faq;
use Phil\Markup\MarkupFormat;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\Clock\MockClock;

#[CoversClass(FaqEditor::class)]
#[CoversClass(UnknownQuestion::class)]
#[CoversClass(UnknownSection::class)]
final class FaqEditorTest extends TestCase
{
    private EntityManager $entityManager;

    private DoctrineFaqRepository $repository;

    private FaqEditor $editor;

    private MockClock $clock;

    private Locale $en;

    #[Override]
    protected function setUp(): void
    {
        $this->entityManager = Database::entityManager();
        $this->repository = new DoctrineFaqRepository($this->entityManager);
        $this->clock = new MockClock('2026-11-21 10:00:00 UTC');
        $this->editor = Faq::editor($this->entityManager, $this->clock);
        $this->en = Locale::fromString('en');
    }

    public function testAKeyIsMadeFromTheTitleAndNumberedWhenTaken(): void
    {
        $first = $this->editor->addSection($this->en, 'Orders & Payments');
        $second = $this->editor->addSection($this->en, 'Orders & payments!');
        $third = $this->editor->addSection($this->en, 'Orders & payments?');
        $named = $this->editor->addSection($this->en, 'Anything', 'shipping');

        self::assertSame(['orders-payments', 'orders-payments-2', 'orders-payments-3', 'shipping'], [$first->key(), $second->key(), $third->key(), $named->key()]);
        self::assertSame([0, 1, 2, 3], [$first->position(), $second->position(), $third->position(), $named->position()]);
        self::assertSame('Orders & Payments', $this->repository->sectionTranslation($first, 'en')?->title());
    }

    public function testAGivenKeyIsASlugNobodyHas(): void
    {
        $this->editor->addSection($this->en, 'Anything', 'shipping');

        try {
            $this->editor->addSection($this->en, 'Other', 'shipping');
            self::fail('A key is taken once.');
        } catch (InvalidFaq $invalidFaq) {
            self::assertSame('A section already has the key "shipping".', $invalidFaq->getMessage());
        }

        $this->expectExceptionObject(InvalidFaq::because('A key or an anchor is lowercase letters and digits, in words joined by hyphens.'));

        $this->editor->addSection($this->en, 'Other', 'Not A Slug');
    }

    public function testATitleWithoutLetterOrDigitMakesNoKey(): void
    {
        $this->expectExceptionObject(InvalidFaq::because('A wording or a title holds a letter or a digit.'));

        $this->editor->addSection($this->en, '?!');
    }

    public function testALongTitleMakesAKeyWithRoomForANumber(): void
    {
        $title = str_repeat('word ', 28);
        $first = $this->editor->addSection($this->en, $title);
        $second = $this->editor->addSection($this->en, $title);

        self::assertLessThanOrEqual(90, \strlen($first->key()));
        self::assertStringEndsNotWith('-', $first->key());
        self::assertSame($first->key().'-2', $second->key());
    }

    public function testWhenEveryNumberIsTakenTheKeyIsRefused(): void
    {
        for ($i = 1; $i <= 100; ++$i) {
            $this->editor->addSection($this->en, 'Same');
        }

        $this->expectExceptionObject(InvalidFaq::because('Every anchor from "same" to "same-100" is taken.'));

        $this->editor->addSection($this->en, 'Same');
    }

    public function testATitleIsAddedOrChangedAndTheKeyStays(): void
    {
        $section = $this->editor->addSection($this->en, 'Orders');
        $this->editor->retitleSection($section->id, Locale::fromString('fr'), 'Commandes');
        $this->editor->retitleSection($section->id, $this->en, 'Your orders');

        self::assertSame(['orders', 'Commandes', 'Your orders'], [$section->key(), $this->repository->sectionTranslation($section, 'fr')?->title(), $this->repository->sectionTranslation($section, 'en')?->title()]);
    }

    public function testSectionsArePlacedBeforeAnotherOrAtTheEnd(): void
    {
        [$a, $b, $c] = $this->sections('a', 'b', 'c');

        $this->editor->placeSection($c->id, $a->id);
        self::assertSame(['c', 'a', 'b'], $this->keys());

        $this->editor->placeSection($c->id);
        self::assertSame(['a', 'b', 'c'], $this->keys());

        $this->editor->placeSection($a->id, $c->id);
        self::assertSame(['b', 'a', 'c'], $this->keys());
        self::assertSame([0, 1, 2], array_map(static fn (Section $one): int => $one->position(), $this->repository->sections()));
        self::assertSame($b->id, $this->editor->section($b->id)->id);
    }

    public function testRemovingASectionRenumbersTheOthers(): void
    {
        [$a, , $c] = $this->sections('a', 'b', 'c');
        $this->editor->removeSection($a->id);

        self::assertSame(['b', 'c'], $this->keys());
        self::assertSame(1, $c->position());
    }

    public function testAnUnknownSectionIsNamed(): void
    {
        $this->expectExceptionObject(UnknownSection::named('nope'));

        $this->editor->placeSection('nope');
    }

    public function testAQuestionTakesItsAnchorFromItsFirstWordingAndKeepsIt(): void
    {
        [$section] = $this->sections('orders');
        $question = $this->editor->addQuestion($section->id, $this->en, 'How do I pay?', 'By **card**.');
        $again = $this->editor->addQuestion($section->id, $this->en, 'How do I pay', 'Twice.');

        $this->clock->sleep(60);
        $this->editor->rewriteQuestion($question->id, $this->en, 'Which payments do you take?', '<p>Cards</p>', MarkupFormat::Html);
        $translation = $this->repository->questionTranslation($question, 'en');

        self::assertSame(['how-do-i-pay', 'how-do-i-pay-2', 0, 1], [$question->anchor(), $again->anchor(), $question->position(), $again->position()]);
        self::assertSame(['Which payments do you take?', '<p>Cards</p>', 'html', true, '2026-11-21 10:01:00'], [$translation?->text(), $translation?->answer()->html, $translation?->answer()->format, $translation?->isPublished(), $translation?->updatedAt()->format('Y-m-d H:i:s')]);
    }

    public function testAnAnswerIsPurified(): void
    {
        [$section] = $this->sections('orders');
        $question = $this->editor->addQuestion($section->id, $this->en, 'Q?', "Hi <script>alert(1)</script>\n\n[link](javascript:alert(1)) *ok*", MarkupFormat::Markdown);
        $html = $this->repository->questionTranslation($question, 'en')?->answer()->html ?? '';

        self::assertStringNotContainsString('<script', $html);
        self::assertStringNotContainsString('javascript:', $html);
        self::assertStringContainsString('<em>ok</em>', $html);
    }

    public function testAnAnswerItsFormatCannotReadIsRefused(): void
    {
        [$section] = $this->sections('orders');

        try {
            $this->editor->addQuestion($section->id, $this->en, 'Q?', 'a', 'klingon');
            self::fail('An unknown format is refused.');
        } catch (InvalidFaq $invalidFaq) {
            self::assertStringStartsWith('The answer cannot be read in its format: ', $invalidFaq->getMessage());
            self::assertStringContainsString('klingon', $invalidFaq->getMessage());
        }
    }

    public function testAGivenAnchorIsASlugNobodyHas(): void
    {
        [$section] = $this->sections('orders');
        $this->editor->addQuestion($section->id, $this->en, 'Q?', 'a', anchor: 'pay');

        $this->expectExceptionObject(InvalidFaq::because('A question already has the anchor "pay".'));

        $this->editor->addQuestion($section->id, $this->en, 'Other?', 'b', anchor: 'pay');
    }

    public function testANewTranslationIsPublishedOnlyWhenToldAndAChangedOneKeepsItsState(): void
    {
        [$section] = $this->sections('orders');
        $question = $this->editor->addQuestion($section->id, $this->en, 'Q?', 'a', published: false);
        $fr = Locale::fromString('fr');

        $this->editor->rewriteQuestion($question->id, $fr, 'Q fr ?', 'a');
        self::assertFalse($this->repository->questionTranslation($question, 'fr')?->isPublished());

        $this->editor->rewriteQuestion($question->id, Locale::fromString('de'), 'Q de?', 'a', published: true);
        self::assertTrue($this->published($question, 'de'));

        $this->editor->rewriteQuestion($question->id, $this->en, 'Q2?', 'b');
        self::assertFalse($this->published($question, 'en'));

        $this->editor->rewriteQuestion($question->id, $this->en, 'Q3?', 'c', published: true);
        self::assertTrue($this->published($question, 'en'));

        $this->editor->publish($question->id, $this->en, false);
        self::assertFalse($this->published($question, 'en'));
    }

    public function testPublishingAMissingTranslationIsRefused(): void
    {
        [$section] = $this->sections('orders');
        $question = $this->editor->addQuestion($section->id, $this->en, 'Q?', 'a');

        $this->expectExceptionObject(UnknownQuestion::named($question->id.' (fr)'));

        $this->editor->publish($question->id, Locale::fromString('fr'), true);
    }

    public function testQuestionsArePlacedInTheirSectionOrMovedToAnother(): void
    {
        [$first, $second] = $this->sections('first', 'second');
        $a = $this->editor->addQuestion($first->id, $this->en, 'A?', 'a');
        $b = $this->editor->addQuestion($first->id, $this->en, 'B?', 'b');
        $c = $this->editor->addQuestion($first->id, $this->en, 'C?', 'c');
        $d = $this->editor->addQuestion($second->id, $this->en, 'D?', 'd');

        $this->editor->placeQuestion($c->id, $first->id, $a->id);
        self::assertSame(['c', 'a', 'b'], $this->anchors($first));

        $this->editor->placeQuestion($a->id, $second->id, $d->id);
        self::assertSame(['c', 'b'], $this->anchors($first));
        self::assertSame(['a', 'd'], $this->anchors($second));
        self::assertSame([0, 1], [$c->position(), $b->position()]);
        self::assertSame($second, $a->section());

        $this->editor->placeQuestion($c->id, $second->id);
        self::assertSame(['a', 'd', 'c'], $this->anchors($second));
        self::assertSame(0, $b->position());
    }

    public function testAQuestionIsPlacedBeforeAnotherOfTheSameSection(): void
    {
        [$first, $second] = $this->sections('first', 'second');
        $a = $this->editor->addQuestion($first->id, $this->en, 'A?', 'a');
        $d = $this->editor->addQuestion($second->id, $this->en, 'D?', 'd');

        $this->expectExceptionObject(InvalidFaq::because('A question is placed before another question of the same section.'));

        $this->editor->placeQuestion($a->id, $first->id, $d->id);
    }

    public function testRemovingAQuestionRenumbersItsSection(): void
    {
        [$section] = $this->sections('orders');
        $a = $this->editor->addQuestion($section->id, $this->en, 'A?', 'a');
        $b = $this->editor->addQuestion($section->id, $this->en, 'B?', 'b');

        $this->editor->removeQuestion($a->id);

        self::assertSame(['b'], $this->anchors($section));
        self::assertSame(0, $b->position());
        self::assertSame($b, $this->editor->question($b->id));
    }

    public function testAnUnknownQuestionIsNamed(): void
    {
        $this->expectExceptionObject(UnknownQuestion::named('nope'));

        $this->editor->removeQuestion('nope');
    }

    private function published(Question $question, string $locale): ?bool
    {
        return $this->repository->questionTranslation($question, $locale)?->isPublished();
    }

    /**
     * @return list<Section>
     */
    private function sections(string ...$keys): array
    {
        return array_map(fn (string $key): Section => $this->editor->addSection($this->en, ucfirst($key), $key), array_values($keys));
    }

    /**
     * @return list<string>
     */
    private function keys(): array
    {
        $this->entityManager->clear();

        return array_map(static fn (Section $one): string => $one->key(), $this->repository->sections());
    }

    /**
     * @return list<string>
     */
    private function anchors(Section $section): array
    {
        return array_map(static fn (Question $one): string => $one->anchor(), $this->repository->questionsOf($section));
    }

    public function testWhatTheEditorChangesReachesTheTables(): void
    {
        [$a, , $c] = $this->sections('a', 'b', 'c');
        $q1 = $this->editor->addQuestion($c->id, $this->en, 'One?', 'one');
        $q2 = $this->editor->addQuestion($c->id, $this->en, 'Two?', 'two');
        $connection = $this->entityManager->getConnection();

        $this->editor->removeSection($a->id);
        $this->entityManager->clear();
        self::assertSame(['b:0', 'c:1'], $connection->fetchFirstColumn("SELECT section_key || ':' || position FROM phil_faq_section ORDER BY position"));

        $this->editor->removeQuestion($q1->id);
        $this->entityManager->clear();
        self::assertSame(['two:0'], $connection->fetchFirstColumn("SELECT anchor || ':' || position FROM phil_faq_question ORDER BY position"));

        $this->editor->publish($q2->id, $this->en, false);
        $this->entityManager->clear();
        self::assertFalse($this->published($this->repository->question($q2->id) ?? throw new RuntimeException(), 'en'));
    }
}
