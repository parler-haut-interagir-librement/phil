<?php

declare(strict_types=1);

namespace Phil\TranslationBundle\Tests\Unit\Application;

use Phil\TranslationBundle\Application\Comments;
use Phil\TranslationBundle\Application\Credit;
use Phil\TranslationBundle\Application\Exception\MachineTranslationFailed;
use Phil\TranslationBundle\Application\Exception\NotFound;
use Phil\TranslationBundle\Application\Glossaries;
use Phil\TranslationBundle\Application\Translations;
use Phil\TranslationBundle\Domain\Exception\InvalidRecord;
use Phil\TranslationBundle\Domain\Model\CommentRecord;
use Phil\TranslationBundle\Domain\Model\SegmentRecord;
use Phil\TranslationBundle\Infrastructure\Memory\InMemoryTermRepository;
use Phil\TranslationBundle\Tests\Unit\Fixture\Machine;
use Phil\TranslationBundle\Tests\Unit\Fixture\RecordingSegments;
use Phil\TranslationBundle\Tests\Unit\Fixture\Sources;
use Phil\TranslationBundle\Tests\Unit\Fixture\Stores;
use Phil\TranslationMemory\Check\Checker;
use Phil\TranslationMemory\Check\CheckIssue;
use Phil\TranslationMemory\Check\Flags;
use Phil\TranslationMemory\Check\SameAsSource;
use Phil\TranslationMemory\Exception\InvalidConfiguration;
use Phil\TranslationMemory\LanguageTag;
use Phil\TranslationMemory\Memory\InMemoryStore;
use Phil\TranslationMemory\Memory\TranslationMemory;
use Phil\TranslationMemory\Segment;
use Phil\TranslationMemory\Segmentation\UjoSegmenter;
use Phil\TranslationMemory\SegmentState;
use Phil\TranslationMemory\Variants;
use Phil\TranslationMemory\Xliff\XliffFile;
use Phil\TranslationMemory\Xliff\XliffUnit;
use Phil\TranslationMemory\Xliff\XliffWriter;
use Phil\Ujo\Block;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\Clock\MockClock;

#[CoversClass(Translations::class)]
#[CoversClass(Comments::class)]
#[CoversClass(Credit::class)]
#[CoversClass(MachineTranslationFailed::class)]
#[CoversClass(NotFound::class)]
final class ContributionsTest extends TestCase
{
    public function testEachActionSaysWhoWroteAndReviewedTheTarget(): void
    {
        $stores = $this->prepared();
        $stores->translators->current = 'ana';
        $stores->translations->suggest('page:1', $this->fr(), 'p1/text', 'Bonjour.');
        self::assertSame(['ana', null, false], $this->attribution($stores, 'p1/text'));

        $stores->translators->current = 'ben';
        $stores->translations->translate('page:1', $this->fr(), 'p1/text', 'Bonjour le monde.');
        self::assertSame(['ben', null, false], $this->attribution($stores, 'p1/text'));

        $stores->translators->current = 'cleo';
        $stores->translations->review('page:1', $this->fr(), 'p1/text');
        self::assertSame(['ben', 'cleo', false], $this->attribution($stores, 'p1/text'));

        $stores->translations->prepare('page:1', Sources::page('Hello world!', 'The <b>red</b> door.'), $this->en(), $this->fr());
        $stores->translators->current = 'dan';
        $stores->translations->confirm('page:1', $this->fr(), 'p1/text');
        self::assertSame(['ben', null, false], $this->attribution($stores, 'p1/text'));

        $stores->translations->clear('page:1', $this->fr(), 'p1/text');
        self::assertSame([null, null, false], $this->attribution($stores, 'p1/text'));
    }

    public function testAnImportIsSignedByWhoImportsIt(): void
    {
        $stores = $this->prepared();
        $stores->translators->current = 'ana';
        $stores->translations->import('page:1', $this->fr(), new XliffWriter()->write(new XliffFile($this->en(), $this->fr(), 'page:1', [
            new XliffUnit('p1/text', 'Hello world.', 'Bonjour le monde.', SegmentState::Reviewed),
            new XliffUnit('p2/text', 'The <b>red</b> door.', 'La porte <b>rouge</b>.', SegmentState::Translated),
        ])), canReview: true);

        self::assertSame(['ana', 'ana', false], $this->attribution($stores, 'p1/text'));
        self::assertSame(['ana', null, false], $this->attribution($stores, 'p2/text'));
    }

    public function testAnImportBySomeoneWhoMayNotReviewMarksNothingReviewed(): void
    {
        $stores = $this->prepared();
        $stores->translators->current = 'ana';

        $result = $stores->translations->import('page:1', $this->fr(), new XliffWriter()->write(new XliffFile($this->en(), $this->fr(), 'page:1', [
            new XliffUnit('p1/text', 'Hello world.', 'Bonjour le monde.', SegmentState::Reviewed),
            new XliffUnit('p2/text', 'The <b>red</b> door.', 'La porte <b>rouge</b>.', SegmentState::Suggested),
        ])));

        self::assertSame(['ana', null, false], $this->attribution($stores, 'p1/text'));
        self::assertSame([SegmentState::Translated, SegmentState::Suggested], array_map(static fn (Segment $segment): SegmentState => $segment->state, $result->segments));
        self::assertSame([SegmentState::Translated, SegmentState::Suggested], array_map(static fn (Segment $segment): SegmentState => $segment->state, $stores->translations->segments('page:1', $this->fr())));
        self::assertSame(['p1/text', 'p2/text'], $result->applied);
        self::assertSame([], $result->skipped);
        self::assertSame('Bonjour le monde.', $stores->translations->segment('page:1', $this->fr(), 'p1/text')->target);
    }

    public function testThanksThoseWhoTranslatedAndReviewedUsableTargets(): void
    {
        $stores = new Stores();
        $stores->translations->prepare('page:1', Sources::page('One.', 'Two.', 'Three.', 'Four.', 'Five.'), $this->en(), $this->fr());
        $stores->translators->names = ['ana' => ' Ana ', 'ben' => 'Ben', 'blank' => ' '];
        $stores->translators->names['7'] = 'Seven';
        $stores->translators->current = 'ana';
        $stores->translations->translate('page:1', $this->fr(), 'p1/text', 'Un.');
        $stores->translations->translate('page:1', $this->fr(), 'p2/text', 'Deux.');
        $stores->translations->suggest('page:1', $this->fr(), 'p5/text', 'Cinq.');
        $stores->translators->current = 'ben';
        $stores->translations->translate('page:1', $this->fr(), 'p3/text', 'Trois.');
        $stores->translations->review('page:1', $this->fr(), 'p1/text');
        $stores->translators->current = '7';
        $stores->translations->review('page:1', $this->fr(), 'p2/text');
        $stores->translators->current = 'shy';
        $stores->translations->translate('page:1', $this->fr(), 'p4/text', 'Quatre.');

        self::assertEquals([new Credit('Ana', 2, 0), new Credit('Ben', 1, 1), new Credit('Seven', 0, 1)], $stores->translations->credits('page:1', $this->fr()));

        $stores->translators->current = 'blank';
        $stores->translations->translate('page:1', $this->fr(), 'p4/text', 'Quatre !');
        self::assertCount(3, $stores->translations->credits('page:1', $this->fr()));
        self::assertSame([], $stores->translations->credits('page:1', LanguageTag::of('de')));
        self::assertSame([], new Stores()->translations->credits('page:1', $this->fr()));
    }

    public function testCreditsOfEqualCountsGoByName(): void
    {
        $stores = new Stores();
        $stores->translations->prepare('page:1', Sources::page('One.', 'Two.'), $this->en(), $this->fr());
        $stores->translators->names = ['z' => 'Zoé', 'a' => 'Alix'];
        $stores->translators->current = 'z';
        $stores->translations->translate('page:1', $this->fr(), 'p1/text', 'Un.');
        $stores->translators->current = 'a';
        $stores->translations->translate('page:1', $this->fr(), 'p2/text', 'Deux.');

        self::assertSame(['Alix', 'Zoé'], array_map(static fn (Credit $credit): string => $credit->name, $stores->translations->credits('page:1', $this->fr())));
    }

    public function testACreditWithoutCountsStartsAtZero(): void
    {
        $credit = new Credit('Ana');

        self::assertSame([0, 0], [$credit->translated, $credit->reviewed]);
    }

    public function testCreditsCountEveryReviewAndSkipUnusableSegmentsWhereverTheyStand(): void
    {
        $stores = new Stores();
        $stores->translations->prepare('page:1', Sources::page('One.', 'Two.', 'Three.', 'Four.'), $this->en(), $this->fr());
        $stores->translators->names = ['ana' => 'Ana', 'ben' => 'Ben'];
        $stores->translators->current = 'ana';
        $stores->translations->translate('page:1', $this->fr(), 'p2/text', 'Deux.');
        $stores->translations->translate('page:1', $this->fr(), 'p3/text', 'Trois.');
        $stores->translations->translate('page:1', $this->fr(), 'p4/text', 'Quatre.');
        $stores->translators->current = 'ben';
        $stores->translations->review('page:1', $this->fr(), 'p2/text');
        $stores->translations->review('page:1', $this->fr(), 'p3/text');

        self::assertEquals([new Credit('Ana', 3, 0), new Credit('Ben', 0, 2)], $stores->translations->credits('page:1', $this->fr()));
    }

    public function testCreditsLeaveOutAnAnonymousTranslatorWhateverTheNameGivenForAnEmptyIdentifier(): void
    {
        $stores = new Stores();
        $stores->translations->prepare('page:1', Sources::page('One.'), $this->en(), $this->fr());
        $stores->translators->names = ['' => 'Nobody'];
        $stores->translations->translate('page:1', $this->fr(), 'p1/text', 'Un.');

        self::assertSame([], $stores->translations->credits('page:1', $this->fr()));
    }

    public function testCreditsOfARecordNobodyKnowsAreEmptyWithoutTranslators(): void
    {
        $stores = new Stores();
        $stores->translations->prepare('page:1', Sources::page('One.'), $this->en(), $this->fr());
        $stores->translators->current = 'ana';
        $stores->translators->names = ['ana' => 'Ana'];
        $stores->translations->translate('page:1', $this->fr(), 'p1/text', 'Un.');

        $alone = new Translations($stores->segments, new TranslationMemory(new InMemoryStore()), $stores->glossaries, new UjoSegmenter(), $stores->clock);

        self::assertSame([], $alone->credits('page:1', $this->fr()));
    }

    public function testCreditsAreSortedByTheirTotalThenByName(): void
    {
        $stores = new Stores();
        $stores->translations->prepare('page:1', Sources::page('One.', 'Two.', 'Three.', 'Four.', 'Five.'), $this->en(), $this->fr());
        $stores->translators->names = ['a' => 'Alix', 'b' => 'Bao', 'c' => 'Cyd'];
        $stores->translators->current = 'a';
        $stores->translations->translate('page:1', $this->fr(), 'p1/text', 'Un.');
        $stores->translators->current = 'b';
        $stores->translations->translate('page:1', $this->fr(), 'p2/text', 'Deux.');
        $stores->translations->translate('page:1', $this->fr(), 'p3/text', 'Trois.');
        $stores->translators->current = 'c';
        $stores->translations->review('page:1', $this->fr(), 'p1/text');
        $stores->translations->review('page:1', $this->fr(), 'p2/text');
        $stores->translations->review('page:1', $this->fr(), 'p3/text');

        self::assertSame(['Cyd', 'Bao', 'Alix'], array_map(static fn (Credit $credit): string => $credit->name, $stores->translations->credits('page:1', $this->fr())));
    }

    public function testMachineTranslationWritesTheRowsItProposed(): void
    {
        $segments = new RecordingSegments();
        $translations = new Translations($segments, new TranslationMemory(new InMemoryStore()), new Glossaries(new InMemoryTermRepository()), new UjoSegmenter(), new MockClock(), machine: Machine::shouting());
        $translations->prepare('page:1', Sources::page('One.', 'Two.'), $this->en(), $this->fr());
        $translations->translate('page:1', $this->fr(), 'p1/text', 'Un.');

        $segments->saved = [];

        $translations->machineTranslate('page:1', $this->fr());

        self::assertSame([['p2/text']], $segments->saved);
    }

    public function testFlagsAreWrittenToTheRow(): void
    {
        $segments = new RecordingSegments();
        $translations = new Translations($segments, new TranslationMemory(new InMemoryStore()), new Glossaries(new InMemoryTermRepository()), new UjoSegmenter(), new MockClock());
        $translations->prepare('page:1', Sources::page('One.'), $this->en(), $this->fr());

        $segments->saved = [];

        $translations->flag('page:1', $this->fr(), 'p1/text', new Flags(['ignore-same']));

        self::assertSame([['p1/text']], $segments->saved);
    }

    public function testAMachineTranslationFailureCarriesNoCode(): void
    {
        self::assertSame(0, MachineTranslationFailed::because('t', new RuntimeException('x'))->getCode());
    }

    public function testMachineTranslationProposesTargetsMarkedAsMachineOutput(): void
    {
        $machine = Machine::shouting();
        $stores = new Stores(machine: $machine);
        $stores->translations->prepare('page:1', Sources::page('One.', 'Two.'), $this->en(), $this->fr());
        $stores->translators->current = 'ana';
        $stores->translators->names = ['ana' => 'Ana'];
        $stores->translations->translate('page:1', $this->fr(), 'p2/text', 'Deux.');

        self::assertTrue($stores->translations->canMachineTranslate());
        self::assertSame(1, $stores->translations->machineTranslate('page:1', $this->fr()));
        self::assertSame([[['One.'], 'en', 'fr']], $machine->calls);
        self::assertSame([['p1/text', 'ONE.', 'suggested'], ['p2/text', 'Deux.', 'translated']], array_map(static fn (Segment $segment): array => [$segment->key, $segment->target, $segment->state->value], $stores->translations->segments('page:1', $this->fr())));
        self::assertSame([null, null, true], $this->attribution($stores, 'p1/text'));
        self::assertSame(['ana', null, false], $this->attribution($stores, 'p2/text'));

        $stores->translations->review('page:1', $this->fr(), 'p1/text');
        self::assertSame([null, 'ana', true], $this->attribution($stores, 'p1/text'));
        self::assertEquals([new Credit('Ana', 1, 1)], $stores->translations->credits('page:1', $this->fr()));
        self::assertSame(0, $stores->translations->machineTranslate('page:1', $this->fr()));
    }

    public function testMachineTranslationSendsBatchesOfFifty(): void
    {
        $machine = Machine::shouting();
        $stores = new Stores(machine: $machine);
        $stores->translations->prepare('page:1', Sources::page(...array_map(static fn (int $index): string => 'Line '.$index.'.', range(1, 101))), $this->en(), $this->fr());

        self::assertSame(101, $stores->translations->machineTranslate('page:1', $this->fr()));
        self::assertSame([50, 50, 1], array_map(static fn (array $call): int => \count($call[0]), $machine->calls));
        self::assertSame(50, Translations::MACHINE_BATCH);
    }

    public function testMachineTranslationLeavesOutTargetsWithoutWords(): void
    {
        $stores = new Stores(machine: new Machine(static fn (array $sources): array => [' <b></b> ', "\xFF", 'Trois.']));
        $stores->translations->prepare('page:1', Sources::page('One.', 'Two.', 'Three.'), $this->en(), $this->fr());

        self::assertSame(1, $stores->translations->machineTranslate('page:1', $this->fr()));
        self::assertSame([null, null, 'Trois.'], array_map(static fn (Segment $segment): ?string => $segment->target, $stores->translations->segments('page:1', $this->fr())));
    }

    public function testMachineTranslationIsOffByDefault(): void
    {
        $stores = $this->prepared();

        self::assertFalse($stores->translations->canMachineTranslate());
        $this->expectException(MachineTranslationFailed::class);
        $this->expectExceptionMessageIsOrContains('Machine translation is off: set phil_translation.machine_translation.translator to turn it on.');
        $stores->translations->machineTranslate('page:1', $this->fr());
    }

    public function testMachineTranslationRefusesAWrongAnswer(): void
    {
        $stores = new Stores(machine: new Machine(static fn (array $sources): array => ['Un.']));
        $stores->translations->prepare('page:1', Sources::page('One.', 'Two.'), $this->en(), $this->fr());

        try {
            $stores->translations->machineTranslate('page:1', $this->fr());
            self::fail('A wrong answer is refused.');
        } catch (MachineTranslationFailed $machineTranslationFailed) {
            self::assertSame('The machine translator "test-machine" gave 1 targets for 2 sources.', $machineTranslationFailed->getMessage());
        }

        self::assertSame([null, null], array_map(static fn (Segment $segment): ?string => $segment->target, $stores->translations->segments('page:1', $this->fr())));
    }

    public function testMachineTranslationReportsAFailure(): void
    {
        $stores = new Stores(machine: new Machine(static fn (array $sources): array => throw new RuntimeException('quota exceeded')));
        $stores->translations->prepare('page:1', Sources::page('One.'), $this->en(), $this->fr());

        try {
            $stores->translations->machineTranslate('page:1', $this->fr());
            self::fail('A failure is reported.');
        } catch (MachineTranslationFailed $machineTranslationFailed) {
            self::assertSame('The machine translator "test-machine" failed: quota exceeded', $machineTranslationFailed->getMessage());
            self::assertInstanceOf(RuntimeException::class, $machineTranslationFailed->getPrevious());
        }
    }

    public function testMachineTranslationNeedsAPreparedDocument(): void
    {
        $this->expectException(NotFound::class);

        new Stores(machine: Machine::shouting())->translations->machineTranslate('page:1', $this->fr());
    }

    public function testAVariantIsPublishedThroughItsParentAndReadRawForTheAdministration(): void
    {
        $stores = new Stores();
        $source = Sources::page('Hello.', 'Email me.', 'Weekend.');
        $canada = LanguageTag::of('fr-CA');
        $stores->translations->prepare('page:1', $source, $this->en(), $this->fr());
        $stores->translations->translate('page:1', $this->fr(), 'p1/text', 'Bonjour.');
        $stores->translations->translate('page:1', $this->fr(), 'p2/text', 'Écrivez-moi un e-mail.');
        $stores->translations->suggest('page:1', $this->fr(), 'p3/text', 'Week-end.');
        $stores->translations->prepare('page:1', $source, $this->en(), $canada);
        $stores->translations->translate('page:1', $canada, 'p2/text', 'Écrivez-moi un courriel.');

        self::assertSame('fr', $stores->translations->parentOf($canada)?->value);
        self::assertNull($stores->translations->parentOf($this->fr()));
        self::assertSame(['Bonjour.', 'Écrivez-moi un courriel.', 'Weekend.'], $this->texts($stores->translations->translated('page:1', $source, $canada)->blocks));
        self::assertSame(['Hello.', 'Écrivez-moi un courriel.', 'Weekend.'], $this->texts($stores->translations->raw('page:1', $source, $canada)->blocks));
        self::assertSame(['Bonjour.', 'Écrivez-moi un e-mail.', 'Weekend.'], $this->texts($stores->translations->translated('page:1', $source, $this->fr())->blocks));
        self::assertSame(['p1/text', 'p2/text', 'p3/text'], array_map(static fn (Segment $segment): string => $segment->key, $stores->translations->resolved('page:1', $canada)));
        self::assertSame(['Bonjour.', 'Écrivez-moi un courriel.', 'Weekend.'], $this->texts($stores->translations->translated('page:1', $source, $canada, [SegmentState::Translated, SegmentState::Suggested])->blocks), 'a suggestion of the parent is not inherited');
    }

    public function testAVariantWithoutSegmentsReadsItsParent(): void
    {
        $stores = new Stores();
        $source = Sources::page('Hello.');
        $stores->translations->prepare('page:1', $source, $this->en(), $this->fr());
        $stores->translations->translate('page:1', $this->fr(), 'p1/text', 'Bonjour.');

        self::assertSame(['Bonjour.'], $this->texts($stores->translations->translated('page:1', $source, LanguageTag::of('fr-CA'))->blocks));
        self::assertSame(['Hello.'], $this->texts($stores->translations->translated('page:1', $source, LanguageTag::of('fr-BE'))->blocks));
        self::assertSame(['Hello.'], $this->texts(new Stores(variants: Variants::none())->translations->translated('page:1', $source, LanguageTag::of('fr-CA'))->blocks));
    }

    public function testChecksASegmentAndKeepsItsFlags(): void
    {
        $stores = $this->prepared();
        $segment = new Segment('k', 'Hello world.', SegmentState::Translated, 'Hello world.');

        self::assertSame(['same'], array_map(static fn (CheckIssue $issue): string => $issue->check, $stores->translations->check($segment, $this->en(), $this->fr())));
        self::assertSame([], $stores->translations->check($segment, $this->en(), $this->fr(), new Flags(['ignore-same'])));
        self::assertSame([], $stores->translations->check(new Segment('k', 'Hello'), $this->en(), $this->fr()));

        self::assertSame(['ignore-same'], $stores->translations->dismiss('page:1', $this->fr(), 'p1/text', 'same')->all());
        self::assertSame(['ignore-same', 'ignore-tags'], $stores->translations->dismiss('page:1', $this->fr(), 'p1/text', 'tags')->all());
        self::assertSame(['ignore-same', 'ignore-tags'], $stores->translations->records('page:1', $this->fr())['p1/text']->flags()->all());
        self::assertSame(['max-size:90'], $stores->translations->flag('page:1', $this->fr(), 'p1/text', new Flags(['max-size:90']))->all());
        self::assertSame(['p1/text', 'p2/text'], array_keys($stores->translations->records('page:1', $this->fr())));
        self::assertSame([], $stores->translations->records('page:1', LanguageTag::of('de')));
    }

    public function testRunsTheChecksItIsGiven(): void
    {
        $stores = new Stores();
        $translations = new Translations($stores->segments, new TranslationMemory(new InMemoryStore()), $stores->glossaries, new UjoSegmenter(), $stores->clock, checker: new Checker([new SameAsSource()]));

        self::assertSame([], $translations->check(new Segment('k', 'Hi.', SegmentState::Translated, 'Salut'), $this->en(), $this->fr()));
    }

    public function testRefusesAFlagThatIsNotOne(): void
    {
        $stores = $this->prepared();

        $this->expectException(InvalidConfiguration::class);
        $stores->translations->dismiss('page:1', $this->fr(), 'p1/text', 'Not a check');
    }

    public function testRefusesFlagsTooLong(): void
    {
        $stores = $this->prepared();

        $this->expectException(InvalidRecord::class);
        $this->expectExceptionMessageIsOrContains('The flags of a segment are at most 1024 bytes.');
        $stores->translations->flag('page:1', $this->fr(), 'p1/text', new Flags(array_map(static fn (int $index): string => 'f'.$index.str_repeat('x', 28).':'.str_repeat('1', 32), range(1, 32))));
    }

    public function testCommentsASegmentSignedByTheCurrentUser(): void
    {
        $stores = new Stores();
        $stores->translators->current = 'ana';

        $first = $stores->comments->add('page:1', $this->fr(), 'p1/text', '  Why “vous”?  ');
        $stores->translators->current = null;
        $stores->comments->add('page:1', $this->fr(), 'p2/text', 'Fine.');
        $stores->comments->add('page:1', $this->fr(), 'p1/text', 'Because.');
        $stores->comments->add('page:1', LanguageTag::of('de'), 'p1/text', 'Gut.');

        self::assertSame(['Why “vous”?', 'ana', 1_793_959_200], [$first->body(), $first->author(), $first->createdAt()->getTimestamp()]);
        self::assertSame(['p1/text' => ['Why “vous”?', 'Because.'], 'p2/text' => ['Fine.']], array_map(static fn (array $records): array => array_map(static fn (CommentRecord $record): string => $record->body(), $records), $stores->comments->forDocument('page:1', $this->fr())));

        $stores->comments->remove('page:1', $this->fr(), (int) $first->id());
        self::assertSame(['Because.'], array_map(static fn (CommentRecord $record): string => $record->body(), $stores->comments->forDocument('page:1', $this->fr())['p1/text']));
        self::assertSame([], new Comments($stores->commentRecords, $stores->clock)->forDocument('page:2', $this->fr()));
        self::assertNull(new Comments($stores->commentRecords, $stores->clock)->add('page:1', $this->fr(), 'k', 'x')->author());
    }

    public function testRemovesOnlyACommentOfTheDocumentAndLanguage(): void
    {
        $stores = new Stores();
        $comment = $stores->comments->add('page:1', $this->fr(), 'p1/text', 'Hm.');

        foreach ([['page:2', $this->fr(), (int) $comment->id()], ['page:1', LanguageTag::of('de'), (int) $comment->id()], ['page:1', $this->fr(), 99]] as [$document, $language, $id]) {
            try {
                $stores->comments->remove($document, $language, $id);
                self::fail('Not found.');
            } catch (NotFound $notFound) {
                self::assertSame(\sprintf('There is no comment %d.', $id), $notFound->getMessage());
            }
        }

        self::assertCount(1, $stores->comments->forDocument('page:1', $this->fr())['p1/text']);
    }

    /**
     * @return array{?string, ?string, bool}
     */
    private function attribution(Stores $stores, string $key): array
    {
        $record = $stores->translations->records('page:1', $this->fr())[$key];
        self::assertInstanceOf(SegmentRecord::class, $record);

        return [$record->translatedBy(), $record->reviewedBy(), $record->isMachine()];
    }

    private function prepared(): Stores
    {
        $stores = new Stores();
        $stores->translations->prepare('page:1', new Sources()->documents['page:1']->content, $this->en(), $this->fr());

        return $stores;
    }

    /**
     * @param list<Block> $blocks
     *
     * @return list<mixed>
     */
    private function texts(array $blocks): array
    {
        return array_map(static fn (Block $block): mixed => $block->data['text'], $blocks);
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
