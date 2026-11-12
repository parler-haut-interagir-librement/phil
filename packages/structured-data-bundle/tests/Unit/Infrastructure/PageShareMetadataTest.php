<?php

declare(strict_types=1);

namespace Phil\StructuredDataBundle\Tests\Unit\Infrastructure;

use Phil\SocialCards\ShareImage;
use Phil\StructuredData\LanguageString;
use Phil\StructuredData\Node;
use Phil\StructuredData\Reference;
use Phil\StructuredData\Type\ImageObject;
use Phil\StructuredDataBundle\Application\Contract\StructuredDataProvider;
use Phil\StructuredDataBundle\Application\PageContext;
use Phil\StructuredDataBundle\Application\PageStructuredData;
use Phil\StructuredDataBundle\Infrastructure\SocialCards\PageShareMetadata;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

#[CoversClass(PageShareMetadata::class)]
final class PageShareMetadataTest extends TestCase
{
    private const string WEBPAGE = 'https://example.com/a#webpage';

    public function testAPageWithoutNodesSaysNothing(): void
    {
        self::assertSame([], new PageShareMetadata($this->page())->values());
    }

    public function testAPageWhoseNodesSayNothingGivesNothing(): void
    {
        self::assertSame([], new PageShareMetadata($this->page(Node::of('WebPage', ['url' => 'https://example.com/a'], self::WEBPAGE), Node::of('Organization', ['name' => 'Not the page'])))->values());
    }

    public function testTheWebPageAlone(): void
    {
        $values = new PageShareMetadata($this->page(Node::of('WebPage', ['name' => 'Page', 'description' => 'About', 'primaryImageOfPage' => 'https://example.com/p.png'], self::WEBPAGE)))->values();

        self::assertSame('Page', $values['title']);
        self::assertSame('About', $values['description']);
        self::assertInstanceOf(ShareImage::class, $values['image']);
        self::assertSame('https://example.com/p.png', $values['image']->url);
    }

    public function testTheMainEntityOfTheWebPageComesFirst(): void
    {
        $values = new PageShareMetadata($this->page(
            Node::of('WebPage', ['name' => 'Page', 'description' => 'About', 'mainEntity' => new Reference('https://example.com/a#article')], self::WEBPAGE),
            Node::of('Article', ['headline' => 'Headline', 'name' => 'Name'], 'https://example.com/a#article'),
        ))->values();

        self::assertSame('Headline', $values['title']);
        self::assertSame('About', $values['description'], 'the WebPage fills what the entity lacks');
    }

    public function testAMainEntityGivenAsAList(): void
    {
        $values = new PageShareMetadata($this->page(Node::of('WebPage', ['mainEntity' => [Node::of('Product', ['name' => 'Product'])]], self::WEBPAGE)))->values();

        self::assertSame(['title' => 'Product'], $values);
    }

    public function testTheNodeThatNamesTheMainEntityOfThePage(): void
    {
        $values = new PageShareMetadata($this->page(
            Node::of('Thing', ['name' => 'Other']),
            Node::of('Article', ['name' => 'Article', 'mainEntityOfPage' => new Reference(self::WEBPAGE)]),
        ))->values();

        self::assertSame(['title' => 'Article'], $values);
    }

    public function testATextInALanguage(): void
    {
        self::assertSame(['title' => 'Titre'], new PageShareMetadata($this->page(Node::of('WebPage', ['name' => [new LanguageString('Titre', 'fr'), new LanguageString('Title', 'en')]], self::WEBPAGE)))->values());
    }

    public function testAnImageObject(): void
    {
        $values = new PageShareMetadata($this->page(Node::of('WebPage', ['image' => ImageObject::of('https://example.com/i.png', caption: 'Caption', width: 1200, height: 630)], self::WEBPAGE)))->values();

        self::assertInstanceOf(ShareImage::class, $values['image']);
        self::assertSame(['https://example.com/i.png', 'Caption', 1200, 630], [$values['image']->url, $values['image']->alt, $values['image']->width, $values['image']->height]);
    }

    public function testTheAddressOfTheFileComesBeforeThatOfItsPage(): void
    {
        $values = new PageShareMetadata($this->page(Node::of('WebPage', ['image' => Node::of('ImageObject', ['contentUrl' => 'https://example.com/file.png', 'url' => 'https://example.com/page'])], self::WEBPAGE)))->values();

        self::assertInstanceOf(ShareImage::class, $values['image']);
        self::assertSame('https://example.com/file.png', $values['image']->url);
    }

    public function testAnImageObjectReferredToAndNamedByItsUrl(): void
    {
        $values = new PageShareMetadata($this->page(
            Node::of('WebPage', ['image' => [new Reference('https://example.com/a#image')]], self::WEBPAGE),
            Node::of('ImageObject', ['url' => 'https://example.com/u.png', 'width' => '1200'], 'https://example.com/a#image'),
        ))->values();

        self::assertInstanceOf(ShareImage::class, $values['image']);
        self::assertSame('https://example.com/u.png', $values['image']->url);
        self::assertNull($values['image']->width, 'a size as text is left out');
    }

    public function testAnImageACardCouldNotNameIsLeftOut(): void
    {
        foreach (['/relative.png', 12, Node::of('ImageObject', ['caption' => 'No address']), new Reference('https://example.com/a#missing'), Node::of('ImageObject', ['url' => 'https://example.com/i.png', 'width' => 0])] as $image) {
            self::assertSame(['title' => 'Page'], new PageShareMetadata($this->page(Node::of('WebPage', ['name' => 'Page', 'image' => $image], self::WEBPAGE)))->values());
        }
    }

    public function testTheNextImageIsTriedAfterABadOne(): void
    {
        $values = new PageShareMetadata($this->page(Node::of('WebPage', ['image' => '/relative.png', 'primaryImageOfPage' => 'https://example.com/p.png'], self::WEBPAGE)))->values();

        self::assertInstanceOf(ShareImage::class, $values['image']);
        self::assertSame('https://example.com/p.png', $values['image']->url);
    }

    public function testAPageWithoutRequestIsReadToo(): void
    {
        self::assertSame([], new PageShareMetadata(new PageStructuredData())->values());
    }

    public function testItsProvidersAreAskedOnceForThePageAndItsScript(): void
    {
        $provider = new class implements StructuredDataProvider {
            public int $calls = 0;

            public function nodes(PageContext $page): iterable
            {
                ++$this->calls;

                return [Node::of('WebPage', ['name' => 'Page'], $page->pageId('webpage'))];
            }
        };
        $page = new PageStructuredData([$provider], new RequestStack([Request::create('https://example.com/a')]));

        self::assertSame(['title' => 'Page'], new PageShareMetadata($page)->values());
        $page->graph();

        self::assertSame(1, $provider->calls);
    }

    private function page(Node ...$nodes): PageStructuredData
    {
        $page = new PageStructuredData(requests: new RequestStack([Request::create('https://example.com/a')]));
        $page->add(...$nodes);

        return $page;
    }
}
