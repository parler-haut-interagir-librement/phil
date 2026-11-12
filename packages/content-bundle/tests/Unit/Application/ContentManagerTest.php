<?php

declare(strict_types=1);

namespace Phil\ContentBundle\Tests\Unit\Application;

use Override;
use Phil\ContentBundle\Application\ContentManager;
use Phil\ContentBundle\Application\ContentStatus;
use Phil\ContentBundle\Application\Editing\ContentEditor;
use Phil\ContentBundle\Application\Publication\ContentPublisher;
use Phil\ContentBundle\Application\Publication\ContentVersions;
use Phil\ContentBundle\Application\Publication\DimensionContentCopier;
use Phil\ContentBundle\Application\Publication\LiveContentReader;
use Phil\ContentBundle\Application\Routing\RouteUpdater;
use Phil\ContentBundle\Domain\Content\ContentRoot;
use Phil\ContentBundle\Domain\Dimension\DimensionKey;
use Phil\ContentBundle\Domain\Dimension\Stage;
use Phil\ContentBundle\Domain\Exception\ContentNotFound;
use Phil\ContentBundle\Domain\Exception\InvalidDimension;
use Phil\ContentBundle\Domain\Exception\SlugTaken;
use Phil\ContentBundle\Domain\Exception\TransitionRefused;
use Phil\ContentBundle\Domain\ValueObject\ContentReference;
use Phil\ContentBundle\Tests\Unit\Fixture\ExampleDimensionContent;
use Phil\ContentBundle\Tests\Unit\Fixture\InMemoryContents;
use Phil\LocaleDimensions\Locale;
use Phil\LocaleDimensions\Version;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * The publication cycle end to end, over in-memory ports: create, publish, edit a published version, publish
 * again, take offline, discard a draft, and the routes each step leaves.
 */
#[CoversClass(ContentManager::class)]
#[CoversClass(ContentStatus::class)]
#[CoversClass(ContentEditor::class)]
#[CoversClass(ContentPublisher::class)]
#[CoversClass(ContentVersions::class)]
#[CoversClass(DimensionContentCopier::class)]
#[CoversClass(LiveContentReader::class)]
#[CoversClass(RouteUpdater::class)]
final class ContentManagerTest extends TestCase
{
    private InMemoryContents $contents;

    private ContentManager $manager;

    #[Override]
    protected function setUp(): void
    {
        $this->contents = new InMemoryContents();
        $this->manager = $this->contents->manager;
    }

    public function testCreatingWritesTheFirstDraftUnpublished(): void
    {
        $reference = $this->manager->create('example', 'fr', ['templateKey' => 'news', 'slug' => 'ouverture', 'excerptTitle' => 'Ouverture']);

        self::assertSame('example:1', $reference->toString());
        self::assertSame(['*/draft/1', 'fr/draft/1'], $this->contents->rows->keys());
        self::assertSame([], $this->contents->routes->describe(), 'A draft has no route.');
        self::assertEquals([new ContentStatus($reference, 'fr', 1, 'unpublished', 'ouverture', null)], $this->manager->status($reference));

        $draft = $this->manager->draft($reference, 'fr');
        self::assertSame('news', $draft['templateKey']);
        self::assertSame('Ouverture', $draft['excerptTitle']);
        self::assertSame(1, $this->contents->transaction->runs);
    }

    public function testPublishingCopiesTheDraftLiveStampsItAndRoutesItsSlug(): void
    {
        $reference = $this->manager->create('example', 'fr', ['templateKey' => 'news', 'slug' => 'ouverture', 'excerptTitle' => 'Ouverture']);

        $this->manager->publish($reference, 'fr');

        self::assertSame(['*/draft/1', '*/live/1', 'fr/draft/1', 'fr/live/1'], $this->contents->rows->keys());
        self::assertSame(['fr/ouverture → example:1'], $this->contents->routes->describe());
        self::assertEquals([new ContentStatus($reference, 'fr', 1, 'published', 'ouverture', 1)], $this->manager->status($reference));

        $live = $this->manager->live($reference, 'fr');
        self::assertSame('news', $live['templateKey']);
        self::assertSame('Ouverture', $live['excerptTitle']);
        self::assertSame('published', $live['workflowPlace']);
        self::assertSame('2026-10-31T16:00:00+00:00', $live['workflowPublished'], 'The publication date is stored in UTC.');

        $draft = $this->row($reference, DimensionKey::unlocalized(Stage::Draft, Version::first()));
        self::assertNotNull($draft->getWorkflowPublished(), 'The shared draft row is stamped too.');
    }

    public function testPublishingWhatIsPublishedIsRefused(): void
    {
        $reference = $this->manager->create('example', 'fr', []);
        $this->manager->publish($reference, 'fr');

        $this->expectException(TransitionRefused::class);
        $this->expectExceptionMessageIsOrContains('The transition "publish" is not allowed from the place "published" of "fr/draft/1".');

        $this->manager->publish($reference, 'fr');
    }

    public function testEditingAPublishedVersionForksTheNextAndLeavesReadersOnThePublishedOne(): void
    {
        $reference = $this->manager->create('example', 'fr', ['slug' => 'ouverture', 'excerptTitle' => 'Ouverture']);
        $this->manager->publish($reference, 'fr');

        $this->manager->edit($reference, 'fr', ['excerptTitle' => 'Grande ouverture']);

        self::assertSame(['*/draft/1', '*/draft/2', '*/live/1', 'fr/draft/1', 'fr/draft/2', 'fr/live/1'], $this->contents->rows->keys());
        self::assertSame('Ouverture', $this->row($reference, DimensionKey::localized(Locale::fromString('fr'), Stage::Draft, Version::first()))->getExcerptTitle(), 'The frozen version is never written.');
        self::assertSame('Grande ouverture', $this->manager->draft($reference, 'fr')['excerptTitle']);
        self::assertSame('Ouverture', $this->manager->live($reference, 'fr')['excerptTitle']);
        self::assertEquals([new ContentStatus($reference, 'fr', 2, 'draft', 'ouverture', 1)], $this->manager->status($reference));
        self::assertNull($this->row($reference, DimensionKey::localized(Locale::fromString('fr'), Stage::Draft, Version::of(2)))->getWorkflowPublished(), 'The fork is not frozen.');
    }

    public function testEditingAnUnpublishedVersionWritesItInPlace(): void
    {
        $reference = $this->manager->create('example', 'fr', ['excerptTitle' => 'Un']);

        $this->manager->edit($reference, 'fr', ['excerptTitle' => 'Deux']);
        $this->manager->edit($reference, 'fr', ['excerptTitle' => 'Trois']);

        self::assertSame(['*/draft/1', 'fr/draft/1'], $this->contents->rows->keys());
        self::assertSame('Trois', $this->manager->draft($reference, 'fr')['excerptTitle']);
    }

    public function testPublishingTheForkReplacesTheLiveRowsAndRedirectsTheOldSlug(): void
    {
        $reference = $this->manager->create('example', 'fr', ['slug' => 'ouverture', 'excerptTitle' => 'Ouverture']);
        $this->manager->publish($reference, 'fr');
        $this->manager->edit($reference, 'fr', ['slug' => 'grande-ouverture', 'excerptTitle' => 'Grande ouverture']);

        $this->manager->publish($reference, 'fr');

        self::assertSame(['*/draft/1', '*/draft/2', '*/live/2', 'fr/draft/1', 'fr/draft/2', 'fr/live/2'], $this->contents->rows->keys());
        self::assertSame('Grande ouverture', $this->manager->live($reference, 'fr')['excerptTitle']);
        self::assertSame(['fr/grande-ouverture → example:1', 'fr/ouverture → example:1 (history)'], $this->contents->routes->describe());
    }

    public function testASlugComesBackToItsRoot(): void
    {
        $reference = $this->manager->create('example', 'fr', ['slug' => 'ouverture']);
        $this->manager->publish($reference, 'fr');
        $this->manager->edit($reference, 'fr', ['slug' => 'grande-ouverture']);
        $this->manager->publish($reference, 'fr');
        $this->manager->edit($reference, 'fr', ['slug' => 'ouverture']);

        $this->manager->publish($reference, 'fr');

        self::assertSame(['fr/grande-ouverture → example:1 (history)', 'fr/ouverture → example:1'], $this->contents->routes->describe());
    }

    public function testUnpublishingTakesTheLocaleOfflineAndKeepsItsRoute(): void
    {
        $reference = $this->manager->create('example', 'fr', ['slug' => 'ouverture']);
        $this->manager->publish($reference, 'fr');

        $this->manager->unpublish($reference, 'fr');

        self::assertSame(['*/draft/1', 'fr/draft/1'], $this->contents->rows->keys(), 'The live shared row no locale reads is removed.');
        self::assertSame(['fr/ouverture → example:1'], $this->contents->routes->describe());
        self::assertEquals([new ContentStatus($reference, 'fr', 1, 'unpublished', 'ouverture', null)], $this->manager->status($reference));

        $this->expectException(ContentNotFound::class);

        $this->manager->live($reference, 'fr');
    }

    public function testUnpublishingWhatIsNotPublishedIsRefused(): void
    {
        $reference = $this->manager->create('example', 'fr', []);

        $this->expectException(TransitionRefused::class);

        $this->manager->unpublish($reference, 'fr');
    }

    public function testAnUnpublishedLocaleIsPublishedAgainFromItsFrozenVersion(): void
    {
        $reference = $this->manager->create('example', 'fr', ['excerptTitle' => 'Un']);
        $this->manager->publish($reference, 'fr');
        $this->manager->unpublish($reference, 'fr');

        $this->manager->publish($reference, 'fr');

        self::assertSame(['*/draft/1', '*/live/1', 'fr/draft/1', 'fr/live/1'], $this->contents->rows->keys());
    }

    public function testRemovingTheDraftPutsThePublishedValuesBack(): void
    {
        $reference = $this->manager->create('example', 'fr', ['templateKey' => 'news', 'excerptTitle' => 'Ouverture']);
        $this->manager->publish($reference, 'fr');
        $this->manager->edit($reference, 'fr', ['templateKey' => 'page', 'excerptTitle' => 'Brouillon']);

        $this->manager->removeDraft($reference, 'fr');

        $draft = $this->manager->draft($reference, 'fr');
        self::assertSame('Ouverture', $draft['excerptTitle']);
        self::assertSame('news', $draft['templateKey'], 'The shared row is put back as well.');
        self::assertSame('published', $draft['workflowPlace']);
        self::assertEquals([new ContentStatus($reference, 'fr', 2, 'published', null, 1)], $this->manager->status($reference));

        $this->manager->edit($reference, 'fr', ['excerptTitle' => 'Encore']);
        self::assertSame(3, $this->contents->versions->latestDraft($this->root($reference))?->number, 'The restored version is frozen, so the next edit forks again.');
    }

    /**
     * `en` changed a shared value in version 2: discarding the draft of `fr` would put back the shared row `fr`
     * was published from, and lose it.
     */
    public function testRemovingTheDraftOfOneLocaleNeverLosesTheSharedChangesOfAnother(): void
    {
        $reference = $this->manager->create('example', 'fr', ['templateKey' => 'news']);
        $this->manager->edit($reference, 'en', []);
        $this->manager->publish($reference, 'fr');
        $this->manager->publish($reference, 'en');
        $this->manager->edit($reference, 'en', ['templateKey' => 'page']);
        $this->manager->edit($reference, 'fr', []);

        try {
            $this->manager->removeDraft($reference, 'fr');
            self::fail('The removal is refused.');
        } catch (TransitionRefused) {
        }

        self::assertSame('page', $this->manager->draft($reference, 'en')['templateKey']);
    }

    public function testRemovingADraftThatDoesNotExistIsRefused(): void
    {
        $reference = $this->manager->create('example', 'fr', []);
        $this->manager->publish($reference, 'fr');

        $this->expectException(TransitionRefused::class);

        $this->manager->removeDraft($reference, 'fr');
    }

    public function testRemovingTheDraftOfALocaleNeverPublishedIsNotFound(): void
    {
        $reference = $this->manager->create('example', 'fr', []);

        $this->expectException(ContentNotFound::class);
        $this->expectExceptionMessageIsOrContains('No content exists in the stage "live" for the locale "fr".');

        $this->manager->removeDraft($reference, 'fr');
    }

    /**
     * Locales are published one at a time: each is served from the version it was published from.
     */
    public function testEachLocaleIsServedFromTheVersionItWasPublishedFrom(): void
    {
        $reference = $this->manager->create('example', 'fr', ['templateKey' => 'news', 'excerptTitle' => 'Bonjour']);
        $this->manager->publish($reference, 'fr');

        $this->manager->edit($reference, 'en', ['excerptTitle' => 'Hello']);

        self::assertEquals([
            new ContentStatus($reference, 'en', 2, 'unpublished', null, null),
            new ContentStatus($reference, 'fr', 2, 'draft', null, 1),
        ], $this->manager->status($reference), 'Editing a frozen version forks it, and the published locale moves to draft.');

        $this->manager->publish($reference, 'en');

        self::assertSame(['*/draft/1', '*/draft/2', '*/live/1', '*/live/2', 'en/draft/2', 'en/live/2', 'fr/draft/1', 'fr/draft/2', 'fr/live/1'], $this->contents->rows->keys());
        self::assertSame('Bonjour', $this->manager->live($reference, 'fr')['excerptTitle']);
        self::assertSame('Hello', $this->manager->live($reference, 'en')['excerptTitle']);
        self::assertSame('news', $this->manager->live($reference, 'en')['templateKey']);

        $this->manager->publish($reference, 'fr');

        self::assertSame(['*/draft/1', '*/draft/2', '*/live/2', 'en/draft/2', 'en/live/2', 'fr/draft/1', 'fr/draft/2', 'fr/live/2'], $this->contents->rows->keys(), 'The live shared row of version 1 is read by no locale any more.');
    }

    public function testAReaderIsServedAlongTheFallbackChain(): void
    {
        $reference = $this->manager->create('example', 'fr', ['excerptTitle' => 'Bonjour']);
        $this->manager->publish($reference, 'fr');

        $live = $this->manager->live($reference, 'fr_CA');

        self::assertSame('Bonjour', $live['excerptTitle']);
    }

    public function testALocaleThatIsNotServedIsRefused(): void
    {
        $this->expectException(InvalidDimension::class);
        $this->expectExceptionMessageIsOrContains('The locale "de" is not served.');

        $this->manager->create('example', 'de', []);
    }

    public function testASlugTakenByAnotherRootIsRefused(): void
    {
        $first = $this->manager->create('example', 'fr', ['slug' => 'ouverture']);
        $this->manager->publish($first, 'fr');

        $this->expectException(SlugTaken::class);
        $this->expectExceptionMessageIsOrContains('The slug "ouverture" is already the address of "example:1" in the locale "fr".');

        $this->manager->create('example', 'fr', ['slug' => 'ouverture']);
    }

    public function testTheSameSlugInAnotherLocaleIsFree(): void
    {
        $first = $this->manager->create('example', 'fr', ['slug' => 'ouverture']);
        $this->manager->publish($first, 'fr');
        $second = $this->manager->create('example', 'en', ['slug' => 'ouverture']);

        $this->manager->publish($second, 'en');

        self::assertSame(['en/ouverture → example:2', 'fr/ouverture → example:1'], $this->contents->routes->describe());
    }

    public function testAHistorySlugOfAnotherRootIsTakenOver(): void
    {
        $first = $this->manager->create('example', 'fr', ['slug' => 'ouverture']);
        $this->manager->publish($first, 'fr');
        $this->manager->edit($first, 'fr', ['slug' => 'archives']);
        $this->manager->publish($first, 'fr');

        $second = $this->manager->create('example', 'fr', ['slug' => 'ouverture']);
        $this->manager->publish($second, 'fr');

        self::assertSame(['fr/archives → example:1', 'fr/ouverture → example:2'], $this->contents->routes->describe());
    }

    public function testAnUnknownRootIsNotFound(): void
    {
        $this->expectException(ContentNotFound::class);
        $this->expectExceptionMessageIsOrContains('No content root "example:9" exists.');

        $this->manager->edit(ContentReference::of('example', '9'), 'fr', []);
    }

    public function testTheSharedRowHasNoWorkflowPlace(): void
    {
        $reference = $this->manager->create('example', 'fr', []);

        self::assertNull($this->row($reference, DimensionKey::unlocalized(Stage::Draft, Version::first()))->getWorkflowPlace());
        self::assertSame('unpublished', $this->row($reference, DimensionKey::localized(Locale::fromString('fr'), Stage::Draft, Version::first()))->getWorkflowPlace());
    }

    /**
     * Only the published locales of a forked version move to `draft`; an unpublished one stays where it is.
     */
    public function testForkingLeavesAnUnpublishedLocaleUnpublished(): void
    {
        $reference = $this->manager->create('example', 'fr', ['excerptTitle' => 'Bonjour']);
        $this->manager->edit($reference, 'en', ['excerptTitle' => 'Hello']);
        $this->manager->publish($reference, 'fr');

        $this->manager->edit($reference, 'fr', ['excerptTitle' => 'Salut']);

        self::assertEquals([
            new ContentStatus($reference, 'en', 2, 'unpublished', null, null),
            new ContentStatus($reference, 'fr', 2, 'draft', null, 1),
        ], $this->manager->status($reference));
    }

    /**
     * Two locales live from one version share its live shared row: unpublishing one keeps it for the other.
     */
    public function testUnpublishingOneLocaleKeepsTheSharedRowTheOtherReads(): void
    {
        $reference = $this->manager->create('example', 'fr', ['templateKey' => 'news']);
        $this->manager->edit($reference, 'en', []);
        $this->manager->publish($reference, 'fr');
        $this->manager->publish($reference, 'en');

        $this->manager->unpublish($reference, 'en');

        self::assertSame(['*/draft/1', '*/live/1', 'en/draft/1', 'fr/draft/1', 'fr/live/1'], $this->contents->rows->keys());
        self::assertSame('news', $this->manager->live($reference, 'fr')['templateKey']);
    }

    public function testTheDraftOfARootWithoutRowsIsNotFound(): void
    {
        $this->contents->roots->add($this->contents->roots->create('example'));
        $this->contents->transaction->flush();

        $this->expectException(ContentNotFound::class);
        $this->expectExceptionMessageIsOrContains('No content exists in the stage "draft" for the locale "fr".');

        $this->manager->draft(ContentReference::of('example', '1'), 'fr');
    }

    public function testPublishingARootWithoutRowsIsNotFound(): void
    {
        $this->contents->roots->add($this->contents->roots->create('example'));
        $this->contents->transaction->flush();

        $this->expectException(ContentNotFound::class);
        $this->expectExceptionMessageIsOrContains('No content exists in the stage "draft" for the locale "fr".');

        $this->manager->publish(ContentReference::of('example', '1'), 'fr');
    }

    public function testPublishingALocaleWithoutDraftIsNotFound(): void
    {
        $reference = $this->manager->create('example', 'fr', []);

        $this->expectException(ContentNotFound::class);
        $this->expectExceptionMessageIsOrContains('No content exists for the dimension "en/draft/1".');

        $this->manager->publish($reference, 'en');
    }

    public function testDeletingARootRemovesItsRowsAndRoutesAndFreesItsSlugs(): void
    {
        $first = $this->manager->create('example', 'fr', ['slug' => 'ouverture']);
        $this->manager->publish($first, 'fr');
        $this->manager->edit($first, 'fr', ['slug' => 'grande-ouverture']);
        $this->manager->publish($first, 'fr');
        $this->manager->edit($first, 'en', ['slug' => 'opening']);

        $kept = $this->manager->create('example', 'fr', ['slug' => 'autre']);

        $this->manager->delete($first);

        self::assertSame(['*/draft/1', 'fr/draft/1'], $this->contents->rows->keys(), 'Only the rows of the other root are left.');
        self::assertSame([], $this->contents->routes->describe());
        self::assertSame([$kept->toString()], array_map(static fn (ContentReference $reference): string => $reference->toString(), $this->manager->list('example')));

        $second = $this->manager->create('example', 'fr', ['slug' => 'grande-ouverture']);
        $this->manager->publish($second, 'fr');

        self::assertSame(['fr/grande-ouverture → example:3'], $this->contents->routes->describe());
    }

    public function testDeletingAnUnknownRootIsNotFound(): void
    {
        $this->expectException(ContentNotFound::class);

        $this->manager->delete(ContentReference::of('example', '9'));
    }

    public function testTheRootsOfAKindAreListed(): void
    {
        $this->manager->create('example', 'fr', []);
        $this->manager->create('example', 'en', []);

        self::assertSame(['example:1', 'example:2'], array_map(static fn (ContentReference $reference): string => $reference->toString(), $this->manager->list('example')));
    }

    public function testARootWithoutDraftHasNoStatus(): void
    {
        $root = $this->contents->roots->create('example');
        $this->contents->roots->add($root);
        $this->contents->transaction->flush();

        self::assertSame([], $this->manager->status(ContentReference::of('example', '1')));
    }

    private function root(ContentReference $reference): ContentRoot
    {
        return $this->contents->roots->find($reference) ?? self::fail('No root.');
    }

    private function row(ContentReference $reference, DimensionKey $key): ExampleDimensionContent
    {
        $row = $this->contents->rows->find($this->root($reference), $key);
        self::assertInstanceOf(ExampleDimensionContent::class, $row);

        return $row;
    }
}
