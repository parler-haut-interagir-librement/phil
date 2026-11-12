<?php

declare(strict_types=1);

namespace Phil\ContentBundle\Tests\Unit\Domain\Content;

use Phil\ContentBundle\Domain\Content\AbstractDimensionContent;
use Phil\ContentBundle\Domain\Dimension\DimensionKey;
use Phil\ContentBundle\Domain\Dimension\Stage;
use Phil\ContentBundle\Tests\Unit\Infrastructure\Doctrine\Fixture\Article;
use Phil\ContentBundle\Tests\Unit\Infrastructure\Doctrine\Fixture\ArticleDimension;
use Phil\LocaleDimensions\Locale;
use Phil\LocaleDimensions\Version;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

#[CoversClass(AbstractDimensionContent::class)]
final class AbstractDimensionContentTest extends TestCase
{
    public function testALocalizedRowKeepsItsDimensionAndItsRoot(): void
    {
        $root = new Article();
        $key = DimensionKey::localized(Locale::fromString('fr_CA'), Stage::Live, Version::of(3));

        $row = new ArticleDimension($root, $key);

        self::assertSame($root, $row->root());
        self::assertTrue($row->dimension()->equals($key));
        self::assertSame('fr_CA', $row->dimension()->locale?->value);
    }

    public function testAnUnlocalizedRowKeepsItsDimension(): void
    {
        $key = DimensionKey::unlocalized(Stage::Draft, Version::of(2));

        $row = new ArticleDimension(new Article(), $key);

        self::assertFalse($row->dimension()->isLocalized());
        self::assertTrue($row->dimension()->equals($key));
    }

    /**
     * The unlocalized row stores `NULL` in `locale` and the sentinel in `locale_key`, which the unique
     * constraint covers: no database counts two `NULL` as a duplicate.
     */
    public function testTheUnlocalizedRowStoresTheSentinelBesideANullLocale(): void
    {
        $row = new ArticleDimension(new Article(), DimensionKey::unlocalized(Stage::Draft, Version::first()));

        self::assertNull($this->stored($row, 'locale'));
        self::assertSame('*', $row->localeKey());
        self::assertSame('*', AbstractDimensionContent::UNLOCALIZED_KEY);
    }

    public function testALocalizedRowStoresItsLocaleInBothColumns(): void
    {
        $locale = Locale::fromString('en_CA');

        $row = new ArticleDimension(new Article(), DimensionKey::localized($locale, Stage::Draft, Version::first()));

        self::assertSame($locale, $this->stored($row, 'locale'));
        self::assertSame('en_CA', $row->localeKey());
        self::assertSame(Stage::Draft, $this->stored($row, 'stage'));
    }

    public function testEachRowHasItsOwnIdentifier(): void
    {
        $key = DimensionKey::unlocalized(Stage::Draft, Version::first());

        self::assertFalse(new ArticleDimension(new Article(), $key)->id()->equals(new ArticleDimension(new Article(), $key)->id()));
    }

    private function stored(AbstractDimensionContent $row, string $property): mixed
    {
        return new ReflectionProperty(AbstractDimensionContent::class, $property)->getValue($row);
    }
}
