<?php

declare(strict_types=1);

namespace Phil\MapBundle\Tests\Unit\Infrastructure;

use Phil\MapBundle\Domain\Exception\InvalidMap;
use Phil\MapBundle\Domain\ValueObject\SiteAddress;
use Phil\MapBundle\Infrastructure\Pmtiles\PmtilesLayer;
use Phil\MapBundle\Tests\Fixtures\FixedBaseLayer;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\AbstractLogger;
use Stringable;
use Symfony\Component\AssetMapper\ImportMap\ImportMapConfigReader;
use Symfony\Component\AssetMapper\ImportMap\RemotePackageStorage;
use Symfony\UX\Map\Bridge\Leaflet\Option\TileLayer;

#[CoversClass(PmtilesLayer::class)]
final class PmtilesLayerTest extends TestCase
{
    public function testTheArchiveIsDescribedAndTheFallbackKept(): void
    {
        $fallback = new TileLayer('/tiles/{z}/{x}/{y}', '© X');
        $definition = $this->layer(PmtilesLayer::RASTER, ['pmtiles' => ['version' => '4.3.0']], $fallback)->definition();

        self::assertSame($fallback, $definition->tileLayer);
        self::assertSame(['other' => 1, 'pmtiles' => ['url' => '/maps/world.pmtiles', 'kind' => 'raster', 'attribution' => '© Protomaps', 'maxZoom' => 14, 'flavor' => 'dark']], $definition->extra);
    }

    public function testWithoutTheImportMapTheArchiveIsTrusted(): void
    {
        $layer = new PmtilesLayer(SiteAddress::of('/a.pmtiles'), PmtilesLayer::VECTOR, 'x', 15, 'light', new FixedBaseLayer());

        $archive = $layer->definition()->extra['pmtiles'] ?? null;
        self::assertIsArray($archive);
        self::assertSame('vector', $archive['kind'] ?? null);
    }

    public function testAMissingPackageFallsBackAndSaysSo(): void
    {
        $logger = new class extends AbstractLogger {
            /** @var list<array{mixed, string, array<array-key, mixed>}> */
            public array $records = [];

            public function log($level, string|Stringable $message, array $context = []): void
            {
                $this->records[] = [$level, (string) $message, $context];
            }
        };
        $definition = $this->layer(PmtilesLayer::VECTOR, ['pmtiles' => ['version' => '4.3.0']], false, $logger)->definition();

        self::assertSame(['other' => 1], $definition->extra);
        self::assertSame([['warning', 'The PMTiles archive needs "{package}" in the import map: the map falls back on its other tiles.', ['package' => 'protomaps-leaflet']]], $logger->records);
        self::assertSame(['other' => 1], $this->layer(PmtilesLayer::RASTER, [], false)->definition()->extra);
    }

    public function testAnUnknownKindIsRefused(): void
    {
        $this->expectExceptionObject(InvalidMap::because('A PMTiles archive is "raster" or "vector", not "mbtiles".'));

        new PmtilesLayer(SiteAddress::of('/a.pmtiles'), 'mbtiles', 'x', 15, 'light', new FixedBaseLayer());
    }

    /**
     * @param array<string, array<string, string>> $importMap
     */
    private function layer(string $kind, array $importMap, TileLayer|false $fallback, ?AbstractLogger $logger = null): PmtilesLayer
    {
        $file = tempnam(sys_get_temp_dir(), 'importmap');
        self::assertIsString($file);
        file_put_contents($file, '<?php return '.var_export($importMap, true).';');

        return new PmtilesLayer(SiteAddress::of('/maps/world.pmtiles'), $kind, '© Protomaps', 14, 'dark', new FixedBaseLayer($fallback, ['other' => 1]), new ImportMapConfigReader($file, new RemotePackageStorage(sys_get_temp_dir().'/vendor')), $logger);
    }
}
