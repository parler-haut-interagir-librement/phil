<?php

declare(strict_types=1);

namespace Phil\FaqBundle\Tests\Unit\Application;

use Override;
use Phil\FaqBundle\Application\FaqEditor;
use Phil\FaqBundle\Application\FaqTransfer;
use Phil\FaqBundle\Domain\Exception\InvalidFaq;
use Phil\FaqBundle\Domain\Model\Section;
use Phil\FaqBundle\Domain\ValueObject\Locale;
use Phil\FaqBundle\Infrastructure\Doctrine\DoctrineFaqRepository;
use Phil\FaqBundle\Tests\Fixtures\Database;
use Phil\FaqBundle\Tests\Fixtures\Faq;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

#[CoversClass(FaqTransfer::class)]
final class FaqTransferTest extends TestCase
{
    private DoctrineFaqRepository $repository;

    private FaqEditor $editor;

    private FaqTransfer $transfer;

    #[Override]
    protected function setUp(): void
    {
        $entityManager = Database::entityManager();
        $this->repository = new DoctrineFaqRepository($entityManager);
        $this->editor = Faq::editor($entityManager, new MockClock('2026-11-21 10:00:00 UTC'));
        $this->transfer = new FaqTransfer($this->repository, $this->editor);
    }

    public function testAnExportHoldsEverySectionAndQuestionInOrder(): void
    {
        $en = Locale::fromString('en');
        $orders = $this->editor->addSection($en, 'Orders', 'orders');
        $this->editor->retitleSection($orders->id, Locale::fromString('fr'), 'Commandes');
        $pay = $this->editor->addQuestion($orders->id, $en, 'How do I pay?', 'By **card**.');
        $this->editor->rewriteQuestion($pay->id, Locale::fromString('fr'), 'Comment payer ?', 'Par carte.', published: false);
        $this->editor->addSection($en, 'Empty', 'empty');

        self::assertSame(['sections' => [
            ['key' => 'orders', 'titles' => ['en' => 'Orders', 'fr' => 'Commandes'], 'questions' => [
                ['anchor' => 'how-do-i-pay', 'translations' => [
                    'en' => ['question' => 'How do I pay?', 'answer' => 'By **card**.', 'format' => 'markdown', 'published' => true],
                    'fr' => ['question' => 'Comment payer ?', 'answer' => 'Par carte.', 'format' => 'markdown', 'published' => false],
                ]],
            ]],
            ['key' => 'empty', 'titles' => ['en' => 'Empty'], 'questions' => []],
        ]], $this->transfer->export());
    }

    public function testAnImportChangesWhatExistsAddsTheRestAndTakesTheOrderOfTheFile(): void
    {
        $en = Locale::fromString('en');
        $kept = $this->editor->addSection($en, 'Kept', 'kept');
        $orders = $this->editor->addSection($en, 'Old orders', 'orders');
        $this->editor->addQuestion($orders->id, $en, 'Old?', 'old', anchor: 'pay');
        $this->editor->addQuestion($orders->id, $en, 'Other?', 'other', anchor: 'other');

        $counts = $this->transfer->import(['sections' => [
            ['key' => 'orders', 'titles' => ['en' => 'Orders', 'fr' => 'Commandes'], 'questions' => [
                ['anchor' => 'new', 'translations' => ['en' => ['question' => 'New?', 'answer' => '<p>New</p>', 'format' => 'html', 'published' => false], 'fr' => ['question' => 'Nouveau ?', 'answer' => 'Nouveau']]],
                ['anchor' => 'pay', 'translations' => ['en' => ['question' => 'How do I pay?', 'answer' => 'Card']]],
            ]],
            ['key' => 'returns', 'titles' => ['fr' => 'Retours'], 'questions' => []],
        ]]);

        self::assertSame(['sections' => 2, 'questions' => 2], $counts);
        $export = $this->transfer->export()['sections'];
        self::assertSame(['kept', 'orders', 'returns'], array_column($export, 'key'));
        self::assertSame($kept->id, $this->repository->sectionByKey('kept')?->id);
        self::assertSame(['en' => 'Orders', 'fr' => 'Commandes'], $export[1]['titles']);
        self::assertSame(['other', 'new', 'pay'], array_column($export[1]['questions'], 'anchor'));
        self::assertSame(['question' => 'New?', 'answer' => '<p>New</p>', 'format' => 'html', 'published' => false], $export[1]['questions'][1]['translations']['en']);
        self::assertSame(['question' => 'Nouveau ?', 'answer' => 'Nouveau', 'format' => 'markdown', 'published' => true], $export[1]['questions'][1]['translations']['fr']);
        self::assertSame(['question' => 'How do I pay?', 'answer' => 'Card', 'format' => 'markdown', 'published' => true], $export[1]['questions'][2]['translations']['en']);
    }

    public function testAnExportImportsBackUnchanged(): void
    {
        $en = Locale::fromString('en');
        $section = $this->editor->addSection($en, 'Orders');
        $this->editor->addQuestion($section->id, $en, 'Q?', 'a');
        $export = $this->transfer->export();

        $this->transfer->import($export);

        self::assertSame($export, $this->transfer->export());
    }

    public function testAValueTheModelRefusesUndoesTheWholeImport(): void
    {
        try {
            $this->transfer->import(['sections' => [
                ['key' => 'orders', 'titles' => ['en' => 'Orders'], 'questions' => []],
                ['key' => 'returns', 'titles' => ['en' => str_repeat('a', 151)], 'questions' => []],
            ]]);
            self::fail('A title too long is refused.');
        } catch (InvalidFaq $invalidFaq) {
            self::assertSame('The title of a section is 1 to 150 characters.', $invalidFaq->getMessage());
        }

        self::assertSame([], $this->repository->sections());
    }

    /**
     * @return iterable<string, array{mixed, string}>
     */
    public static function shapes(): iterable
    {
        yield 'not a map' => ['text', 'sections is a list.'];
        yield 'no sections' => [[], 'sections is a list.'];
        yield 'sections as a map' => [['sections' => ['a' => []]], 'sections is a list.'];
        yield 'a section not a map' => [['sections' => ['x']], 'sections[0] is a map.'];
        yield 'questions not a list' => [['sections' => [['questions' => 'x']]], 'sections[0].questions is a list.'];
        yield 'a question not a map' => [['sections' => [['questions' => [1]]]], 'sections[0].questions[0] is a map.'];
        yield 'no translations' => [['sections' => [['questions' => [[]]]]], 'sections[0].questions[0].translations is a map.'];
        yield 'empty translations' => [['sections' => [['questions' => [['anchor' => 'a', 'translations' => []]]]]], 'sections[0].questions[0].translations holds one language at least.'];
        yield 'a translation not a map' => [['sections' => [['questions' => [['translations' => ['en' => 'x']]]]]], 'sections[0].questions[0].translations.en is a map.'];
        yield 'a wrong locale' => [['sections' => [['questions' => [['translations' => ['EN' => []]]]]]], 'sections[0].questions[0].translations.EN is not under a locale.'];
        yield 'a numbered locale' => [['sections' => [['questions' => [['translations' => [[]]]]]]], 'sections[0].questions[0].translations.0 is not under a locale.'];
        yield 'no wording' => [['sections' => [['questions' => [['translations' => ['en' => []]]]]]], 'sections[0].questions[0].translations.en.question is a text.'];
        yield 'no answer' => [['sections' => [['questions' => [['translations' => ['en' => ['question' => 'Q']]]]]]], 'sections[0].questions[0].translations.en.answer is a text.'];
        yield 'a format not a text' => [['sections' => [['questions' => [['translations' => ['en' => ['question' => 'Q', 'answer' => 'A', 'format' => 1]]]]]]], 'sections[0].questions[0].translations.en.format is a text.'];
        yield 'published not a flag' => [['sections' => [['questions' => [['translations' => ['en' => ['question' => 'Q', 'answer' => 'A', 'published' => 'yes']]]]]]], 'sections[0].questions[0].translations.en.published is true or false.'];
        yield 'no anchor' => [['sections' => [['questions' => [['translations' => ['en' => ['question' => 'Q', 'answer' => 'A']]]]]]], 'sections[0].questions[0].anchor is a text.'];
        yield 'no titles' => [['sections' => [[]]], 'sections[0].titles is a map.'];
        yield 'a title not a text' => [['sections' => [['titles' => ['en' => 3]]]], 'sections[0].titles.en is a text.'];
        yield 'a title under no locale' => [['sections' => [['titles' => ['english' => 'x']]]], 'sections[0].titles.english is not under a locale.'];
        yield 'empty titles' => [['sections' => [['key' => 'a', 'titles' => []]]], 'sections[0].titles holds one language at least.'];
        yield 'no key' => [['sections' => [['titles' => ['en' => 'x']]]], 'sections[0].key is a text.'];
        yield 'the second section' => [['sections' => [['key' => 'a', 'titles' => ['en' => 'A']], ['key' => 3, 'titles' => ['en' => 'B']]]], 'sections[1].key is a text.'];
    }

    #[DataProvider('shapes')]
    public function testTheShapeIsCheckedBeforeAnyChange(mixed $data, string $message): void
    {
        try {
            $this->transfer->import($data);
            self::fail('The shape is refused.');
        } catch (InvalidFaq $invalidFaq) {
            self::assertSame($message, $invalidFaq->getMessage());
        }

        self::assertSame([], $this->repository->sections());
    }

    public function testAFileHoldsAtMostFiveThousandItems(): void
    {
        $questions = array_fill(0, 4_999, ['anchor' => 'a', 'translations' => ['en' => ['question' => 'Q', 'answer' => 'A']]]);
        $this->transfer->import(['sections' => []]);

        $this->expectExceptionObject(InvalidFaq::because('A file holds 5000 sections and questions at most.'));

        $this->transfer->import(['sections' => [['key' => 'a', 'titles' => ['en' => 'A'], 'questions' => $questions], ['key' => 'b', 'titles' => ['en' => 'B']]]]);
    }

    public function testFiveThousandItemsAreRead(): void
    {
        $questions = array_fill(0, 4_999, ['anchor' => 'a', 'translations' => ['en' => ['question' => 'Q', 'answer' => 'A']]]);

        // The shape passes; the model then refuses the first title.
        $this->expectExceptionObject(InvalidFaq::because('The title of a section is 1 to 150 characters.'));

        $this->transfer->import(['sections' => [['key' => 'a', 'titles' => ['en' => str_repeat('a', 151)], 'questions' => $questions]]]);
    }

    public function testAnExportListsTheLanguagesInOrder(): void
    {
        $fr = Locale::fromString('fr');
        $orders = $this->editor->addSection($fr, 'Commandes', 'orders');
        $this->editor->retitleSection($orders->id, Locale::fromString('en'), 'Orders');
        $pay = $this->editor->addQuestion($orders->id, $fr, 'Comment payer ?', 'Par carte.');
        $this->editor->rewriteQuestion($pay->id, Locale::fromString('en'), 'How do I pay?', 'By card.');

        $section = $this->transfer->export()['sections'][0];

        self::assertSame(['en', 'fr'], array_keys($section['titles']));
        self::assertSame(['en', 'fr'], array_keys($section['questions'][0]['translations']));
    }

    public function testAnImportPlacesTheSectionsThatExistInTheOrderOfTheFile(): void
    {
        $en = Locale::fromString('en');
        $this->editor->addSection($en, 'Orders', 'orders');
        $this->editor->addSection($en, 'Returns', 'returns');

        $data = $this->transfer->export();
        $data['sections'] = array_reverse($data['sections']);

        $this->transfer->import($data);

        self::assertSame(['returns', 'orders'], array_map(static fn (Section $section): string => $section->key(), $this->repository->sections()));
    }
}
