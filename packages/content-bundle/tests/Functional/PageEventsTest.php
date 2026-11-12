<?php

declare(strict_types=1);

namespace Phil\ContentBundle\Tests\Functional;

use Phil\ContentBundle\Application\Exception\PublicationLosesContent;
use Phil\ContentBundle\Application\Publication\Publication;
use Phil\ContentBundle\Application\Publication\PublicationMarkingStore;
use Phil\ContentBundle\Infrastructure\Http\ContentCanonicalAddresses;
use Phil\ContentBundle\Infrastructure\Http\ContentUrlGenerator;
use Phil\ContentBundle\Tests\Functional\App\PageEventRecorder;
use Phil\LocaleDimensions\Locale;
use Phil\Publishing\PagePublished;
use Phil\Publishing\PageRetired;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Component\Workflow\Registry;

/**
 * The events of `phil/publishing` in a kernel: the router gives the canonical addresses, the container's dispatcher
 * carries the events, and the publication workflow refuses a publication that loses content unless its context
 * accepts it.
 */
#[CoversClass(ContentCanonicalAddresses::class)]
#[CoversClass(PublicationMarkingStore::class)]
final class PageEventsTest extends FunctionalTestCase
{
    public function testPublishingAndUnpublishingDispatchTheEventsAtTheRoutesAddress(): void
    {
        $news = $this->contents()->create('news', 'fr_CA', ['slug' => 'ouverture']);

        $this->contents()->publish($news, 'fr_CA');
        $this->contents()->unpublish($news, 'fr_CA');

        self::assertCount(2, $this->events());
        self::assertInstanceOf(PagePublished::class, $this->events()[0]);
        self::assertInstanceOf(PageRetired::class, $this->events()[1]);

        foreach ($this->events() as $event) {
            self::assertSame('http://localhost/fr_CA/news/ouverture', $event->canonicalUrl->toString());
            self::assertSame('fr-CA', $event->locale);
            self::assertSame($news->toString(), $event->ownerId);
        }
    }

    public function testALocalhostAddressIsRefusedOutsideDebug(): void
    {
        $news = $this->contents()->create('news', 'fr', ['slug' => 'ouverture']);
        $this->contents()->publish($news, 'fr');
        $urls = $this->service(ContentUrlGenerator::class);

        self::assertNull(new ContentCanonicalAddresses($urls)->of($news, Locale::fromString('fr')));
        self::assertSame('http://localhost/fr/news/ouverture', new ContentCanonicalAddresses($urls, true)->of($news, Locale::fromString('fr'))?->toString());
        self::assertNull(new ContentCanonicalAddresses($urls, true)->of($news, Locale::fromString('en')), 'No route in the locale, no address.');
    }

    public function testTheWorkflowRefusesALossyPublication(): void
    {
        $news = $this->contents()->create('news', 'fr', ['slug' => 'pertes', 'bodyFormat' => 'html', 'bodySource' => '<p><object>x</object></p>']);
        $publication = Publication::of($news, 'fr');

        $this->expectException(PublicationLosesContent::class);

        $this->service(Registry::class)->get($publication)->apply($publication, 'publish');
    }

    public function testTheWorkflowPublishesALossyDraftWhenItsContextAcceptsTheLosses(): void
    {
        $news = $this->contents()->create('news', 'fr', ['slug' => 'pertes', 'bodyFormat' => 'html', 'bodySource' => '<p><object>x</object></p>']);
        $publication = Publication::of($news, 'fr');
        $workflow = $this->service(Registry::class)->get($publication);

        $workflow->apply($publication, 'publish', ['accept_losses' => true]);

        self::assertTrue($workflow->getMarking($publication)->has('published'));
        self::assertCount(1, $this->events());
    }

    /**
     * @return list<PagePublished|PageRetired>
     */
    private function events(): array
    {
        return $this->service(PageEventRecorder::class)->events();
    }
}
