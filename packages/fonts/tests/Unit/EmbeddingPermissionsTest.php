<?php

declare(strict_types=1);

namespace Phil\Fonts\Tests\Unit;

use Phil\Fonts\EmbeddingPermissions;
use Phil\Fonts\EmbeddingUsage;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(EmbeddingPermissions::class)]
#[CoversClass(EmbeddingUsage::class)]
final class EmbeddingPermissionsTest extends TestCase
{
    /**
     * @return iterable<string, array{int, EmbeddingUsage}>
     */
    public static function usages(): iterable
    {
        yield 'installable' => [0x0000, EmbeddingUsage::Installable];
        yield 'reserved bit 0 only' => [0x0001, EmbeddingUsage::Installable];
        yield 'restricted' => [0x0002, EmbeddingUsage::Restricted];
        yield 'preview and print' => [0x0004, EmbeddingUsage::PreviewAndPrint];
        yield 'editable' => [0x0008, EmbeddingUsage::Editable];
        yield 'restricted and preview: the least restrictive' => [0x0006, EmbeddingUsage::PreviewAndPrint];
        yield 'preview and editable: the least restrictive' => [0x000C, EmbeddingUsage::Editable];
        yield 'all three' => [0x000E, EmbeddingUsage::Editable];
    }

    #[DataProvider('usages')]
    public function testReadsTheUsage(int $fsType, EmbeddingUsage $usage): void
    {
        self::assertSame($usage, new EmbeddingPermissions($fsType)->usage);
        self::assertSame($fsType, new EmbeddingPermissions($fsType)->fsType);
    }

    public function testReadsTheSubsettingAndBitmapBits(): void
    {
        $plain = new EmbeddingPermissions();
        self::assertSame(0, $plain->fsType);
        self::assertSame(EmbeddingUsage::Installable, $plain->usage);
        self::assertTrue($plain->allowsSubsetting());
        self::assertFalse($plain->bitmapOnly());
        self::assertTrue($plain->allowsOutlineEmbedding());

        $whole = new EmbeddingPermissions(0x0100);
        self::assertFalse($whole->allowsSubsetting());
        self::assertFalse($whole->bitmapOnly());
        self::assertTrue($whole->allowsOutlineEmbedding());

        $bitmap = new EmbeddingPermissions(0x0200 | 0x0004);
        self::assertTrue($bitmap->allowsSubsetting());
        self::assertTrue($bitmap->bitmapOnly());
        self::assertFalse($bitmap->allowsOutlineEmbedding());

        self::assertFalse(new EmbeddingPermissions(0x0002)->allowsOutlineEmbedding());
    }

    public function testEachUsageSaysWhatItAllows(): void
    {
        self::assertSame(
            [[true, true], [true, true], [true, false], [false, false]],
            array_map(static fn (EmbeddingUsage $usage): array => [$usage->allowsEmbedding(), $usage->allowsEditing()], EmbeddingUsage::cases()),
        );
    }
}
