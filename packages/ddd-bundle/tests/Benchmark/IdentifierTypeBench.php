<?php

declare(strict_types=1);

namespace Phil\DddBundle\Tests\Benchmark;

use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Platforms\SQLitePlatform;
use Generator;
use Phil\DddBundle\Tests\Unit\Infrastructure\Doctrine\Fixture\ExampleId;
use Phil\DddBundle\Tests\Unit\Infrastructure\Doctrine\Fixture\ExampleIdType;

/**
 * The identifier type converts every identifier column of every hydrated row: a platform check, the binary to
 * RFC 4122 conversion on a binary column, then the parse and UUIDv7 validation of `Identifier::fromString()`.
 *
 * @BeforeMethods("setUp")
 *
 * @Revs(50)
 *
 * @Groups({"ddd", "doctrine"})
 */
final class IdentifierTypeBench
{
    private readonly ExampleIdType $type;

    private readonly SQLitePlatform $binaryPlatform;

    private readonly PostgreSQLPlatform $nativePlatform;

    /**
     * @var list<string> 16-byte values, as a binary column returns them
     */
    private array $binary = [];

    /**
     * @var list<string> canonical strings, as a native UUID column returns them
     */
    private array $canonical = [];

    public function __construct()
    {
        $this->type = new ExampleIdType();
        $this->binaryPlatform = new SQLitePlatform();
        $this->nativePlatform = new PostgreSQLPlatform();
    }

    public function setUp(): void
    {
        for ($i = 0; $i < 1000; ++$i) {
            $id = ExampleId::generate();
            $this->binary[] = $id->value->toBinary();
            $this->canonical[] = $id->toString();
        }

        // The platform check is cached per platform object: warm it, as the first row of a request would.
        $this->type->convertToPHPValue($this->binary[0], $this->binaryPlatform);
        $this->type->convertToPHPValue($this->canonical[0], $this->nativePlatform);
    }

    /**
     * @ParamProviders("rows")
     *
     * @param array{rows: int} $params
     */
    public function benchBinaryColumn(array $params): void
    {
        for ($i = 0; $i < $params['rows']; ++$i) {
            $this->type->convertToPHPValue($this->binary[$i], $this->binaryPlatform);
        }
    }

    /**
     * @ParamProviders("rows")
     *
     * @param array{rows: int} $params
     */
    public function benchNativeColumn(array $params): void
    {
        for ($i = 0; $i < $params['rows']; ++$i) {
            $this->type->convertToPHPValue($this->canonical[$i], $this->nativePlatform);
        }
    }

    /**
     * The parse alone, without the type, to tell its share of the conversion.
     *
     * @ParamProviders("rows")
     *
     * @param array{rows: int} $params
     */
    public function benchFromStringOnly(array $params): void
    {
        for ($i = 0; $i < $params['rows']; ++$i) {
            ExampleId::fromString($this->canonical[$i]);
        }
    }

    /**
     * @return Generator<string, array{rows: int}>
     */
    public function rows(): Generator
    {
        yield '1 row' => ['rows' => 1];
        yield '100 rows' => ['rows' => 100];
        yield '1000 rows' => ['rows' => 1000];
    }
}
