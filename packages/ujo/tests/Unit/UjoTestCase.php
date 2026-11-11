<?php

declare(strict_types=1);

namespace Phil\Ujo\Tests\Unit;

use Phil\Ujo\Block;
use Phil\Ujo\Document;
use Phil\Ujo\Exception\InvalidDocument;
use Phil\Ujo\Normalizer;
use Phil\Ujo\Type\BlockTypeRegistry;
use Phil\Ujo\Type\CommunityTypes;
use Phil\Ujo\Type\LayoutTypes;
use Phil\Ujo\Type\MediaTypes;
use Phil\Ujo\Type\StandardTypes;
use Phil\Ujo\Type\TuneRegistry;
use Phil\Ujo\Type\Tunes;
use PHPUnit\Framework\TestCase;

/**
 * Normalizes one block at a time with the types of the default registry and `raw`, and the tunes of the default
 * registry, built again for each block: the default registries are built once per process, so mutation testing would
 * credit their types and tunes to the first test alone.
 */
abstract class UjoTestCase extends TestCase
{
    /**
     * @param array<array-key, mixed> $data
     * @param array<string, mixed>    $tunes
     */
    protected static function normalize(string $type, array $data, array $tunes = []): Block
    {
        return self::normalizer()->normalize(new Document([new Block('b', $type, $data, $tunes)]))->blocks[0];
    }

    /**
     * @param array<array-key, mixed> $data
     * @param array<string, mixed>    $tunes
     */
    protected static function assertInvalid(string $message, string $type, array $data, array $tunes = []): void
    {
        try {
            self::normalize($type, $data, $tunes);
            self::fail('The block is valid: '.$message);
        } catch (InvalidDocument $invalidDocument) {
            self::assertSame($message, $invalidDocument->getMessage());
        }
    }

    protected static function normalizer(): Normalizer
    {
        return new Normalizer(new BlockTypeRegistry(...StandardTypes::all(), ...CommunityTypes::all(), ...MediaTypes::all(), ...LayoutTypes::all(), ...[StandardTypes::raw()]), new TuneRegistry(...Tunes::all()));
    }

    /**
     * The data with a value set at a dotted path, `meta.start` or `cols.0.blocks`.
     *
     * @param array<array-key, mixed> $data
     *
     * @return array<array-key, mixed>
     */
    protected static function set(array $data, string $path, mixed $value): array
    {
        $keys = explode('.', $path);
        $first = array_shift($keys);
        $data[$first] = [] === $keys ? $value : self::set(\is_array($data[$first] ?? null) ? $data[$first] : [], implode('.', $keys), $value);

        return $data;
    }

    /**
     * The data without the value at a dotted path.
     *
     * @param array<array-key, mixed> $data
     *
     * @return array<array-key, mixed>
     */
    protected static function unset(array $data, string $path): array
    {
        $keys = explode('.', $path);
        $first = array_shift($keys);

        if ([] === $keys) {
            unset($data[$first]);
        } elseif (\is_array($data[$first] ?? null)) {
            $data[$first] = self::unset($data[$first], implode('.', $keys));
        }

        return $data;
    }

    protected static function at(string $path): string
    {
        return 'blocks[0].'.preg_replace('/\.(\d+)/', '[$1]', $path);
    }
}
