<?php

declare(strict_types=1);

namespace Phil\ContentBundle\Tests\Unit\Application\Publication;

use Override;
use Phil\ContentBundle\Application\ContentManager;
use Phil\ContentBundle\Application\Exception\PublicationLosesContent;
use Phil\ContentBundle\Application\Publication\ContentPublisher;
use Phil\ContentBundle\Application\Publication\ConversionLoss;
use Phil\ContentBundle\Application\Publication\PageEvents;
use Phil\ContentBundle\Domain\Exception\TransitionRefused;
use Phil\ContentBundle\Tests\Unit\Fixture\InMemoryCanonicalAddresses;
use Phil\ContentBundle\Tests\Unit\Fixture\InMemoryContents;
use Phil\ContentBundle\Tests\Unit\Fixture\PageEventRecorder;
use Phil\LocaleDimensions\Locale;
use Phil\Publishing\PagePublished;
use Phil\Publishing\PageRetired;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * The events of `phil/publishing` around the lifecycle of a content, and the refusal of a publication that loses part
 * of its body.
 */
#[CoversClass(PageEvents::class)]
#[CoversClass(ContentManager::class)]
#[CoversClass(ContentPublisher::class)]
#[CoversClass(PublicationLosesContent::class)]
final class PageEventsTest extends TestCase
{
    private InMemoryContents $contents;

    private PageEventRecorder $recorder;

    #[Override]
    protected function setUp(): void
    {
        $this->contents = new InMemoryContents();
        $this->recorder = new PageEventRecorder();
        $this->contents->dispatcher->addListener(PagePublished::class, $this->recorder->published(...));
        $this->contents->dispatcher->addListener(PageRetired::class, $this->recorder->retired(...));
    }

    public function testPublishingDispatchesPagePublishedWithTheAddressOfTheLocale(): void
    {
        $reference = $this->contents->manager->create('example', 'fr_CA', ['slug' => 'ouverture']);

        $this->contents->manager->publish($reference, 'fr_CA');

        self::assertCount(1, $this->recorder->events());
        $event = $this->recorder->events()[0];
        self::assertInstanceOf(PagePublished::class, $event);
        self::assertSame('https://example.org/fr-CA/ouverture', $event->canonicalUrl->toString());
        self::assertSame('fr-CA', $event->locale);
        self::assertTrue($event->indexable);
        self::assertSame($reference->toString(), $event->ownerId);
    }

    public function testTheCanonicalUrlOfTheSeoWinsAndNoIndexIsNotIndexable(): void
    {
        $reference = $this->contents->manager->create('example', 'fr', ['slug' => 'ouverture', 'seoCanonicalUrl' => 'https://ailleurs.example/page', 'seoNoIndex' => true]);

        $this->contents->manager->publish($reference, 'fr');

        $event = $this->recorder->events()[0];
        self::assertSame('https://ailleurs.example/page', $event->canonicalUrl->toString());
        self::assertFalse($event->indexable);
    }

    public function testAnExplicitIndexIsIndexable(): void
    {
        $reference = $this->contents->manager->create('example', 'fr', ['slug' => 'ouverture', 'seoNoIndex' => false]);

        $this->contents->manager->publish($reference, 'fr');

        self::assertTrue($this->recorder->events()[0]->indexable);
    }

    public function testAContentWithoutAnAddressDispatchesNothing(): void
    {
        $reference = $this->contents->manager->create('example', 'fr', ['excerptTitle' => 'Sans adresse']);

        $this->contents->manager->publish($reference, 'fr');
        $this->contents->manager->unpublish($reference, 'fr');

        self::assertSame([], $this->recorder->events());
    }

    public function testUnpublishingDispatchesPageRetiredAsThePageWasOnline(): void
    {
        $reference = $this->contents->manager->create('example', 'fr', ['slug' => 'ouverture', 'seoNoIndex' => true]);
        $this->contents->manager->publish($reference, 'fr');

        $this->contents->manager->unpublish($reference, 'fr');

        self::assertCount(2, $this->recorder->events());
        $event = $this->recorder->events()[1];
        self::assertInstanceOf(PageRetired::class, $event);
        self::assertSame('https://example.org/fr/ouverture', $event->canonicalUrl->toString());
        self::assertSame('fr', $event->locale);
        self::assertFalse($event->indexable);
        self::assertSame($reference->toString(), $event->ownerId);
    }

    public function testARefusedUnpublicationDispatchesNothing(): void
    {
        $reference = $this->contents->manager->create('example', 'fr', ['slug' => 'ouverture']);

        try {
            $this->contents->manager->unpublish($reference, 'fr');
            self::fail('A locale never published cannot be taken offline.');
        } catch (TransitionRefused) {
        }

        self::assertSame([], $this->recorder->events());
    }

    public function testDeletingDispatchesPageRetiredForEveryLiveLocaleInTheDeclaredOrder(): void
    {
        $reference = $this->contents->manager->create('example', 'fr', ['slug' => 'ouverture']);
        $this->contents->manager->edit($reference, 'en', ['slug' => 'opening']);
        $this->contents->manager->edit($reference, 'fr_CA', ['slug' => 'ouverture-qc']);
        $this->contents->manager->publish($reference, 'en');
        $this->contents->manager->publish($reference, 'fr');

        $this->recorder->reset();

        $this->contents->manager->delete($reference);

        self::assertSame(
            ['https://example.org/fr/ouverture', 'https://example.org/en/opening'],
            array_map(static fn (PagePublished|PageRetired $event): string => $event->canonicalUrl->toString(), $this->recorder->events()),
        );
        self::assertContainsOnlyInstancesOf(PageRetired::class, $this->recorder->events());
    }

    public function testPublishingAgainDispatchesPagePublishedAgain(): void
    {
        $reference = $this->contents->manager->create('example', 'fr', ['slug' => 'ouverture']);
        $this->contents->manager->publish($reference, 'fr');
        $this->contents->manager->edit($reference, 'fr', ['slug' => 'grande-ouverture']);

        $this->contents->manager->publish($reference, 'fr');

        self::assertSame('https://example.org/fr/grande-ouverture', $this->recorder->events()[1]->canonicalUrl->toString());
        self::assertInstanceOf(PagePublished::class, $this->recorder->events()[1]);
    }

    public function testWithoutDispatcherOrAddressesNoEventIsBuilt(): void
    {
        $reference = $this->contents->manager->create('example', 'fr', ['slug' => 'ouverture']);
        $this->contents->manager->publish($reference, 'fr');
        $root = $this->contents->roots->find($reference);
        self::assertNotNull($root);
        $locale = Locale::fromString('fr');

        $withoutDispatcher = new PageEvents($this->contents->rows, $this->contents->aggregator, new InMemoryCanonicalAddresses($this->contents->routes));
        $withoutAddresses = new PageEvents($this->contents->rows, $this->contents->aggregator, null, $this->contents->dispatcher);

        self::assertNull($withoutDispatcher->published($root, $reference, $locale));
        self::assertNull($withoutAddresses->retired($root, $reference, $locale));
        self::assertNotNull($this->contents->pages->published($root, $reference, $locale));
        self::assertNull($this->contents->pages->published($root, $reference, Locale::fromString('en')), 'A locale that is not live has no page.');

        $withoutDispatcher->dispatch(null, $this->contents->pages->published($root, $reference, $locale));
        self::assertCount(1, $this->recorder->events(), 'Without a dispatcher, nothing is dispatched.');
    }

    public function testAPublicationThatLosesContentIsRefusedBeforeAnythingChanges(): void
    {
        $reference = $this->contents->manager->create('example', 'fr', ['slug' => 'pertes', 'bodyFormat' => 'html', 'bodySource' => '<p>a<object>x</object><object>y</object><a href="ftp://x">f</a></p>']);

        try {
            $this->contents->manager->publish($reference, 'fr');
            self::fail('The publication is refused.');
        } catch (PublicationLosesContent $publicationLosesContent) {
            self::assertSame('Publishing "fr/draft/1" would lose 3 element(s) of its body in UJO (object, a); accept the losses to publish anyway.', $publicationLosesContent->getMessage());
            self::assertSame(['object', 'object', 'a'], array_map(static fn (ConversionLoss $loss): string => $loss->element, $publicationLosesContent->losses));
        }

        self::assertSame('unpublished', $this->contents->manager->status($reference)[0]->place);
        self::assertNull($this->contents->manager->status($reference)[0]->liveVersion);
        self::assertSame([], $this->recorder->events());

        $this->contents->manager->publish($reference, 'fr', acceptLosses: true);

        self::assertSame('published', $this->contents->manager->status($reference)[0]->place);
        self::assertCount(1, $this->recorder->events());
    }

    public function testThePublisherRefusesLossesUnlessToldOtherwise(): void
    {
        $reference = $this->contents->manager->create('example', 'fr', ['slug' => 'pertes', 'bodyFormat' => 'html', 'bodySource' => '<p>a<object>x</object></p>']);
        $root = $this->contents->roots->find($reference);
        self::assertNotNull($root);

        try {
            $this->contents->publisher->publish($root, Locale::fromString('fr'));
            self::fail('The publication is refused.');
        } catch (PublicationLosesContent) {
            self::assertSame('unpublished', $this->contents->manager->status($reference)[0]->place);
        }

        $this->contents->publisher->publish($root, Locale::fromString('fr'), true);

        self::assertSame('published', $this->contents->manager->status($reference)[0]->place);
    }

    public function testABodyThatLosesNothingIsPublished(): void
    {
        $reference = $this->contents->manager->create('example', 'fr', ['slug' => 'propre', 'bodyFormat' => 'markdown', 'bodySource' => 'Le site **ouvre**.']);

        $this->contents->manager->publish($reference, 'fr');

        self::assertSame('published', $this->contents->manager->status($reference)[0]->place);
    }
}
