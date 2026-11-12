<?php

declare(strict_types=1);

namespace Phil\ContentBundle\Tests\Unit\Domain\Capability;

use Phil\ContentBundle\Domain\Capability\LinkTrait;
use Phil\ContentBundle\Domain\Dimension\DimensionKey;
use Phil\ContentBundle\Domain\Dimension\Stage;
use Phil\ContentBundle\Domain\ValueObject\Link;
use Phil\ContentBundle\Tests\Unit\Fixture\ExampleDimensionContent;
use Phil\ContentBundle\Tests\Unit\Fixture\ExampleRoot;
use Phil\LocaleDimensions\Locale;
use Phil\LocaleDimensions\Version;
use Phil\Url\Url;
use PHPUnit\Framework\Attributes\CoversTrait;
use PHPUnit\Framework\TestCase;

#[CoversTrait(LinkTrait::class)]
final class LinkTraitTest extends TestCase
{
    public function testANewRowHasNoLink(): void
    {
        self::assertNull($this->row()->getLink());
    }

    public function testALinkToAContentRootIsReadBack(): void
    {
        $row = $this->row();

        $row->setLink(Link::toContent('article', '42', Locale::fromString('fr_CA')));

        $link = $row->getLink();

        self::assertInstanceOf(Link::class, $link);
        self::assertTrue($link->isToContent());
        self::assertSame('article', $link->resourceKey);
        self::assertSame('42', $link->resourceId);
        self::assertSame('fr_CA', $link->locale?->value);
    }

    public function testALinkToAUrlIsReadBack(): void
    {
        $row = $this->row();

        $row->setLink(Link::toUrl(Url::fromString('https://example.org/')));

        self::assertSame('https://example.org/', $row->getLink()?->url?->toString());
    }

    /**
     * The four properties are written together: a URL replacing a content link leaves nothing of it.
     */
    public function testANewLinkReplacesTheWholeOldOne(): void
    {
        $row = $this->row();
        $row->setLink(Link::toContent('article', '42', Locale::fromString('fr')));

        $row->setLink(Link::toUrl(Url::fromString('https://example.org/')));

        $link = $row->getLink();

        self::assertInstanceOf(Link::class, $link);
        self::assertFalse($link->isToContent());
        self::assertNull($link->resourceKey);
        self::assertNull($link->resourceId);
        self::assertNull($link->locale);

        $row->setLink(Link::toContent('page', '7'));
        $link = $row->getLink();

        self::assertInstanceOf(Link::class, $link);
        self::assertTrue($link->isToContent());
        self::assertSame('page', $link->resourceKey);
        self::assertNull($link->url);
    }

    public function testTheLinkIsCleared(): void
    {
        $row = $this->row();
        $row->setLink(Link::toUrl(Url::fromString('https://example.org/')));

        $row->setLink(null);

        self::assertNull($row->getLink());
    }

    private function row(): ExampleDimensionContent
    {
        return new ExampleRoot()->createDimensionContent(DimensionKey::localized(Locale::fromString('fr'), Stage::Draft, Version::first()));
    }
}
