<?php

declare(strict_types=1);

namespace Phil\TranslationBundle\Tests\Unit\Application;

use Phil\TranslationBundle\Application\Exception\NotFound;
use Phil\TranslationBundle\Application\Glossaries;
use Phil\TranslationBundle\Application\Translations;
use Phil\TranslationBundle\Domain\Model\SegmentRecord;
use Phil\TranslationBundle\Infrastructure\Memory\InMemorySegmentRepository;
use Phil\TranslationBundle\Infrastructure\Memory\InMemoryTermRepository;
use Phil\TranslationBundle\Tests\Unit\Fixture\Policy;
use Phil\TranslationBundle\Tests\Unit\Fixture\RecordingSegments;
use Phil\TranslationBundle\Tests\Unit\Fixture\Sources;
use Phil\TranslationBundle\Tests\Unit\Fixture\Stores;
use Phil\TranslationMemory\Exception\InvalidLanguage;
use Phil\TranslationMemory\Exception\InvalidSegment;
use Phil\TranslationMemory\Exception\InvalidXliff;
use Phil\TranslationMemory\Glossary\Term;
use Phil\TranslationMemory\Glossary\TermHit;
use Phil\TranslationMemory\Glossary\TermIssue;
use Phil\TranslationMemory\LanguageTag;
use Phil\TranslationMemory\Memory\InMemoryStore;
use Phil\TranslationMemory\Memory\MemoryMatch;
use Phil\TranslationMemory\Memory\MemoryUnit;
use Phil\TranslationMemory\Memory\TranslationMemory;
use Phil\TranslationMemory\Segment;
use Phil\TranslationMemory\Segmentation\Granularity;
use Phil\TranslationMemory\Segmentation\UjoSegmenter;
use Phil\TranslationMemory\SegmentState;
use Phil\TranslationMemory\Xliff\XliffFile;
use Phil\TranslationMemory\Xliff\XliffReader;
use Phil\TranslationMemory\Xliff\XliffUnit;
use Phil\TranslationMemory\Xliff\XliffWriter;
use Phil\Ujo\Block;
use Phil\Ujo\Document;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

#[CoversClass(Translations::class)]
#[CoversClass(NotFound::class)]
final class TranslationsTest extends TestCase
{
    public function testPreparesADocumentIntoSegmentsKeptInOrder(): void
    {
        $stores = new Stores();
        $result = $stores->translations->prepare('page:1', Sources::page('One.', 'Two.'), $this->en(), $this->fr());

        self::assertSame(['p1/text', 'p2/text'], $result->added);
        self::assertSame([['p1/text', 'One.', 'untranslated'], ['p2/text', 'Two.', 'untranslated']], $this->describe($stores->translations->segments('page:1', $this->fr())));

        $records = $stores->segments->forDocument('page:1', $this->fr());
        self::assertSame([[0, 'en', 'fr', 1_793_959_200], [1, 'en', 'fr', 1_793_959_200]], array_map(static fn (SegmentRecord $record): array => [$record->position(), $record->sourceLanguage()->value, $record->targetLanguage()->value, $record->updatedAt()->getTimestamp()], $records));
        self::assertSame('1793959200.000000', $records[0]->updatedAt()->format('U.u'));
        self::assertSame([], $stores->translations->segments('page:1', LanguageTag::of('de')));
    }

    public function testProposesTheExactMatchesOfTheMemoryToNewSegments(): void
    {
        $stores = new Stores();
        $stores->memory->add(new MemoryUnit($this->en(), $this->fr(), 'One.', 'Un.'));
        $stores->memory->add(new MemoryUnit($this->en(), $this->fr(), 'Two!', 'Deux !'));

        $result = $stores->translations->prepare('page:1', Sources::page('One.', 'Two.'), $this->en(), $this->fr());

        self::assertSame([['p1/text', 'Un.', 'suggested'], ['p2/text', null, 'untranslated']], array_map(static fn (Segment $segment): array => [$segment->key, $segment->target, $segment->state->value], $result->segments));
        self::assertSame([['p1/text', 'One.', 'suggested'], ['p2/text', 'Two.', 'untranslated']], $this->describe($stores->translations->segments('page:1', $this->fr())));
    }

    public function testTheMemoryProposesOnlyWhatComesFromADocumentTheUserMayTranslate(): void
    {
        $stores = new Stores(policy: new Policy(hidden: ['secret:1']));
        $stores->memory->add(new MemoryUnit($this->en(), $this->fr(), 'One.', 'Un, en secret.', 'secret:1'));
        $stores->memory->add(new MemoryUnit($this->en(), $this->fr(), 'One!', 'Un !', 'page:2'));
        $stores->memory->add(new MemoryUnit($this->en(), $this->fr(), 'One?', 'Un ?'));

        $result = $stores->translations->prepare('page:1', Sources::page('One.'), $this->en(), $this->fr());

        self::assertSame([['p1/text', null, 'untranslated']], array_map(static fn (Segment $segment): array => [$segment->key, $segment->target, $segment->state->value], $result->segments), 'no prefill from a hidden document');
        self::assertSame(['Un !', 'Un ?'], array_map(static fn (MemoryMatch $match): string => $match->unit->target, $stores->translations->matches(new Segment('k', 'One.'), $this->en(), $this->fr())));

        $open = new Stores();
        $open->memory->add(new MemoryUnit($this->en(), $this->fr(), 'One.', 'Un, en secret.', 'secret:1'));
        self::assertSame(['Un, en secret.'], array_map(static fn (MemoryMatch $match): string => $match->unit->target, $open->translations->matches(new Segment('k', 'One.'), $this->en(), $this->fr())), 'without a policy, the whole memory');
    }

    public function testProposesNothingWhenToldNotTo(): void
    {
        $stores = new Stores(prefill: false);
        $stores->memory->add(new MemoryUnit($this->en(), $this->fr(), 'One.', 'Un.'));
        $stores->translations->prepare('page:1', Sources::page('One.'), $this->en(), $this->fr());

        self::assertSame([['p1/text', 'One.', 'untranslated']], $this->describe($stores->translations->segments('page:1', $this->fr())));
    }

    public function testDoesNotProposeOverATargetAlreadyWritten(): void
    {
        $stores = new Stores();
        $stores->translations->prepare('page:1', Sources::page('One.'), $this->en(), $this->fr());
        $stores->translations->translate('page:1', $this->fr(), 'p1/text', 'Une.');
        $stores->memory->add(new MemoryUnit($this->en(), $this->fr(), 'One.', 'Un.'));
        $stores->translations->prepare('page:1', Sources::page('One.'), $this->en(), $this->fr());

        self::assertSame('Une.', $stores->translations->segment('page:1', $this->fr(), 'p1/text')->target);
    }

    public function testBringsTheSegmentsUpToDateWhenTheSourceChanges(): void
    {
        $stores = new Stores(granularity: Granularity::Sentence);
        $stores->translations->prepare('page:1', Sources::page('One. Two.', 'Three.'), $this->en(), $this->fr());
        $stores->translations->translate('page:1', $this->fr(), 'p1/text#1', 'Un.');
        $stores->translations->translate('page:1', $this->fr(), 'p1/text#2', 'Deux.');
        $stores->translations->translate('page:1', $this->fr(), 'p2/text#1', 'Trois.');

        $result = $stores->translations->prepare('page:1', Sources::page('One. Two!'), $this->en(), $this->fr());

        self::assertSame([], $result->moved);
        self::assertSame(['p1/text#2'], $result->stale);
        self::assertSame(['p2/text#1'], array_map(static fn (Segment $segment): string => $segment->key, $result->removed));
        self::assertSame([['p1/text#1', 'One.', 'translated'], ['p1/text#2', 'Two!', 'stale']], $this->describe($stores->translations->segments('page:1', $this->fr())));

        $result = $stores->translations->prepare('page:1', Sources::page('Zero. One. Two!'), $this->en(), $this->fr());

        self::assertSame(['p1/text#2' => 'p1/text#1', 'p1/text#3' => 'p1/text#2'], $result->moved);
        self::assertSame([['p1/text#1', 'Zero.', 'untranslated'], ['p1/text#2', 'One.', 'translated'], ['p1/text#3', 'Two!', 'stale']], $this->describe($stores->translations->segments('page:1', $this->fr())));
        self::assertSame([0, 1, 2], array_map(static fn (SegmentRecord $record): int => $record->position(), $stores->segments->forDocument('page:1', $this->fr())));
        self::assertSame('Zero.', $result->segments[0]->source);
    }

    public function testAPreviewGivesWhatAPreparationWouldAndStoresNothing(): void
    {
        $stores = new Stores();
        $stores->memory->add(new MemoryUnit($this->en(), $this->fr(), 'One.', 'Un.'));
        $stores->translations->prepare('page:1', Sources::page('One.', 'Two.'), $this->en(), $this->fr());
        $stores->translations->translate('page:1', $this->fr(), 'p2/text', 'Deux.');

        $before = $stores->segments->forDocument('page:1', $this->fr());
        $stamps = array_map(static fn (SegmentRecord $record): array => [$record->position(), $record->updatedAt()->getTimestamp(), $record->segment()->source], $before);

        $preview = $stores->translations->preview('page:1', Sources::page('Zero.', 'One.', 'Three.'), $this->en(), $this->fr());

        self::assertSame([['p1/text', 'Zero.', null, 'untranslated'], ['p2/text', 'One.', 'Un.', 'suggested'], ['p3/text', 'Three.', null, 'untranslated']], array_map(static fn (Segment $segment): array => [$segment->key, $segment->source, $segment->target, $segment->state->value], $preview->segments));
        self::assertSame($stamps, array_map(static fn (SegmentRecord $record): array => [$record->position(), $record->updatedAt()->getTimestamp(), $record->segment()->source], $stores->segments->forDocument('page:1', $this->fr())), 'the stored records are left as they were');
        self::assertSame([['p1/text', 'One.', 'suggested'], ['p2/text', 'Two.', 'translated']], $this->describe($stores->translations->segments('page:1', $this->fr())));

        $prepared = $stores->translations->prepare('page:1', Sources::page('Zero.', 'One.', 'Three.'), $this->en(), $this->fr());
        self::assertEquals($prepared, $preview);
    }

    public function testAPreviewOrItsExportWritesNoRow(): void
    {
        $segments = new RecordingSegments();
        $memory = new InMemoryStore([new MemoryUnit($this->en(), $this->fr(), 'One.', 'Un.')]);
        $translations = new Translations($segments, new TranslationMemory($memory), new Glossaries(new InMemoryTermRepository()), new UjoSegmenter(), new MockClock());

        self::assertSame(['p1/text', 'p2/text'], $translations->preview('page:1', Sources::page('One.', 'Two.'), $this->en(), $this->fr())->added);
        $file = new XliffReader()->read($translations->exportPreview('page:1', Sources::page('One.', 'Two.'), LanguageTag::of('de'), $this->fr()));

        self::assertSame(['de', 'fr', 'page:1'], [$file->sourceLanguage->value, $file->targetLanguage?->value, $file->original]);
        self::assertSame([['p1/text', 'One.', null], ['p2/text', 'Two.', null]], array_map(static fn (XliffUnit $unit): array => [$unit->key, $unit->source, $unit->target], $file->units), 'the memory is asked in the languages given');
        self::assertSame([], $segments->saved);
        self::assertSame([], $translations->segments('page:1', $this->fr()));
    }

    public function testProposesTheMemoryByDefault(): void
    {
        $memory = new InMemoryStore([new MemoryUnit($this->en(), $this->fr(), 'One.', 'Un.')]);
        $translations = new Translations(new InMemorySegmentRepository(), new TranslationMemory($memory), new Glossaries(new InMemoryTermRepository()), new UjoSegmenter(), new MockClock());

        self::assertSame(SegmentState::Suggested, $translations->prepare('page:1', Sources::page('One.'), $this->en(), $this->fr())->segments[0]->state);
    }

    public function testWritesEveryRowItChanges(): void
    {
        $segments = new RecordingSegments();
        $translations = new Translations($segments, new TranslationMemory(new InMemoryStore()), new Glossaries(new InMemoryTermRepository()), new UjoSegmenter(), new MockClock());
        $translations->prepare('page:1', Sources::page('One.', 'Two.'), $this->en(), $this->fr());
        $translations->translate('page:1', $this->fr(), 'p2/text', 'Deux.');
        $translations->import('page:1', $this->fr(), new XliffWriter()->write(new XliffFile($this->en(), $this->fr(), 'page:1', [new XliffUnit('p1/text', 'One.', 'Un.', SegmentState::Translated), new XliffUnit('p2/text', 'Two.', 'Deux.', SegmentState::Translated)])));

        self::assertSame([['p1/text', 'p2/text'], ['p2/text'], ['p1/text']], $segments->saved);
    }

    public function testExportsAndImportsInTheSourceLanguageOfTheFirstSegment(): void
    {
        $stores = $this->prepared();
        $stores->translations->prepare('page:1', new Document([new Block('q1', 'paragraph', ['text' => 'Hallo.']), ...new Sources()->documents['page:1']->content->blocks]), LanguageTag::of('de'), $this->fr());

        self::assertSame('de', new XliffReader()->read($stores->translations->export('page:1', $this->fr()))->sourceLanguage->value);
        self::assertSame(['q1/text'], $stores->translations->import('page:1', $this->fr(), new XliffWriter()->write(new XliffFile(LanguageTag::of('de'), $this->fr(), 'page:1', [new XliffUnit('q1/text', 'Hallo.', 'Salut.', SegmentState::Translated)])))->applied);
    }

    public function testTranslatesASegmentAndLearnsIt(): void
    {
        $stores = $this->prepared();
        $segment = $stores->translations->translate('page:1', $this->fr(), 'p1/text', 'Bonjour le monde.');

        self::assertSame([SegmentState::Translated, 'Bonjour le monde.'], [$segment->state, $segment->target]);
        self::assertSame('Bonjour le monde.', $stores->translations->segment('page:1', $this->fr(), 'p1/text')->target);
        self::assertSame(['Bonjour le monde.', 'page:1'], [$this->memoryTarget($stores, 'Hello world.'), $this->memoryOrigin($stores, 'Hello world.')]);
        self::assertSame(1_793_959_260, $this->later($stores, 'p1/text'));
    }

    public function testProposesReviewsConfirmsAndClears(): void
    {
        $stores = $this->prepared();

        self::assertSame(SegmentState::Suggested, $stores->translations->suggest('page:1', $this->fr(), 'p1/text', 'Salut.')->state);
        self::assertNull($this->memoryTarget($stores, 'Hello world.'));
        self::assertSame(SegmentState::Reviewed, $stores->translations->review('page:1', $this->fr(), 'p1/text')->state);
        self::assertSame('Salut.', $this->memoryTarget($stores, 'Hello world.'));
        self::assertSame(SegmentState::Untranslated, $stores->translations->clear('page:1', $this->fr(), 'p1/text')->state);
        self::assertSame(SegmentState::Untranslated, $stores->translations->segment('page:1', $this->fr(), 'p1/text')->state);

        $stores->translations->translate('page:1', $this->fr(), 'p2/text', 'La porte <b>rouge</b>.');
        $stores->translations->prepare('page:1', Sources::page('Hello world.', 'The <b>red</b> door!'), $this->en(), $this->fr());
        self::assertSame(SegmentState::Stale, $stores->translations->segment('page:1', $this->fr(), 'p2/text')->state);

        $confirmed = $stores->translations->confirm('page:1', $this->fr(), 'p2/text');
        self::assertSame([SegmentState::Translated, 'The <b>red</b> door!'], [$confirmed->state, $confirmed->translatedSource]);
        self::assertSame('La porte <b>rouge</b>.', $this->memoryTarget($stores, 'The <b>red</b> door!'));
    }

    public function testRefusesAChangeTheSegmentCannotTake(): void
    {
        $stores = $this->prepared();

        $this->expectException(InvalidSegment::class);

        $stores->translations->review('page:1', $this->fr(), 'p1/text');
    }

    public function testRefusesASegmentItDoesNotHave(): void
    {
        $stores = $this->prepared();

        $this->expectException(NotFound::class);
        $this->expectExceptionMessageIsOrContains('Document "page:1" has no segment "p9/text" in "fr".');

        $stores->translations->translate('page:1', $this->fr(), 'p9/text', 'x');
    }

    public function testProposesMatchesFindsTermsAndChecksTargets(): void
    {
        $stores = $this->prepared();
        $stores->memory->add(new MemoryUnit($this->en(), $this->fr(), 'The <b>red</b> doors.', 'Les portes <b>rouges</b>.'));
        $stores->glossaries->put($this->en(), $this->fr(), new Term('door', ['porte']));

        self::assertSame(['Les portes <b>rouges</b>.'], array_map(static fn (MemoryMatch $match): string => $match->unit->target, $stores->translations->suggestions('page:1', $this->fr(), 'p2/text')));
        self::assertSame(['door'], array_map(static fn (TermHit $hit): string => $hit->text, $stores->translations->terms('page:1', $this->fr(), 'p2/text')));
        self::assertSame([], array_map(static fn (TermIssue $issue): string => $issue->kind->value, $stores->translations->issues('page:1', $this->fr(), 'p2/text')));

        $stores->translations->translate('page:1', $this->fr(), 'p2/text', 'L’huis <b>rouge</b>.');

        self::assertSame(['door'], array_map(static fn (TermIssue $issue): string => $issue->term->source, $stores->translations->issues('page:1', $this->fr(), 'p2/text')));
        self::assertSame(['door'], array_map(static fn (Term $term): string => $term->source, $stores->translations->glossary($this->en(), $this->fr())->terms));
        self::assertCount(2, $stores->translations->matches(new Segment('k', 'The <b>red</b> door.'), $this->en(), $this->fr()));
    }

    public function testCountsTheProgressOfADocument(): void
    {
        $stores = $this->prepared();
        $stores->translations->translate('page:1', $this->fr(), 'p1/text', 'Bonjour.');

        self::assertSame([2, 1, 50], [$stores->translations->progress('page:1', $this->fr())->total(), $stores->translations->progress('page:1', $this->fr())->usable(), $stores->translations->progress('page:1', $this->fr())->percent()]);
    }

    public function testExportsTheSegmentsAsXliff(): void
    {
        $stores = $this->prepared();
        $stores->translations->translate('page:1', $this->fr(), 'p1/text', 'Bonjour.');

        $file = new XliffReader()->read($stores->translations->export('page:1', $this->fr()));

        self::assertSame(['en', 'fr', 'page:1'], [$file->sourceLanguage->value, $file->targetLanguage?->value, $file->original]);
        self::assertSame([['p1/text', 'Bonjour.', 'translated'], ['p2/text', null, 'untranslated']], array_map(static fn (XliffUnit $unit): array => [$unit->key, $unit->target, $unit->state->value], $file->units));
    }

    public function testExportsOnlyAPreparedDocument(): void
    {
        $this->expectException(NotFound::class);
        $this->expectExceptionMessageIsOrContains('Document "page:1" has no segments in "fr": prepare it first.');

        new Stores()->translations->export('page:1', $this->fr());
    }

    public function testImportsAnXliffFileAndLearnsWhatItMakesUsable(): void
    {
        $stores = $this->prepared();
        $stores->translations->translate('page:1', $this->fr(), 'p1/text', 'Bonjour.');

        $xml = new XliffWriter()->write(new XliffFile($this->en(), $this->fr(), 'page:1', [
            new XliffUnit('p1/text', 'Hello world.', 'Bonjour.', SegmentState::Translated),
            new XliffUnit('p2/text', 'The <b>red</b> door.', 'La porte <b>rouge</b>.', SegmentState::Reviewed),
        ]));
        $stores->clock->sleep(60);
        $result = $stores->translations->import('page:1', $this->fr(), $xml, canReview: true);

        self::assertSame(['p2/text'], $result->applied);
        self::assertCount(1, $result->skipped);
        self::assertSame([['p1/text', 'Hello world.', 'translated'], ['p2/text', 'The <b>red</b> door.', 'reviewed']], $this->describe($stores->translations->segments('page:1', $this->fr())));
        self::assertSame('La porte <b>rouge</b>.', $this->memoryTarget($stores, 'The <b>red</b> door.'));
        self::assertSame([1_793_959_200, 1_793_959_260], array_map(static fn (SegmentRecord $record): int => $record->updatedAt()->getTimestamp(), $stores->segments->forDocument('page:1', $this->fr())));
        self::assertSame([0, 1], array_map(static fn (SegmentRecord $record): int => $record->position(), $stores->segments->forDocument('page:1', $this->fr())));
    }

    public function testRefusesAFileOfOtherLanguages(): void
    {
        $stores = $this->prepared();
        $xml = new XliffWriter()->write(new XliffFile(LanguageTag::of('de'), $this->fr(), 'page:1'));

        $this->expectException(InvalidLanguage::class);

        $stores->translations->import('page:1', $this->fr(), $xml);
    }

    public function testRefusesAFileItCannotRead(): void
    {
        $stores = $this->prepared();

        $this->expectException(InvalidXliff::class);

        $stores->translations->import('page:1', $this->fr(), '<nope/>');
    }

    public function testImportsOnlyIntoAPreparedDocument(): void
    {
        $this->expectException(NotFound::class);

        new Stores()->translations->import('page:1', $this->fr(), '<nope/>');
    }

    public function testPutsTheTranslatedDocumentTogether(): void
    {
        $stores = $this->prepared();
        $stores->translations->translate('page:1', $this->fr(), 'p1/text', 'Bonjour le monde.');
        $stores->translations->suggest('page:1', $this->fr(), 'p2/text', 'La porte rouge.');

        $source = new Sources()->documents['page:1']->content;

        self::assertSame(['Bonjour le monde.', 'The <b>red</b> door.'], array_map(static fn (Block $block): mixed => $block->data['text'], $stores->translations->translated('page:1', $source, $this->fr())->blocks));
        self::assertSame(['Hello world.', 'La porte rouge.'], array_map(static fn (Block $block): mixed => $block->data['text'], $stores->translations->translated('page:1', $source, $this->fr(), [SegmentState::Suggested])->blocks));
    }

    private function prepared(): Stores
    {
        $stores = new Stores();
        $stores->translations->prepare('page:1', new Sources()->documents['page:1']->content, $this->en(), $this->fr());

        return $stores;
    }

    private function later(Stores $stores, string $key): int
    {
        $stores->clock->sleep(60);
        $stores->translations->translate('page:1', $this->fr(), $key, 'x');

        foreach ($stores->segments->forDocument('page:1', $this->fr()) as $record) {
            if ($record->key() === $key) {
                return $record->updatedAt()->getTimestamp();
            }
        }

        return 0;
    }

    private function memoryOrigin(Stores $stores, string $source): ?string
    {
        foreach ($stores->memory->exact($this->en(), $this->fr(), new Segment('k', $source)->fingerprint) as $unit) {
            return $unit->origin;
        }

        return null;
    }

    private function memoryTarget(Stores $stores, string $source): ?string
    {
        foreach ($stores->memory->exact($this->en(), $this->fr(), new Segment('k', $source)->fingerprint) as $unit) {
            return $unit->target;
        }

        return null;
    }

    /**
     * @param list<Segment> $segments
     *
     * @return list<array{string, string, string}>
     */
    private function describe(array $segments): array
    {
        return array_map(static fn (Segment $segment): array => [$segment->key, $segment->source, $segment->state->value], $segments);
    }

    private function en(): LanguageTag
    {
        return LanguageTag::of('en');
    }

    private function fr(): LanguageTag
    {
        return LanguageTag::of('fr');
    }
}
