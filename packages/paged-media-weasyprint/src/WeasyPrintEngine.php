<?php

declare(strict_types=1);

namespace Phil\PagedMediaWeasyprint;

use InvalidArgumentException;
use Phil\PagedMediaBundle\Application\Contract\PrintEngine;
use Phil\PagedMediaBundle\Application\PrintableHtml;
use Phil\PagedMediaWeasyprint\Exception\WeasyPrintFailed;
use Symfony\Component\Process\Exception\ExceptionInterface;
use Symfony\Component\Process\Exception\ProcessTimedOutException;
use Symfony\Component\Process\Process;

/**
 * The print engine that lays a page out with the `weasyprint` command.
 *
 * The command reads the document on its standard input and writes the PDF on its standard output, so no temporary
 * file exists. It is told to fetch `data:` addresses and nothing else, which leaves the disk and the network out of its
 * reach; the files of a {@see LocalAssets} directory reach it already written as `data:` addresses.
 */
final readonly class WeasyPrintEngine implements PrintEngine
{
    /** The oldest version of WeasyPrint the package is written and tested for. */
    public const string MINIMUM_VERSION = '70.0';

    private const float VERSION_TIMEOUT = 30.0;

    /**
     * @param string           $binary  the command, a name found on the `PATH` or a path
     * @param float            $timeout the seconds a document may take, in the end what bounds a costly layout
     * @param LocalAssets|null $assets  the directory the document may take files from; none without it
     * @param PdfOptions|null  $pdf     the standard, output intent, tags, forms and images of the PDF; WeasyPrint's
     *                                  defaults without it
     */
    public function __construct(
        private string $binary = 'weasyprint',
        private float $timeout = 120.0,
        private ?LocalAssets $assets = null,
        private ?PdfOptions $pdf = null,
    ) {
        if ($timeout <= 0.0) {
            throw new InvalidArgumentException('The timeout of WeasyPrint must be positive.');
        }
    }

    public function pdf(PrintableHtml $page): string
    {
        $document = $page->document();

        if ($this->assets instanceof LocalAssets) {
            $document = $this->assets->inline($document);
        }

        $process = new Process(
            [
                $this->binary,
                '--allowed-protocols', 'data',
                '--base-url', '',
                '--encoding', 'utf-8',
                ...$this->pdf?->arguments() ?? [],
                '-', '-',
            ],
            timeout: $this->timeout,
        );
        $process->setInput($document);
        $this->run($process);

        $pdf = $process->getOutput();

        if (!str_starts_with($pdf, '%PDF-')) {
            throw WeasyPrintFailed::notPdf($process->getErrorOutput());
        }

        return $pdf;
    }

    /**
     * The version the command reports, such as `70.0`.
     */
    public function version(): string
    {
        $process = new Process([$this->binary, '--version'], timeout: self::VERSION_TIMEOUT);
        $this->run($process);

        if (1 !== preg_match('/\d+(?:\.\d+)+/', $process->getOutput(), $match)) {
            throw WeasyPrintFailed::noVersion($process->getOutput());
        }

        return $match[0];
    }

    /**
     * Stops on a command older than {@see self::MINIMUM_VERSION}: a check for a deployment or a health probe, as a
     * command too old to know the arguments of {@see self::pdf()} refuses them anyway.
     */
    public function assertSupported(): void
    {
        $version = $this->version();

        if (version_compare($version, self::MINIMUM_VERSION, '<')) {
            throw WeasyPrintFailed::tooOld($version, self::MINIMUM_VERSION);
        }
    }

    private function run(Process $process): void
    {
        try {
            $process->run();
        } catch (ProcessTimedOutException $exception) {
            throw WeasyPrintFailed::timedOut($this->binary, $process->getErrorOutput(), $exception);
        } catch (ExceptionInterface $exception) {
            throw WeasyPrintFailed::notRun($this->binary, $exception);
        }

        if (!$process->isSuccessful()) {
            throw WeasyPrintFailed::exited((int) $process->getExitCode(), $process->getErrorOutput());
        }
    }
}
