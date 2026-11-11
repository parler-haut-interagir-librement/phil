<?php

declare(strict_types=1);

namespace Phil\SitemapBundle\Infrastructure\Console;

use DirectoryIterator;
use Phil\Sitemap\XmlWriter;
use Phil\SitemapBundle\Infrastructure\Sitemaps;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Routing\RequestContext;

/**
 * Writes the sitemap as files: `sitemap.{name}.xml` — gzipped with `--gzip` — for each section or part, and the
 * `sitemap.xml` index that points at them, under the base URL of the router or the one given.
 *
 * Nothing is touched until the sitemap is built, so a provider that fails leaves the previous dump whole. Each file is
 * then written under a temporary name and renamed into place, the index last, so a crawler never reads half a file nor
 * an index that names a file not there yet; the files of an earlier dump the new one no longer has are removed at the
 * end. A write that fails stops the command, removes what it wrote under temporary names, and keeps the previous index.
 *
 * Without `--base-url`, the base URL is the router's request context: on the command line, `framework.router.default_uri`.
 * A context left on `localhost` is refused, since the index would point search engines at `http://localhost`.
 */
#[AsCommand(name: 'phil:sitemap:dump', description: 'Writes the sitemap files and their index.')]
final readonly class DumpSitemapsCommand
{
    public function __construct(
        private Sitemaps $sitemaps,
        private RequestContext $context,
        private string $directory,
        private int $perFile,
        private bool $allowLocalhost = false,
    ) {
    }

    public function __invoke(
        SymfonyStyle $io,
        #[Argument(description: 'Where the files are written; the configured dump directory when none is given.')]
        ?string $directory = null,
        #[Option(description: "The address the files are served from, as https://example.com; the router's when none is given.")]
        ?string $baseUrl = null,
        #[Option(description: 'Gzip the files of the sections.')]
        bool $gzip = false,
    ): int {
        $directory = rtrim($directory ?? $this->directory, '/');

        if (null === $baseUrl && 'localhost' === $this->context->getHost()) {
            $io->error("The router's address is http://localhost, which search engines cannot reach: set framework.router.default_uri, or pass --base-url.");

            return Command::FAILURE;
        }

        $base = rtrim($baseUrl ?? $this->baseUrl(), '/');
        $files = $this->sitemaps->build()->files($this->perFile);

        if (!@mkdir($directory, 0o775, true) && !is_dir($directory)) {
            $io->error(\sprintf('The directory "%s" cannot be made.', $directory));

            return Command::FAILURE;
        }

        $index = [];
        $written = [];

        foreach ($files as $name => $urls) {
            $file = 'sitemap.'.$name.'.xml'.($gzip ? '.gz' : '');
            $xml = XmlWriter::urlset($urls);
            $temporary = $this->write($directory, $gzip ? gzencode($xml, 9) : $xml);

            if (null === $temporary) {
                return $this->fail($io, $directory, $file, $written);
            }

            $written[$file] = $temporary;
            $index[$base.'/'.$file] = $urls;
            $io->writeln(\sprintf('%s: %d pages', $file, \count($urls)));
        }

        $temporary = $this->write($directory, XmlWriter::index($index, $this->allowLocalhost));

        if (null === $temporary) {
            return $this->fail($io, $directory, 'sitemap.xml', $written);
        }

        foreach ($written as $file => $path) {
            if (!@rename($path, $directory.'/'.$file)) {
                return $this->fail($io, $directory, $file, [...$written, 'sitemap.xml' => $temporary]);
            }

            unset($written[$file]);
        }

        if (!@rename($temporary, $directory.'/sitemap.xml')) {
            return $this->fail($io, $directory, 'sitemap.xml', ['sitemap.xml' => $temporary]);
        }

        $this->removeStale($directory, array_map(static fn (string $loc): string => substr($loc, \strlen($base) + 1), array_keys($index)));
        $io->success(\sprintf('The sitemap is written in %s: %d files and their index.', $directory, \count($index)));

        return Command::SUCCESS;
    }

    /**
     * Writes the content in the directory, under a hidden temporary name; the path written, or null when the write failed.
     */
    private function write(string $directory, string|false $content): ?string
    {
        if (false === $content) {
            return null;
        }

        $temporary = $directory.'/.'.random_int(\PHP_INT_MIN, \PHP_INT_MAX);

        if (\strlen($content) !== @file_put_contents($temporary, $content)) {
            @unlink($temporary);

            return null;
        }

        return $temporary;
    }

    /**
     * @param array<string, string> $written the temporary files to remove
     */
    private function fail(SymfonyStyle $io, string $directory, string $file, array $written): int
    {
        foreach ($written as $path) {
            @unlink($path);
        }

        $io->error(\sprintf('The file "%s" cannot be written in %s; the previous sitemap is kept.', $file, $directory));

        return Command::FAILURE;
    }

    /**
     * Removes the files of an earlier dump the new one does not have.
     *
     * @param list<string> $kept
     */
    private function removeStale(string $directory, array $kept): void
    {
        foreach (new DirectoryIterator($directory) as $earlier) {
            $name = $earlier->getFilename();

            if (1 === preg_match('/^sitemap\..+\.xml(?:\.gz)?$/D', $name) && !\in_array($name, $kept, true)) {
                @unlink($earlier->getPathname());
            }
        }
    }

    private function baseUrl(): string
    {
        $scheme = $this->context->getScheme();
        $port = 'https' === $scheme ? $this->context->getHttpsPort() : $this->context->getHttpPort();
        $default = 'https' === $scheme ? 443 : 80;

        return $scheme.'://'.$this->context->getHost().($port === $default ? '' : ':'.$port).$this->context->getBaseUrl();
    }
}
