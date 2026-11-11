<?php

declare(strict_types=1);

namespace Phil\Geo\Tests\Unit\Geocoding\MaxMind;

use Override;
use Phil\Geo\Exception\InvalidDatabase;
use Phil\Geo\Geocoding\MaxMind\FileBytes;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(FileBytes::class)]
final class FileBytesTest extends TestCase
{
    private const int BLOCK = 4_096;

    /** @var array<string, string> by seed */
    private static array $contents = [];

    private string $path;

    #[Override]
    protected function setUp(): void
    {
        $this->path = sys_get_temp_dir().'/phil-geo-bytes-'.bin2hex(random_bytes(6)).'.bin';
        file_put_contents($this->path, $this->content('a'));
    }

    #[Override]
    protected function tearDown(): void
    {
        if (is_file($this->path)) {
            unlink($this->path);
        }
    }

    /**
     * @return iterable<string, array{int, int}>
     */
    public static function reads(): iterable
    {
        yield 'a byte' => [0, 1];
        yield 'within a block' => [100, 50];
        yield 'the last byte of a block' => [self::BLOCK - 1, 1];
        yield 'the first byte of a block' => [self::BLOCK, 1];
        yield 'across two blocks' => [self::BLOCK - 6, 10];
        yield 'across three blocks' => [self::BLOCK - 1, self::BLOCK + 2];
        yield 'a whole block' => [self::BLOCK * 2, self::BLOCK];
        yield 'to the end of the file' => [self::BLOCK * 66 - 7, 7];
        yield 'the whole file' => [0, self::BLOCK * 66];
    }

    #[DataProvider('reads')]
    public function testTheBytesAskedForAreRead(int $offset, int $length): void
    {
        $bytes = new FileBytes($this->path);

        self::assertSame(substr($this->content('a'), $offset, $length), $bytes->read($offset, $length));
        self::assertSame(substr($this->content('a'), $offset, $length), $bytes->read($offset, $length));
        self::assertSame(self::BLOCK * 66, $bytes->size());
    }

    public function testTheBlockOfAnOffsetIsGivenWithItsStart(): void
    {
        $bytes = new FileBytes($this->path);

        self::assertSame([0, substr($this->content('a'), 0, self::BLOCK)], $bytes->blockAt(0));
        self::assertSame([0, substr($this->content('a'), 0, self::BLOCK)], $bytes->blockAt(self::BLOCK - 1));
        self::assertSame([self::BLOCK * 3, substr($this->content('a'), self::BLOCK * 3, self::BLOCK)], $bytes->blockAt(self::BLOCK * 3 + 17));
        self::assertSame([self::BLOCK * 65, substr($this->content('a'), self::BLOCK * 65)], $bytes->blockAt(self::BLOCK * 66 - 1));
    }

    public function testNothingIsReadForAnEmptyLength(): void
    {
        self::assertSame('', new FileBytes($this->path)->read(self::BLOCK * 66, 0));
    }

    public function testAReadPastTheEndIsCorrupt(): void
    {
        $this->expectException(InvalidDatabase::class);
        $this->expectExceptionMessageIsOrContains('it ends before 8 bytes from offset '.(self::BLOCK * 66 - 7).'.');

        new FileBytes($this->path)->read(self::BLOCK * 66 - 7, 8);
    }

    /**
     * A block read is kept, and only that block: the file written again after it is read again past the block.
     */
    public function testTheBlockReadIsKept(): void
    {
        $bytes = new FileBytes($this->path);
        $bytes->read(10, 1);
        file_put_contents($this->path, $this->content('b'));

        self::assertSame(substr($this->content('a'), 0, self::BLOCK), $bytes->read(0, self::BLOCK));
        self::assertSame(substr($this->content('b'), self::BLOCK, 3), $bytes->read(self::BLOCK, 3));
    }

    /**
     * Only the blocks a read needs are read and kept: one that ends on a block's last byte keeps no block after it.
     */
    public function testAReadKeepsOnlyTheBlocksItNeeds(): void
    {
        $bytes = new FileBytes($this->path);

        for ($block = 0; $block < 63; ++$block) {
            $bytes->read($block * self::BLOCK + self::BLOCK - 1, 1);
        }

        file_put_contents($this->path, $this->content('b'));

        self::assertSame(substr($this->content('b'), 63 * self::BLOCK, 2), $bytes->read(63 * self::BLOCK, 2));
        self::assertSame(substr($this->content('a'), 0, 2), $bytes->read(0, 2));
    }

    /**
     * A read across blocks takes the kept ones as they were, and reads the others.
     */
    public function testAReadAcrossBlocksTakesTheKeptOnes(): void
    {
        $bytes = new FileBytes($this->path);
        $bytes->read(10, 1);
        file_put_contents($this->path, $this->content('b'));

        self::assertSame(substr($this->content('a'), self::BLOCK - 6, 6).substr($this->content('b'), self::BLOCK, 4), $bytes->read(self::BLOCK - 6, 10));
    }

    /**
     * The file is opened once: removed after the first read, it is still read.
     */
    public function testTheFileIsOpenedOnce(): void
    {
        $bytes = new FileBytes($this->path);
        $bytes->read(10, 1);
        unlink($this->path);

        self::assertSame(substr($this->content('a'), self::BLOCK * 5, 3), $bytes->read(self::BLOCK * 5, 3));
        self::assertSame(self::BLOCK * 66, $bytes->size());
    }

    /**
     * At most 64 blocks are kept: the 65th lets the others go.
     */
    public function testAtMost64BlocksAreKept(): void
    {
        $bytes = new FileBytes($this->path);

        for ($block = 0; $block < 64; ++$block) {
            $bytes->read($block * self::BLOCK, 1);
        }

        file_put_contents($this->path, $this->content('b'));

        self::assertSame(substr($this->content('a'), 0, 2), $bytes->read(0, 2));
        self::assertSame(substr($this->content('a'), 63 * self::BLOCK, 2), $bytes->read(63 * self::BLOCK, 2));
        self::assertSame(substr($this->content('b'), 64 * self::BLOCK, 2), $bytes->read(64 * self::BLOCK, 2));
        self::assertSame(substr($this->content('b'), 0, 2), $bytes->read(0, 2));
    }

    /**
     * 66 blocks of bytes that differ from one offset to the next and from one seed to the other.
     */
    private function content(string $seed): string
    {
        if (!isset(self::$contents[$seed])) {
            $content = '';

            for ($i = 0; $i < self::BLOCK * 66; ++$i) {
                $content .= \chr(($i * 7 + \ord($seed[0])) % 251);
            }

            self::$contents[$seed] = $content;
        }

        return self::$contents[$seed];
    }
}
