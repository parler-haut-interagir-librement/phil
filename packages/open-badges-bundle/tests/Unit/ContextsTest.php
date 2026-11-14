<?php

declare(strict_types=1);

namespace Phil\OpenBadgesBundle\Tests\Unit;

use LogicException;
use Override;
use Phil\OpenBadges\Context;
use Phil\OpenBadges\Exception\InvalidCredential;
use Phil\OpenBadgesBundle\Infrastructure\DependencyInjection\Contexts;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The JSON-LD contexts read from the files the application provides.
 */
#[CoversClass(Contexts::class)]
final class ContextsTest extends TestCase
{
    private string $directory;

    #[Override]
    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir().'/phil-open-badges-contexts-'.bin2hex(random_bytes(4));
        mkdir($this->directory);
    }

    #[Override]
    protected function tearDown(): void
    {
        $files = glob($this->directory.'/*');
        array_map(unlink(...), false === $files ? [] : $files);
        rmdir($this->directory);
    }

    public function testTheContextsOfTheApplicationAreServedBesideTheLibrarys(): void
    {
        $document = ['@context' => ['@version' => 1.1, 'name' => 'https://example.com/vocab#name'], 'note' => 'kept'];
        $loader = Contexts::loader(['https://example.com/contexts/v1' => $this->file('v1.json', json_encode($document, \JSON_THROW_ON_ERROR))]);

        self::assertSame($document, $loader->load('https://example.com/contexts/v1'));
        self::assertArrayHasKey('@context', $loader->load(Context::CREDENTIALS_V2));

        $this->expectException(InvalidCredential::class);
        $loader->load('https://example.com/contexts/v2');
    }

    public function testNoFileGivesTheLibrarysAlone(): void
    {
        self::assertArrayHasKey('@context', Contexts::loader([])->load(Context::CREDENTIALS_V2));
    }

    public function testTheLargestFileIsRead(): void
    {
        $json = '{"@context":{},"pad":"'.str_repeat('a', Contexts::MAX_SIZE - 24).'"}';
        self::assertSame(Contexts::MAX_SIZE, \strlen($json));

        self::assertSame([], Contexts::loader(['https://example.com/v1' => $this->file('big.json', $json)])->load('https://example.com/v1')['@context']);
    }

    /**
     * @return iterable<string, array{string|null, string}>
     */
    public static function unreadable(): iterable
    {
        yield 'no file' => [null, 'phil_open_badges.contexts: the context "https://example.com/v1" is read from "%s", which is not a readable file of at most 1048576 bytes.'];
        yield 'too large' => ['{"@context":{},"pad":"'.str_repeat('a', Contexts::MAX_SIZE - 23).'"}', 'phil_open_badges.contexts: the context "https://example.com/v1" is read from "%s", which is not a readable file of at most 1048576 bytes.'];
        yield 'not JSON' => ['{"@context":', 'phil_open_badges.contexts: the file "%s" of the context "https://example.com/v1" is not a JSON object with an "@context".'];
        yield 'a list' => ['[1, 2]', 'phil_open_badges.contexts: the file "%s" of the context "https://example.com/v1" is not a JSON object with an "@context".'];
        yield 'no context' => ['{"name": "x"}', 'phil_open_badges.contexts: the file "%s" of the context "https://example.com/v1" is not a JSON object with an "@context".'];
        yield 'a context by URL' => ['{"@context": "https://example.com/v0"}', 'phil_open_badges.contexts: the file "%s" of the context "https://example.com/v1" is not a JSON object with an "@context".'];
        yield 'too deep' => ['{"@context":'.str_repeat('[', 63).str_repeat(']', 63).'}', 'phil_open_badges.contexts: the file "%s" of the context "https://example.com/v1" is not a JSON object with an "@context".'];
    }

    #[DataProvider('unreadable')]
    public function testAFileThatIsNotAContextStopsTheLoader(?string $contents, string $message): void
    {
        $file = null === $contents ? $this->directory.'/missing.json' : $this->file('context.json', $contents);

        $this->expectExceptionObject(new LogicException(\sprintf($message, $file)));

        Contexts::loader(['https://example.com/v1' => $file]);
    }

    public function testTheDeepestDocumentOfSixtyFourLevelsIsRead(): void
    {
        $json = '{"@context":'.str_repeat('[', 62).str_repeat(']', 62).'}';

        self::assertArrayHasKey('@context', Contexts::loader(['https://example.com/v1' => $this->file('deep.json', $json)])->load('https://example.com/v1'));
    }

    public function testADirectoryIsNoFile(): void
    {
        $this->expectExceptionObject(new LogicException(\sprintf('phil_open_badges.contexts: the context "https://example.com/v1" is read from "%s", which is not a readable file of at most 1048576 bytes.', $this->directory)));

        Contexts::loader(['https://example.com/v1' => $this->directory]);
    }

    private function file(string $name, string $contents): string
    {
        file_put_contents($this->directory.'/'.$name, $contents);

        return $this->directory.'/'.$name;
    }
}
