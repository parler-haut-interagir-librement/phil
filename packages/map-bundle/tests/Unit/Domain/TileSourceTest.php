<?php

declare(strict_types=1);

namespace Phil\MapBundle\Tests\Unit\Domain;

use Phil\MapBundle\Domain\Exception\InvalidMap;
use Phil\MapBundle\Domain\ValueObject\TileSource;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(TileSource::class)]
#[CoversClass(InvalidMap::class)]
final class TileSourceTest extends TestCase
{
    public function testATileIsTheTemplateWithItsNumbers(): void
    {
        $source = TileSource::of('https://tiles.example.org/{z}/{x}/{y}.png', '© Example', 18);

        self::assertSame('https://tiles.example.org/3/7/0.png', $source->url(3, 7, 0)->toString());
        self::assertSame(['https://tiles.example.org/{z}/{x}/{y}.png', '© Example', 18, false], [$source->template, $source->attribution, $source->maxZoom, $source->allowLocalhost]);
    }

    public function testTheGridOfEachZoom(): void
    {
        $source = TileSource::of('https://t.example.org/{z}/{x}/{y}.png', 'x', 2);

        self::assertTrue($source->has(0, 0, 0));
        self::assertTrue($source->has(2, 3, 3));
        self::assertFalse($source->has(0, 1, 0));
        self::assertFalse($source->has(0, 0, 1));
        self::assertFalse($source->has(2, 4, 0));
        self::assertFalse($source->has(2, 0, 4));
        self::assertFalse($source->has(2, -1, 0));
        self::assertFalse($source->has(2, 0, -1));
        self::assertFalse($source->has(3, 0, 0));
        self::assertFalse($source->has(-1, 0, 0));
        self::assertTrue($source->has(1, 1, 1));
    }

    public function testATileOutsideTheGridIsRefused(): void
    {
        $this->expectExceptionObject(InvalidMap::because('There is no tile 1/2/0 at this source.'));

        TileSource::of('https://t.example.org/{z}/{x}/{y}.png', 'x')->url(1, 2, 0);
    }

    public function testLocalhostIsAHostOnlyWhenAllowed(): void
    {
        self::assertSame('http://localhost:8080/0/0/0.png', TileSource::of('http://localhost:8080/{z}/{x}/{y}.png', 'x', 19, true)->url(0, 0, 0)->toString());

        try {
            TileSource::of('http://localhost/{z}/{x}/{y}', 'x');
            self::fail('localhost is refused.');
        } catch (InvalidMap $invalidMap) {
            self::assertStringStartsWith('The tile template "http://localhost/{z}/{x}/{y}" is no address: ', $invalidMap->getMessage());
        }
    }

    /**
     * @return iterable<string, array{string, string, int, string}>
     */
    public static function refused(): iterable
    {
        yield 'no z' => ['https://t.example.org/{x}/{y}', 'x', 19, 'The tile template "https://t.example.org/{x}/{y}" has no "{z}".'];
        yield 'no x' => ['https://t.example.org/{z}/{y}', 'x', 19, 'The tile template "https://t.example.org/{z}/{y}" has no "{x}".'];
        yield 'no y' => ['https://t.example.org/{z}/{x}', 'x', 19, 'The tile template "https://t.example.org/{z}/{x}" has no "{y}".'];
        yield 'no attribution' => ['https://t.example.org/{z}/{x}/{y}', '  ', 19, 'A tile source names its attribution: its licence requires it.'];
        yield 'zoom below' => ['https://t.example.org/{z}/{x}/{y}', 'x', -1, 'The deepest zoom of a tile source is between 0 and 22, not -1.'];
        yield 'zoom above' => ['https://t.example.org/{z}/{x}/{y}', 'x', 23, 'The deepest zoom of a tile source is between 0 and 22, not 23.'];
    }

    #[DataProvider('refused')]
    public function testARefusedSource(string $template, string $attribution, int $zoom, string $message): void
    {
        $this->expectExceptionObject(InvalidMap::because($message));

        TileSource::of($template, $attribution, $zoom);
    }

    public function testTheDeepestZoomIs19ByDefault(): void
    {
        self::assertSame(19, TileSource::of('https://t.example.org/{z}/{x}/{y}', 'x')->maxZoom);
    }

    public function testTheBoundsOfTheZoomAreAccepted(): void
    {
        self::assertSame(22, TileSource::of('https://t.example.org/{z}/{x}/{y}', 'x', 22)->maxZoom);
        self::assertSame(0, TileSource::of('https://t.example.org/{z}/{x}/{y}', 'x', 0)->maxZoom);
    }
}
