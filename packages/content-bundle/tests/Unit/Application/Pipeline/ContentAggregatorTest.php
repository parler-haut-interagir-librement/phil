<?php

declare(strict_types=1);

namespace Phil\ContentBundle\Tests\Unit\Application\Pipeline;

use Phil\ContentBundle\Application\Merger\TemplateMerger;
use Phil\ContentBundle\Application\Pipeline\ContentAggregator;
use Phil\ContentBundle\Domain\Dimension\DimensionKey;
use Phil\ContentBundle\Domain\Dimension\Stage;
use Phil\ContentBundle\Domain\Exception\ContentNotFound;
use Phil\ContentBundle\Domain\Exception\InvalidDimension;
use Phil\ContentBundle\Tests\Unit\Fixture\ExampleDimensionContent;
use Phil\ContentBundle\Tests\Unit\Fixture\ExampleRoot;
use Phil\ContentBundle\Tests\Unit\Fixture\InMemoryDimensionContentRepository;
use Phil\ContentBundle\Tests\Unit\Fixture\RecordingMerger;
use Phil\LocaleDimensions\FallbackChain;
use Phil\LocaleDimensions\Locale;
use Phil\LocaleDimensions\Version;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ContentAggregator::class)]
final class ContentAggregatorTest extends TestCase
{
    private ExampleRoot $root;

    private InMemoryDimensionContentRepository $repository;

    protected function setUp(): void
    {
        $this->root = new ExampleRoot();
        $this->repository = new InMemoryDimensionContentRepository();
    }

    public function testTheLocalizedRowIsMergedOverTheSharedRow(): void
    {
        $this->store(null, 'article', ['image' => 'cover.png', 'title' => 'shared']);
        $this->store('fr_CA', null, ['title' => 'Bonjour']);

        $merged = $this->aggregator()->aggregate($this->root, $this->key('fr_CA'));

        self::assertInstanceOf(ExampleDimensionContent::class, $merged);
        self::assertSame('article', $merged->getTemplateKey());
        self::assertSame(['image' => 'cover.png', 'title' => 'Bonjour'], $merged->getTemplateData());
    }

    /**
     * The merged row is a fresh row of the application's class, carrying the requested dimension: merging
     * never writes onto a stored row.
     */
    public function testTheResultIsAFreshRowAndTheStoredRowsAreUntouched(): void
    {
        $shared = $this->store(null, 'article', ['title' => 'shared']);
        $localized = $this->store('fr_CA', null, ['title' => 'Bonjour']);

        $merged = $this->aggregator()->aggregate($this->root, $this->key('fr_CA'));

        self::assertNotSame($shared, $merged);
        self::assertNotSame($localized, $merged);
        self::assertSame($this->root, $merged->root());
        self::assertTrue($merged->dimension()->equals($this->key('fr_CA')));
        self::assertSame(['title' => 'shared'], $shared->getTemplateData());
        self::assertNull($localized->getTemplateKey());
    }

    /**
     * Every merger sees the shared row first and the localized row second, which is what lets the localized
     * value win.
     */
    public function testEachMergerSeesTheSharedRowThenTheLocalizedRow(): void
    {
        $this->store(null);
        $this->store('fr_CA');
        $first = new RecordingMerger();
        $second = new RecordingMerger();

        new ContentAggregator($this->repository, [$first, $second])->aggregate($this->root, $this->key('fr_CA'));

        self::assertSame(['*/draft/1', 'fr_CA/draft/1'], $first->seen);
        self::assertSame(['*/draft/1', 'fr_CA/draft/1'], $second->seen);
    }

    public function testALocaleWithoutItsOwnRowIsNotFoundEvenWithASharedRow(): void
    {
        $this->store(null, 'article');

        $this->expectException(ContentNotFound::class);
        $this->expectExceptionMessageIsOrContains('No content exists for the dimension "fr_CA/draft/1".');

        $this->aggregator()->aggregate($this->root, $this->key('fr_CA'));
    }

    public function testALocalizedRowWithoutASharedRowIsStillRead(): void
    {
        $this->store('fr_CA', 'article', ['title' => 'Bonjour']);

        $merged = $this->aggregator()->aggregate($this->root, $this->key('fr_CA'));

        self::assertInstanceOf(ExampleDimensionContent::class, $merged);
        self::assertSame(['title' => 'Bonjour'], $merged->getTemplateData());
    }

    public function testTheSharedRowCanBeReadAlone(): void
    {
        $this->store(null, 'article', ['image' => 'cover.png']);

        $merged = $this->aggregator()->aggregate($this->root, $this->key(null));

        self::assertInstanceOf(ExampleDimensionContent::class, $merged);
        self::assertSame('article', $merged->getTemplateKey());
    }

    public function testAMissingSharedRowIsNotFoundWhenItIsWhatWasAsked(): void
    {
        $this->expectException(ContentNotFound::class);

        $this->aggregator()->aggregate($this->root, $this->key(null));
    }

    /**
     * A row of another stage or version is never read in place of the one asked for.
     */
    public function testRowsOfAnotherStageOrVersionAreIgnored(): void
    {
        $this->repository->store($this->row(DimensionKey::localized(Locale::fromString('fr_CA'), Stage::Live, Version::first()), 'article', []));
        $this->repository->store($this->row(DimensionKey::localized(Locale::fromString('fr_CA'), Stage::Draft, Version::of(2)), 'article', []));

        $this->expectException(ContentNotFound::class);

        $this->aggregator()->aggregate($this->root, $this->key('fr_CA'));
    }

    public function testAlongAChainTheFirstLocaleWithARowIsServed(): void
    {
        $this->store(null, 'article', ['image' => 'cover.png']);
        $this->store('fr_CA', null, ['title' => 'Bonjour']);
        $this->store('en_CA', null, ['title' => 'Hello']);
        $chain = FallbackChain::of([Locale::fromString('fr'), Locale::fromString('fr_CA'), Locale::fromString('en_CA')]);

        $merged = $this->aggregator()->aggregateAlong($this->root, $chain, Stage::Draft, Version::first());

        self::assertInstanceOf(ExampleDimensionContent::class, $merged);
        self::assertSame('fr_CA', $merged->dimension()->locale?->value);
        self::assertSame(['image' => 'cover.png', 'title' => 'Bonjour'], $merged->getTemplateData());
        self::assertFalse($chain->leavesRequestedLanguage(Locale::fromString('fr_CA')));
    }

    /**
     * The shared row alone does not satisfy a chain: no locale of it has content.
     */
    public function testAChainWithNoLocalizedRowIsNotFound(): void
    {
        $this->store(null, 'article');
        $this->store('en');
        $chain = FallbackChain::of([Locale::fromString('fr'), Locale::fromString('fr_CA')]);

        $this->expectException(ContentNotFound::class);
        $this->expectExceptionMessageIsOrContains('No content exists in any locale of the chain "fr → fr_CA" for the stage "draft" and the version 1.');

        $this->aggregator()->aggregateAlong($this->root, $chain, Stage::Draft, Version::first());
    }

    /**
     * A localized key walks the mergers once per source row: a generator of mergers must still reach both.
     */
    public function testMergersGivenAsAGeneratorAreWalkedForEveryRow(): void
    {
        $this->store(null, 'article', ['image' => 'cover.png']);
        $this->store('fr_CA', null, ['title' => 'Bonjour']);
        $recording = new RecordingMerger();
        $mergers = (static function () use ($recording): iterable {
            yield new TemplateMerger();
            yield $recording;
        })();

        $merged = new ContentAggregator($this->repository, $mergers)->aggregate($this->root, $this->key('fr_CA'));

        self::assertInstanceOf(ExampleDimensionContent::class, $merged);
        self::assertSame(['image' => 'cover.png', 'title' => 'Bonjour'], $merged->getTemplateData());
        self::assertSame(['*/draft/1', 'fr_CA/draft/1'], $recording->seen);
    }

    /**
     * The row the chain chose is the row merged: read once, so a deletion in between cannot turn the chosen
     * locale into a "not found".
     */
    public function testAlongAChainEachRowIsReadOnce(): void
    {
        $this->store(null, 'article');
        $this->store('fr_CA', null, ['title' => 'Bonjour']);
        $chain = FallbackChain::of([Locale::fromString('fr'), Locale::fromString('fr_CA')]);

        $this->aggregator()->aggregateAlong($this->root, $chain, Stage::Draft, Version::first());

        self::assertSame(['fr/draft/1', 'fr_CA/draft/1', '*/draft/1'], $this->repository->lookups);
    }

    public function testAnUnlocalizedKeyReadsItsRowOnce(): void
    {
        $this->store(null, 'article');

        $this->aggregator()->aggregate($this->root, $this->key(null));

        self::assertSame(['*/draft/1'], $this->repository->lookups);
    }

    public function testALocalizedKeyReadsEachOfItsRowsOnce(): void
    {
        $this->store(null, 'article');
        $this->store('fr_CA');

        $this->aggregator()->aggregate($this->root, $this->key('fr_CA'));

        self::assertSame(['fr_CA/draft/1', '*/draft/1'], $this->repository->lookups);
    }

    /**
     * Rows read for many roots at once are merged without a lookup: the shared row first, then the localized one.
     */
    public function testRowsAlreadyLoadedAreMergedWithoutALookup(): void
    {
        $shared = $this->row($this->key(null), 'article', ['image' => 'cover.png', 'title' => 'shared']);
        $localized = $this->row($this->key('fr'), null, ['title' => 'Bonjour']);

        $merged = $this->aggregator()->mergeRows($localized, $shared);
        $alone = $this->aggregator()->mergeRows($localized, null);

        self::assertInstanceOf(ExampleDimensionContent::class, $merged);
        self::assertInstanceOf(ExampleDimensionContent::class, $alone);
        self::assertSame(['article', ['image' => 'cover.png', 'title' => 'Bonjour']], [$merged->getTemplateKey(), $merged->getTemplateData()]);
        self::assertSame([null, ['title' => 'Bonjour']], [$alone->getTemplateKey(), $alone->getTemplateData()]);
        self::assertTrue($merged->dimension()->equals($this->key('fr')));
        self::assertNotSame($localized, $alone);
        self::assertSame([], $this->repository->lookups);
    }

    public function testRowsAreMergedOverALocalizedRowOnly(): void
    {
        $this->expectException(InvalidDimension::class);
        $this->expectExceptionMessageIsOrContains('Rows are merged over a localized row; "*/draft/1" is not one.');

        $this->aggregator()->mergeRows($this->row($this->key(null), null, []), null);
    }

    public function testASharedRowOfAnotherVersionIsRefused(): void
    {
        $this->expectException(InvalidDimension::class);
        $this->expectExceptionMessageIsOrContains('The shared row "*/draft/2" is not the counterpart of "fr/draft/1" under the same root.');

        $this->aggregator()->mergeRows($this->row($this->key('fr'), null, []), $this->row(DimensionKey::unlocalized(Stage::Draft, Version::of(2)), null, []));
    }

    public function testASharedRowOfAnotherRootIsRefused(): void
    {
        $this->expectException(InvalidDimension::class);

        $this->aggregator()->mergeRows($this->row($this->key('fr'), null, []), new ExampleRoot()->createDimensionContent($this->key(null)));
    }

    /**
     * @param array<string, mixed> $templateData
     */
    private function store(?string $locale, ?string $templateKey = null, array $templateData = []): ExampleDimensionContent
    {
        $row = $this->row($this->key($locale), $templateKey, $templateData);
        $this->repository->store($row);

        return $row;
    }

    /**
     * @param array<string, mixed> $templateData
     */
    private function row(DimensionKey $key, ?string $templateKey, array $templateData): ExampleDimensionContent
    {
        $row = $this->root->createDimensionContent($key);
        $row->setTemplateKey($templateKey);
        $row->setTemplateData($templateData);

        return $row;
    }

    private function key(?string $locale): DimensionKey
    {
        return null === $locale
            ? DimensionKey::unlocalized(Stage::Draft, Version::first())
            : DimensionKey::localized(Locale::fromString($locale), Stage::Draft, Version::first());
    }

    private function aggregator(): ContentAggregator
    {
        return new ContentAggregator($this->repository, [new TemplateMerger()]);
    }
}
