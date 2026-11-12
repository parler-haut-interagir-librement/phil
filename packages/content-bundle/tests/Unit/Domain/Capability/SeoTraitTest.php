<?php

declare(strict_types=1);

namespace Phil\ContentBundle\Tests\Unit\Domain\Capability;

use Phil\ContentBundle\Domain\Capability\SeoTrait;
use Phil\ContentBundle\Domain\Dimension\DimensionKey;
use Phil\ContentBundle\Domain\Dimension\Stage;
use Phil\ContentBundle\Domain\Exception\InvalidContent;
use Phil\ContentBundle\Tests\Unit\Fixture\ExampleDimensionContent;
use Phil\ContentBundle\Tests\Unit\Fixture\ExampleRoot;
use Phil\LocaleDimensions\Locale;
use Phil\LocaleDimensions\Version;
use Phil\Url\Url;
use PHPUnit\Framework\Attributes\CoversTrait;
use PHPUnit\Framework\TestCase;

#[CoversTrait(SeoTrait::class)]
final class SeoTraitTest extends TestCase
{
    public function testANewRowSetsNothing(): void
    {
        $row = $this->row();

        self::assertNull($row->getSeoTitle());
        self::assertNull($row->getSeoDescription());
        self::assertSame([], $row->getSeoKeywords());
        self::assertNull($row->getSeoCanonicalUrl());
        self::assertNull($row->getSeoNoIndex());
        self::assertNull($row->getSeoNoFollow());
        self::assertNull($row->getSeoHideInSitemap());
    }

    public function testTheTitleIsSetAndCleared(): void
    {
        $row = $this->row();

        $row->setSeoTitle('Bonjour');
        self::assertSame('Bonjour', $row->getSeoTitle());

        $row->setSeoTitle(null);
        self::assertNull($row->getSeoTitle());
    }

    public function testTheTitleIsOneLine(): void
    {
        $this->expectException(InvalidContent::class);
        $this->expectExceptionMessageIsOrContains('The SEO title is a single line.');

        $this->row()->setSeoTitle("first\nsecond");
    }

    public function testTheDescriptionMaySpanLinesAndIsCleared(): void
    {
        $row = $this->row();

        $row->setSeoDescription("first\nsecond");
        self::assertSame("first\nsecond", $row->getSeoDescription());

        $row->setSeoDescription(null);
        self::assertNull($row->getSeoDescription());
    }

    public function testAnUntrimmedDescriptionIsRefused(): void
    {
        $this->expectException(InvalidContent::class);
        $this->expectExceptionMessageIsOrContains('The SEO description must be trimmed');

        $this->row()->setSeoDescription(' Bonjour');
    }

    public function testKeywordsKeepTheirOrderAndARepeatedOneOnce(): void
    {
        $row = $this->row();

        $row->setSeoKeywords(['larp', 'events', 'larp', '2026', '2026']);

        self::assertSame(['larp', 'events', '2026'], $row->getSeoKeywords());
    }

    public function testKeywordsAreClearedByAnEmptyList(): void
    {
        $row = $this->row();
        $row->setSeoKeywords(['larp']);

        $row->setSeoKeywords([]);

        self::assertSame([], $row->getSeoKeywords());
    }

    public function testAKeywordWithACommaIsRefused(): void
    {
        $this->expectException(InvalidContent::class);
        $this->expectExceptionMessageIsOrContains('The SEO keyword "larp, events" holds a comma');

        $this->row()->setSeoKeywords(['larp, events']);
    }

    public function testABlankKeywordIsRefused(): void
    {
        $this->expectException(InvalidContent::class);
        $this->expectExceptionMessageIsOrContains('An SEO keyword must be trimmed and not empty');

        $this->row()->setSeoKeywords(['larp', '']);
    }

    public function testARefusedKeywordLeavesTheKeywordsAlone(): void
    {
        $row = $this->row();
        $row->setSeoKeywords(['larp']);

        try {
            $row->setSeoKeywords(['events', 'a,b']);
        } catch (InvalidContent) {
        }

        self::assertSame(['larp'], $row->getSeoKeywords());
    }

    public function testTheCanonicalUrlIsSetAndCleared(): void
    {
        $row = $this->row();

        $row->setSeoCanonicalUrl(Url::fromString('https://example.org/news/one'));
        self::assertSame('https://example.org/news/one', $row->getSeoCanonicalUrl()?->toString());

        $row->setSeoCanonicalUrl(null);
        self::assertNull($row->getSeoCanonicalUrl());
    }

    public function testEachDirectiveHasThreeStates(): void
    {
        $row = $this->row();

        $row->setSeoNoIndex(true);
        $row->setSeoNoFollow(false);
        $row->setSeoHideInSitemap(true);
        self::assertTrue($row->getSeoNoIndex());
        self::assertFalse($row->getSeoNoFollow());
        self::assertTrue($row->getSeoHideInSitemap());

        $row->setSeoNoIndex(false);
        $row->setSeoNoFollow(true);
        $row->setSeoHideInSitemap(false);
        self::assertFalse($row->getSeoNoIndex());
        self::assertTrue($row->getSeoNoFollow());
        self::assertFalse($row->getSeoHideInSitemap());

        $row->setSeoNoIndex(null);
        $row->setSeoNoFollow(null);
        $row->setSeoHideInSitemap(null);
        self::assertNull($row->getSeoNoIndex());
        self::assertNull($row->getSeoNoFollow());
        self::assertNull($row->getSeoHideInSitemap());
    }

    private function row(): ExampleDimensionContent
    {
        return new ExampleRoot()->createDimensionContent(DimensionKey::localized(Locale::fromString('fr'), Stage::Draft, Version::first()));
    }
}
