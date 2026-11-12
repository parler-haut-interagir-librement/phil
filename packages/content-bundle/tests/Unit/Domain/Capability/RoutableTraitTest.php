<?php

declare(strict_types=1);

namespace Phil\ContentBundle\Tests\Unit\Domain\Capability;

use Phil\ContentBundle\Domain\Capability\RoutableTrait;
use Phil\ContentBundle\Domain\Dimension\DimensionKey;
use Phil\ContentBundle\Domain\Dimension\Stage;
use Phil\ContentBundle\Tests\Unit\Fixture\ExampleDimensionContent;
use Phil\ContentBundle\Tests\Unit\Fixture\ExampleRoot;
use Phil\LocaleDimensions\Locale;
use Phil\LocaleDimensions\Version;
use Phil\Slug\Slug;
use PHPUnit\Framework\Attributes\CoversTrait;
use PHPUnit\Framework\TestCase;

#[CoversTrait(RoutableTrait::class)]
final class RoutableTraitTest extends TestCase
{
    public function testTheSlugIsSetReadBackAndCleared(): void
    {
        $row = $this->row();
        self::assertNull($row->getSlug());

        $row->setSlug(Slug::fromString('my-first-article'));
        self::assertSame('my-first-article', $row->getSlug()?->value);

        $row->setSlug(null);
        self::assertNull($row->getSlug());
    }

    private function row(): ExampleDimensionContent
    {
        return new ExampleRoot()->createDimensionContent(DimensionKey::localized(Locale::fromString('fr'), Stage::Draft, Version::first()));
    }
}
