<?php

declare(strict_types=1);

namespace Phil\ContentBundle\Tests\Unit\Application\Publication;

use Override;
use Phil\ContentBundle\Application\ContentManager;
use Phil\ContentBundle\Application\ContentStatus;
use Phil\ContentBundle\Application\Editing\ContentEditor;
use Phil\ContentBundle\Application\Publication\ContentPublisher;
use Phil\ContentBundle\Application\Publication\ContentVersions;
use Phil\ContentBundle\Domain\Dimension\DimensionKey;
use Phil\ContentBundle\Domain\Dimension\Stage;
use Phil\ContentBundle\Domain\Exception\TransitionRefused;
use Phil\ContentBundle\Domain\ValueObject\ContentReference;
use Phil\ContentBundle\Tests\Unit\Fixture\ExampleDimensionContent;
use Phil\ContentBundle\Tests\Unit\Fixture\ExampleRoot;
use Phil\ContentBundle\Tests\Unit\Fixture\InMemoryContents;
use Phil\LocaleDimensions\Version;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * Publication with several locales on one root: the shared row of a version is read by every locale that has
 * a row there, so publishing, unpublishing, forking and discarding one locale must leave the others' values
 * where they are.
 */
#[CoversClass(ContentPublisher::class)]
#[CoversClass(ContentEditor::class)]
#[CoversClass(ContentVersions::class)]
final class ContentPublisherTest extends TestCase
{
    private InMemoryContents $contents;

    private ContentManager $manager;

    #[Override]
    protected function setUp(): void
    {
        $this->contents = new InMemoryContents();
        $this->manager = $this->contents->manager;
    }

    /**
     * The shared change of `en` is pending in version 2; discarding the draft of `fr` would put the shared row
     * of version 1 back and lose it.
     */
    public function testRemovingADraftIsRefusedWhenAnotherLocaleReadsChangedSharedValues(): void
    {
        $reference = $this->publishedInTwoLocales();
        $this->manager->edit($reference, 'en', ['templateKey' => 'page']);
        $this->manager->edit($reference, 'fr', ['excerptTitle' => 'Brouillon']);

        try {
            $this->manager->removeDraft($reference, 'fr');
            self::fail('The removal is refused.');
        } catch (TransitionRefused $transitionRefused) {
            self::assertSame('The draft of "fr/draft/2" cannot be removed: its shared values changed since it was published, and the locales "en" read them too.', $transitionRefused->getMessage());
        }

        self::assertSame('page', $this->manager->draft($reference, 'en')['templateKey']);
        self::assertSame('Brouillon', $this->manager->draft($reference, 'fr')['excerptTitle']);
        self::assertSame(['en' => 'draft', 'fr' => 'draft'], $this->places($reference), 'A refusal leaves the place of the row as it was.');
    }

    /**
     * Every shared value counts, not only the template key: the excerpt image is shared too.
     */
    public function testRemovingADraftIsRefusedWhenAnyOtherSharedValueChanged(): void
    {
        $reference = $this->publishedInTwoLocales();
        $this->manager->edit($reference, 'en', ['excerptImage' => '/images/ouverture.png']);
        $this->manager->edit($reference, 'fr', ['excerptTitle' => 'Brouillon']);

        $this->expectException(TransitionRefused::class);

        $this->manager->removeDraft($reference, 'fr');
    }

    public function testTheRefusalNamesEveryOtherLocaleOfTheVersion(): void
    {
        $reference = $this->publishedInTwoLocales();
        $this->manager->edit($reference, 'fr_CA', ['excerptTitle' => 'Allô']);
        $this->manager->edit($reference, 'en', ['templateKey' => 'page']);

        $this->expectException(TransitionRefused::class);
        $this->expectExceptionMessageIsOrContains('the locales "en", "fr_CA" read them too.');

        $this->manager->removeDraft($reference, 'fr');
    }

    public function testRemovingADraftKeepsTheLocalizedChangesOfAnotherLocale(): void
    {
        $reference = $this->publishedInTwoLocales();
        $this->manager->edit($reference, 'en', ['excerptTitle' => 'Hello again']);
        $this->manager->edit($reference, 'fr', ['excerptTitle' => 'Brouillon']);

        $this->manager->removeDraft($reference, 'fr');

        self::assertSame('Bonjour', $this->manager->draft($reference, 'fr')['excerptTitle']);
        self::assertSame('news', $this->manager->draft($reference, 'fr')['templateKey']);
        self::assertSame('Hello again', $this->manager->draft($reference, 'en')['excerptTitle']);
        self::assertSame(['en' => 'draft', 'fr' => 'published'], $this->places($reference));
        self::assertSame(['*/draft/1', '*/draft/2', '*/live/1', 'en/draft/1', 'en/draft/2', 'en/live/1', 'fr/draft/1', 'fr/draft/2', 'fr/live/1'], $this->contents->rows->keys());
    }

    /**
     * The shared row of the locale's own fork changed: with no other locale in the version, it is put back.
     */
    public function testRemovingTheDraftOfTheOnlyLocaleOfTheVersionPutsTheSharedRowBack(): void
    {
        $reference = $this->manager->create('example', 'fr', ['templateKey' => 'news']);
        $this->manager->publish($reference, 'fr');
        $this->manager->edit($reference, 'fr', ['templateKey' => 'page']);

        $this->manager->removeDraft($reference, 'fr');

        self::assertSame('news', $this->manager->draft($reference, 'fr')['templateKey']);
    }

    /**
     * The shared values come back from the frozen shared draft row of the published version, not from its live
     * twin: the live copy is changed here so the two differ.
     */
    public function testRemovingTheDraftPutsTheSharedValuesOfThePublishedDraftBack(): void
    {
        $reference = $this->manager->create('example', 'fr', ['templateKey' => 'news']);
        $this->manager->publish($reference, 'fr');
        $this->manager->edit($reference, 'fr', ['templateKey' => 'page']);
        $this->sharedRow($reference, Stage::Live)->setTemplateKey('live');

        $this->manager->removeDraft($reference, 'fr');

        self::assertSame('news', $this->manager->draft($reference, 'fr')['templateKey']);
    }

    /**
     * Another locale reads the shared row of the version: it holds the values of the published draft, so nothing
     * pending is lost and the removal goes through, whatever the live copy holds.
     */
    public function testRemovingADraftComparesTheSharedRowWithTheOneItWouldPutBack(): void
    {
        $reference = $this->publishedInTwoLocales();
        $this->manager->edit($reference, 'fr', ['excerptTitle' => 'Brouillon']);
        $this->sharedRow($reference, Stage::Live)->setTemplateKey('live');

        $this->manager->removeDraft($reference, 'fr');

        self::assertSame('Bonjour', $this->manager->draft($reference, 'fr')['excerptTitle']);
        self::assertSame('news', $this->manager->draft($reference, 'fr')['templateKey']);
        self::assertSame(['en' => 'draft', 'fr' => 'published'], $this->places($reference));
    }

    /**
     * Without the frozen shared draft row of the published version, its live twin is put back.
     */
    public function testRemovingTheDraftPutsTheLiveSharedRowBackWithoutTheFrozenOne(): void
    {
        $reference = $this->manager->create('example', 'fr', ['templateKey' => 'news']);
        $this->manager->publish($reference, 'fr');
        $this->manager->edit($reference, 'fr', ['templateKey' => 'page']);

        $this->contents->rows->remove($this->sharedRow($reference, Stage::Draft));
        $this->contents->rows->flush();

        $this->manager->removeDraft($reference, 'fr');

        self::assertSame('news', $this->manager->draft($reference, 'fr')['templateKey']);
    }

    /**
     * Publishing one locale of a fork publishes the shared row of the fork; the other locale stays live on
     * the version it was published from, over the shared row of that version.
     */
    public function testPublishingOneLocaleOfAForkLeavesTheOtherOnItsVersion(): void
    {
        $reference = $this->publishedInTwoLocales();
        $this->manager->edit($reference, 'fr', ['templateKey' => 'page', 'excerptTitle' => 'Salut']);

        $this->manager->publish($reference, 'fr');

        self::assertSame('page', $this->manager->live($reference, 'fr')['templateKey']);
        self::assertSame('news', $this->manager->live($reference, 'en')['templateKey']);
        self::assertSame(['*/draft/1', '*/draft/2', '*/live/1', '*/live/2', 'en/draft/1', 'en/draft/2', 'en/live/1', 'fr/draft/1', 'fr/draft/2', 'fr/live/2'], $this->contents->rows->keys());
        self::assertEquals([
            new ContentStatus($reference, 'en', 2, 'draft', null, 1),
            new ContentStatus($reference, 'fr', 2, 'published', null, 2),
        ], $this->manager->status($reference));
    }

    /**
     * A shared value is shared: a change written while editing `en` is published with `fr`, since both read
     * the shared row of version 2.
     */
    public function testASharedChangeIsPublishedWithAnyLocaleOfItsVersion(): void
    {
        $reference = $this->publishedInTwoLocales();
        $this->manager->edit($reference, 'en', ['templateKey' => 'page']);

        $this->manager->publish($reference, 'fr');

        self::assertSame('page', $this->manager->live($reference, 'fr')['templateKey']);
        self::assertSame('news', $this->manager->live($reference, 'en')['templateKey']);
    }

    public function testUnpublishingTheLastLocaleOfAVersionRemovesItsLiveSharedRowOnly(): void
    {
        $reference = $this->publishedInTwoLocales();
        $this->manager->edit($reference, 'fr', ['excerptTitle' => 'Salut']);
        $this->manager->publish($reference, 'fr');

        $this->manager->unpublish($reference, 'en');

        self::assertSame(['*/draft/1', '*/draft/2', '*/live/2', 'en/draft/1', 'en/draft/2', 'fr/draft/1', 'fr/draft/2', 'fr/live/2'], $this->contents->rows->keys());
        self::assertSame('Salut', $this->manager->live($reference, 'fr')['excerptTitle']);
    }

    /**
     * A fork copies every locale of the version; each published locale moves to `draft`, and an edit of one
     * locale writes none of the other's values.
     */
    public function testAForkCopiesEveryLocaleAndAnEditWritesOneOnly(): void
    {
        $reference = $this->publishedInTwoLocales();

        $this->manager->edit($reference, 'fr', ['excerptTitle' => 'Salut']);

        self::assertSame(['en' => 'draft', 'fr' => 'draft'], $this->places($reference));
        self::assertSame('Hello', $this->manager->draft($reference, 'en')['excerptTitle']);
        self::assertSame('Salut', $this->manager->draft($reference, 'fr')['excerptTitle']);
        self::assertNull($this->manager->draft($reference, 'en')['workflowPublished'], 'The fork is not frozen.');
    }

    private function publishedInTwoLocales(): ContentReference
    {
        $reference = $this->manager->create('example', 'fr', ['templateKey' => 'news', 'excerptTitle' => 'Bonjour']);
        $this->manager->edit($reference, 'en', ['excerptTitle' => 'Hello']);
        $this->manager->publish($reference, 'fr');
        $this->manager->publish($reference, 'en');

        return $reference;
    }

    /**
     * The shared row of version 1 in `$stage`.
     */
    private function sharedRow(ContentReference $reference, Stage $stage): ExampleDimensionContent
    {
        $root = $this->contents->roots->find($reference);
        self::assertInstanceOf(ExampleRoot::class, $root);
        $row = $this->contents->rows->find($root, DimensionKey::unlocalized($stage, Version::first()));
        self::assertInstanceOf(ExampleDimensionContent::class, $row);

        return $row;
    }

    /**
     * @return array<string, string|null>
     */
    private function places(ContentReference $reference): array
    {
        $places = [];

        foreach ($this->manager->status($reference) as $status) {
            $places[$status->locale] = $status->place;
        }

        return $places;
    }
}
