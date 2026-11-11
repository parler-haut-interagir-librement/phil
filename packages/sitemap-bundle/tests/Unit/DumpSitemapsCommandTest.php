<?php

declare(strict_types=1);

namespace Phil\SitemapBundle\Tests\Unit;

use DirectoryIterator;
use Override;
use Phil\Sitemap\Url;
use Phil\Sitemap\UrlContainer;
use Phil\Sitemap\UrlProvider;
use Phil\SitemapBundle\Infrastructure\Console\DumpSitemapsCommand;
use Phil\SitemapBundle\Infrastructure\Sitemaps;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Routing\RequestContext;

#[CoversClass(DumpSitemapsCommand::class)]
final class DumpSitemapsCommandTest extends TestCase
{
    private string $directory;

    private BufferedOutput $output;

    #[Override]
    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir().'/phil-sitemap-dump-'.bin2hex(random_bytes(4));
        mkdir($this->directory);
        file_put_contents($this->directory.'/sitemap.xml', 'previous index');
        file_put_contents($this->directory.'/sitemap.old.xml', 'previous file');
        $this->output = new BufferedOutput();
    }

    #[Override]
    protected function tearDown(): void
    {
        chmod($this->directory, 0o775);
        new Filesystem()->remove($this->directory);
    }

    public function testAFailingProviderLeavesThePreviousDumpWhole(): void
    {
        $failing = new class implements UrlProvider {
            public function provide(UrlContainer $urls, ?string $section): void
            {
                throw new RuntimeException('The database is down.');
            }
        };

        try {
            $this->dump($failing);
            self::fail('The provider did not fail.');
        } catch (RuntimeException $runtimeException) {
            self::assertSame('The database is down.', $runtimeException->getMessage());
        }

        self::assertSame(['sitemap.old.xml' => 'previous file', 'sitemap.xml' => 'previous index'], $this->files());
    }

    public function testAFileThatCannotBeWrittenKeepsThePreviousIndex(): void
    {
        chmod($this->directory, 0o555);

        self::assertSame(Command::FAILURE, $this->dump($this->pages()));
        $written = $this->output->fetch();
        self::assertStringContainsString('The file "sitemap.news.xml" cannot be written', $written);
        self::assertStringNotContainsString('sitemap.news.xml: 1 pages', $written, 'the command stops at the first write that fails');

        chmod($this->directory, 0o775);
        self::assertSame(['sitemap.old.xml' => 'previous file', 'sitemap.xml' => 'previous index'], $this->files());
    }

    public function testTheNewFilesReplaceTheOldOnesAndNoTemporaryFileStays(): void
    {
        file_put_contents($this->directory."/sitemap.kept.xml\n", 'not a sitemap: its name goes on past .xml');

        self::assertSame(Command::SUCCESS, $this->dump($this->pages()));

        $files = $this->files();
        self::assertSame(["sitemap.kept.xml\n", 'sitemap.news.xml', 'sitemap.xml'], array_keys($files));
        self::assertStringContainsString('<loc>https://example.com/sitemap.news.xml</loc>', $files['sitemap.xml']);
        self::assertStringContainsString('<loc>https://example.com/a</loc>', $files['sitemap.news.xml']);
    }

    public function testTheRoutersLocalhostIsRefusedWithoutABaseUrl(): void
    {
        $command = new DumpSitemapsCommand(new Sitemaps([$this->pages()]), new RequestContext(), $this->directory, 50000);

        self::assertSame(Command::FAILURE, $command(new SymfonyStyle(new ArrayInput([]), $this->output)));
        self::assertStringContainsString('framework.router.default_uri', $this->output->fetch());
        self::assertSame(['sitemap.old.xml' => 'previous file', 'sitemap.xml' => 'previous index'], $this->files());
    }

    public function testAMissingDirectoryIsMadeWithItsParents(): void
    {
        $umask = umask(0);

        try {
            self::assertSame(Command::SUCCESS, $this->dump($this->pages(), $this->directory.'/made/deeper/'));
        } finally {
            umask($umask);
        }

        self::assertSame(0o775, fileperms($this->directory.'/made/deeper') & 0o777);
        self::assertFileExists($this->directory.'/made/deeper/sitemap.news.xml');
        self::assertStringContainsString('writtenin'.$this->directory.'/made/deeper:1files', $this->compact($this->output->fetch()));
    }

    public function testADirectoryThatCannotBeMadeIsRefused(): void
    {
        chmod($this->directory, 0o555);

        self::assertSame(Command::FAILURE, $this->dump($this->pages(), $this->directory.'/made'));
        $written = $this->compact($this->output->fetch());
        self::assertStringContainsString('Thedirectory"'.$this->directory.'/made"cannotbemade.', $written);
        self::assertStringNotContainsString('cannotbewritten', $written, 'the command stops there');
    }

    /**
     * The temporary files are written in the directory itself, so a parent that cannot be written does not matter.
     */
    public function testADirectoryUnderAParentThatCannotBeWrittenIsWritten(): void
    {
        mkdir($this->directory.'/public');
        chmod($this->directory, 0o555);

        self::assertSame(Command::SUCCESS, $this->dump($this->pages(), $this->directory.'/public'));
        self::assertSame(['.', '..', 'sitemap.news.xml', 'sitemap.xml'], scandir($this->directory.'/public'));
    }

    public function testAFileThatCannotBeRenamedLeavesNoTemporaryFile(): void
    {
        mkdir($this->directory.'/sitemap.news.xml');
        touch($this->directory.'/sitemap.news.xml/taken');

        self::assertSame(Command::FAILURE, $this->dump($this->pages()));
        self::assertStringContainsString('The file "sitemap.news.xml" cannot be written', $this->output->fetch());
        self::assertSame(['.', '..', 'sitemap.news.xml', 'sitemap.old.xml', 'sitemap.xml'], scandir($this->directory));
        self::assertSame('previous index', file_get_contents($this->directory.'/sitemap.xml'));
    }

    public function testTheSectionsAreGzippedAtTheBestCompression(): void
    {
        self::assertSame(Command::SUCCESS, $this->dump($this->pages(), gzip: true));

        $gzip = (string) file_get_contents($this->directory.'/sitemap.news.xml.gz');
        self::assertSame(2, \ord($gzip[8]), 'the extra flags of the gzip header say: the slowest, best compression');
        self::assertStringContainsString('<loc>https://example.com/a</loc>', (string) gzdecode($gzip));
        self::assertStringContainsString('<loc>https://example.com/sitemap.news.xml.gz</loc>', (string) file_get_contents($this->directory.'/sitemap.xml'));
    }

    public function testTheSectionsAreNotGzippedByDefault(): void
    {
        $command = new DumpSitemapsCommand(new Sitemaps([$this->pages()]), new RequestContext(host: 'example.com', scheme: 'https'), $this->directory, 50000);

        self::assertSame(Command::SUCCESS, $command(new SymfonyStyle(new ArrayInput([]), $this->output)));
        self::assertSame(['sitemap.news.xml', 'sitemap.xml'], array_keys($this->files()));
    }

    public function testEachFileWrittenIsReportedWithItsPages(): void
    {
        self::assertSame(Command::SUCCESS, $this->dump($this->pages()));
        self::assertStringContainsString('sitemap.news.xml: 1 pages', $this->output->fetch());
    }

    public function testAGivenBaseUrlWinsOverTheRoutersAndLosesItsTrailingSlash(): void
    {
        $command = new DumpSitemapsCommand(new Sitemaps([$this->pages()]), new RequestContext(host: 'example.com', scheme: 'https'), $this->directory, 50000);

        self::assertSame(Command::SUCCESS, $command(new SymfonyStyle(new ArrayInput([]), $this->output), baseUrl: 'https://static.example.org/maps/'));
        self::assertStringContainsString('<loc>https://static.example.org/maps/sitemap.news.xml</loc>', $this->files()['sitemap.xml']);
    }

    public function testOnlyTheFilesNamedLikeASitemapAreRemoved(): void
    {
        file_put_contents($this->directory.'/old.sitemap.old.xml', 'not a sitemap: its name does not start with sitemap.');

        self::assertSame(Command::SUCCESS, $this->dump($this->pages()));
        self::assertSame(['old.sitemap.old.xml', 'sitemap.news.xml', 'sitemap.xml'], array_keys($this->files()));
    }

    /**
     * @return iterable<string, array{RequestContext, string}>
     */
    public static function routers(): iterable
    {
        yield 'http on its port' => [new RequestContext(host: 'example.com', scheme: 'http', httpPort: 80), 'http://example.com/sitemap.news.xml'];
        yield 'http on another port, under a path' => [new RequestContext(baseUrl: '/shop', host: 'example.com', scheme: 'http', httpPort: 8080), 'http://example.com:8080/shop/sitemap.news.xml'];
        yield 'https on another port' => [new RequestContext(host: 'example.com', scheme: 'https', httpPort: 8080, httpsPort: 8443), 'https://example.com:8443/sitemap.news.xml'];
    }

    #[DataProvider('routers')]
    public function testTheBaseUrlIsTheRoutersAddress(RequestContext $context, string $loc): void
    {
        self::assertSame(Command::SUCCESS, $this->dump($this->pages(), context: $context));
        self::assertStringContainsString('<loc>'.$loc.'</loc>', (string) file_get_contents($this->directory.'/sitemap.xml'));
    }

    private function dump(UrlProvider $provider, ?string $directory = null, bool $gzip = false, ?RequestContext $context = null): int
    {
        $command = new DumpSitemapsCommand(new Sitemaps([$provider]), $context ?? new RequestContext(host: 'example.com', scheme: 'https'), $this->directory, 50000);

        return $command(new SymfonyStyle(new ArrayInput([]), $this->output), $directory, gzip: $gzip);
    }

    /**
     * The output without its spaces and line breaks, which the console puts where its width ends.
     */
    private function compact(string $output): string
    {
        return (string) preg_replace('/\s+/', '', $output);
    }

    private function pages(): UrlProvider
    {
        return new class implements UrlProvider {
            public function provide(UrlContainer $urls, ?string $section): void
            {
                $urls->add(new Url('https://example.com/a'), 'news');
            }
        };
    }

    /**
     * Every file of the directory and its content, hidden ones included, by name.
     *
     * @return array<string, string>
     */
    private function files(): array
    {
        $files = [];

        foreach (new DirectoryIterator($this->directory) as $file) {
            if (!$file->isDot()) {
                $files[$file->getFilename()] = (string) file_get_contents($file->getPathname());
            }
        }

        ksort($files);

        return $files;
    }
}
