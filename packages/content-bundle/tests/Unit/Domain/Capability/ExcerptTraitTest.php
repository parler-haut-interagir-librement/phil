<?php

declare(strict_types=1);

namespace Phil\ContentBundle\Tests\Unit\Domain\Capability;

use Phil\ContentBundle\Domain\Capability\ExcerptTrait;
use Phil\ContentBundle\Domain\Dimension\DimensionKey;
use Phil\ContentBundle\Domain\Dimension\Stage;
use Phil\ContentBundle\Domain\Exception\InvalidContent;
use Phil\ContentBundle\Tests\Unit\Fixture\ExampleDimensionContent;
use Phil\ContentBundle\Tests\Unit\Fixture\ExampleRoot;
use Phil\LocaleDimensions\Version;
use PHPUnit\Framework\Attributes\CoversTrait;
use PHPUnit\Framework\TestCase;

#[CoversTrait(ExcerptTrait::class)]
final class ExcerptTraitTest extends TestCase
{
    public function testANewRowSetsNothing(): void
    {
        $row = $this->row();

        self::assertNull($row->getExcerptTitle());
        self::assertNull($row->getExcerptDescription());
        self::assertNull($row->getExcerptImage());
    }

    public function testEachValueIsSetAndCleared(): void
    {
        $row = $this->row();

        $row->setExcerptTitle('A first article');
        $row->setExcerptDescription("It begins.\nThen it goes on.");
        $row->setExcerptImage('/images/cover.png');
        self::assertSame('A first article', $row->getExcerptTitle());
        self::assertSame("It begins.\nThen it goes on.", $row->getExcerptDescription());
        self::assertSame('/images/cover.png', $row->getExcerptImage());

        $row->setExcerptTitle(null);
        $row->setExcerptDescription(null);
        $row->setExcerptImage(null);
        self::assertNull($row->getExcerptTitle());
        self::assertNull($row->getExcerptDescription());
        self::assertNull($row->getExcerptImage());
    }

    public function testTheTitleIsOneLine(): void
    {
        $this->expectException(InvalidContent::class);
        $this->expectExceptionMessageIsOrContains('The excerpt title is a single line.');

        $this->row()->setExcerptTitle("first\nsecond");
    }

    public function testAnUntrimmedDescriptionIsRefused(): void
    {
        $this->expectException(InvalidContent::class);
        $this->expectExceptionMessageIsOrContains('The excerpt description must be trimmed');

        $this->row()->setExcerptDescription('Bonjour ');
    }

    public function testAnImageWithASpaceIsRefused(): void
    {
        $this->expectException(InvalidContent::class);
        $this->expectExceptionMessageIsOrContains('The excerpt image must be 1 to 2048 printable ASCII characters');

        $this->row()->setExcerptImage('/images/my cover.png');
    }

    private function row(): ExampleDimensionContent
    {
        return new ExampleRoot()->createDimensionContent(DimensionKey::unlocalized(Stage::Draft, Version::first()));
    }
}
