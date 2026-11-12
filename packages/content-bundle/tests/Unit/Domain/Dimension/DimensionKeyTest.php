<?php

declare(strict_types=1);

namespace Phil\ContentBundle\Tests\Unit\Domain\Dimension;

use Phil\ContentBundle\Domain\Dimension\DimensionKey;
use Phil\ContentBundle\Domain\Dimension\Stage;
use Phil\LocaleDimensions\Locale;
use Phil\LocaleDimensions\Version;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(DimensionKey::class)]
final class DimensionKeyTest extends TestCase
{
    public function testTheUnlocalizedKeyHasNoLocale(): void
    {
        $key = DimensionKey::unlocalized(Stage::Draft, Version::first());

        self::assertFalse($key->isLocalized());
        self::assertNull($key->locale);
    }

    public function testALocalizedKeyHasItsLocale(): void
    {
        $key = DimensionKey::localized(Locale::fromString('fr_CA'), Stage::Live, Version::of(2));

        self::assertTrue($key->isLocalized());
        self::assertSame('fr_CA', $key->locale?->value);
        self::assertSame(Stage::Live, $key->stage);
        self::assertSame(2, $key->version->number);
    }

    /**
     * A localized row is read over the shared row of the same stage and version, never of another one: a
     * draft read over the live shared values would preview a page that will not be published.
     */
    public function testTheUnlocalizedCounterpartKeepsStageAndVersion(): void
    {
        $key = DimensionKey::localized(Locale::fromString('fr_CA'), Stage::Draft, Version::of(4));

        self::assertTrue($key->unlocalizedCounterpart()->equals(DimensionKey::unlocalized(Stage::Draft, Version::of(4))));
    }

    public function testTheUnlocalizedKeyIsItsOwnCounterpart(): void
    {
        $key = DimensionKey::unlocalized(Stage::Live, Version::of(3));

        self::assertTrue($key->unlocalizedCounterpart()->equals($key));
    }

    public function testInStageChangesOnlyTheStage(): void
    {
        $draft = DimensionKey::localized(Locale::fromString('en_CA'), Stage::Draft, Version::of(5));

        self::assertTrue($draft->inStage(Stage::Live)->equals(DimensionKey::localized(Locale::fromString('en_CA'), Stage::Live, Version::of(5))));
        self::assertSame(Stage::Draft, $draft->stage);
    }

    public function testAtVersionChangesOnlyTheVersion(): void
    {
        $key = DimensionKey::localized(Locale::fromString('en_CA'), Stage::Draft, Version::of(5));

        self::assertTrue($key->atVersion(Version::of(6))->equals(DimensionKey::localized(Locale::fromString('en_CA'), Stage::Draft, Version::of(6))));
    }

    public function testEqualityLooksAtEveryDimension(): void
    {
        $key = DimensionKey::localized(Locale::fromString('fr'), Stage::Draft, Version::first());

        self::assertTrue($key->equals(DimensionKey::localized(Locale::fromString('fr'), Stage::Draft, Version::first())));
        self::assertFalse($key->equals(DimensionKey::localized(Locale::fromString('fr_CA'), Stage::Draft, Version::first())));
        self::assertFalse($key->equals(DimensionKey::localized(Locale::fromString('fr'), Stage::Live, Version::first())));
        self::assertFalse($key->equals(DimensionKey::localized(Locale::fromString('fr'), Stage::Draft, Version::of(2))));
    }

    /**
     * The shared row and a localized row are never the same row, in either direction.
     */
    public function testTheUnlocalizedKeyNeverEqualsALocalizedOne(): void
    {
        $unlocalized = DimensionKey::unlocalized(Stage::Draft, Version::first());
        $localized = DimensionKey::localized(Locale::fromString('fr'), Stage::Draft, Version::first());

        self::assertFalse($unlocalized->equals($localized));
        self::assertFalse($localized->equals($unlocalized));
    }

    public function testItsStringFormNamesEveryDimension(): void
    {
        self::assertSame('fr_CA/draft/3', DimensionKey::localized(Locale::fromString('fr_CA'), Stage::Draft, Version::of(3))->toString());
        self::assertSame('*/live/1', DimensionKey::unlocalized(Stage::Live, Version::first())->toString());
    }
}
