<?php

declare(strict_types=1);

namespace Phil\PagedMediaWeasyprint\Tests\Unit;

use InvalidArgumentException;
use Phil\PagedMediaBundle\Application\PrintableHtml;
use Phil\PagedMediaWeasyprint\Exception\WeasyPrintFailed;
use Phil\PagedMediaWeasyprint\LocalAssets;
use Phil\PagedMediaWeasyprint\PdfOptions;
use Phil\PagedMediaWeasyprint\PdfVariant;
use Phil\PagedMediaWeasyprint\WeasyPrintEngine;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Exception\ProcessTimedOutException;

#[CoversClass(WeasyPrintEngine::class)]
#[CoversClass(WeasyPrintFailed::class)]
final class WeasyPrintEngineTest extends TestCase
{
    private ?FakeBinary $binary = null;

    protected function tearDown(): void
    {
        $this->binary?->remove();
    }

    public function testTheDocumentGoesInOnStandardInputAndThePdfComesOut(): void
    {
        $binary = $this->binary("printf '%%PDF-1.7 made'");

        $pdf = new WeasyPrintEngine($binary->path)->pdf(new PrintableHtml('<p>Hello</p>', ['p { color: red }'], 'fr', 'Book'));

        self::assertSame('%PDF-1.7 made', $pdf);
        self::assertSame(new PrintableHtml('<p>Hello</p>', ['p { color: red }'], 'fr', 'Book')->document(), $binary->input());
    }

    public function testTheCommandMayFetchDataAddressesAndNothingElse(): void
    {
        $binary = $this->binary("printf '%%PDF-1.7'");

        new WeasyPrintEngine($binary->path)->pdf(new PrintableHtml('<p>Hello</p>'));

        self::assertSame(['--allowed-protocols', 'data', '--base-url', '', '--encoding', 'utf-8', '-', '-'], $binary->arguments());
    }

    public function testThePdfOptionsComeBeforeTheInputAndOutput(): void
    {
        $binary = $this->binary("printf '%%PDF-1.7'");

        new WeasyPrintEngine($binary->path, pdf: new PdfOptions(PdfVariant::PdfX4, '--press', tags: true))->pdf(new PrintableHtml('<p>Hello</p>'));

        self::assertSame(
            ['--allowed-protocols', 'data', '--base-url', '', '--encoding', 'utf-8', '--pdf-variant=pdf/x-4', '--output-intent=--press', '--pdf-tags', '-', '-'],
            $binary->arguments(),
        );
    }

    public function testAnAddressInTheDocumentReachesNoArgument(): void
    {
        $binary = $this->binary("printf '%%PDF-1.7'");

        new WeasyPrintEngine($binary->path)->pdf(new PrintableHtml('--allowed-protocols=file'));

        self::assertNotContains('--allowed-protocols=file', $binary->arguments());
    }

    public function testTheFilesOfTheAssetsDirectoryGoIntoTheDocument(): void
    {
        $binary = $this->binary("printf '%%PDF-1.7'");
        mkdir($binary->directory.'/assets');
        file_put_contents($binary->directory.'/assets/logo.png', 'logo');

        new WeasyPrintEngine($binary->path, assets: new LocalAssets($binary->directory.'/assets'))->pdf(new PrintableHtml('<img src="logo.png">'));

        self::assertStringContainsString('data:image/png;base64,'.base64_encode('logo'), $binary->input());
    }

    public function testAFailureKeepsItsExitCodeAndErrorOutput(): void
    {
        $binary = $this->binary("echo 'it broke' >&2\nexit 3");

        try {
            new WeasyPrintEngine($binary->path)->pdf(new PrintableHtml('<p>Hello</p>'));
            self::fail('The failure was not reported.');
        } catch (WeasyPrintFailed $weasyPrintFailed) {
            self::assertSame(3, $weasyPrintFailed->exitCode);
            self::assertSame("it broke\n", $weasyPrintFailed->errorOutput);
            self::assertSame('WeasyPrint stopped with exit code 3.', $weasyPrintFailed->getMessage());
        }
    }

    public function testTheErrorOutputKeptIsBounded(): void
    {
        $binary = $this->binary("head -c 70000 /dev/zero >&2\nexit 1");

        try {
            new WeasyPrintEngine($binary->path)->pdf(new PrintableHtml('<p>Hello</p>'));
            self::fail('The failure was not reported.');
        } catch (WeasyPrintFailed $weasyPrintFailed) {
            self::assertSame(65_536, \strlen($weasyPrintFailed->errorOutput));
        }
    }

    public function testAnAnswerThatIsNotAPdfIsAFailure(): void
    {
        $binary = $this->binary("echo 'odd' >&2\nprintf 'not a pdf'");

        try {
            new WeasyPrintEngine($binary->path)->pdf(new PrintableHtml('<p>Hello</p>'));
            self::fail('The failure was not reported.');
        } catch (WeasyPrintFailed $weasyPrintFailed) {
            self::assertSame(0, $weasyPrintFailed->exitCode);
            self::assertSame("odd\n", $weasyPrintFailed->errorOutput);
            self::assertSame('WeasyPrint did not write a PDF.', $weasyPrintFailed->getMessage());
        }
    }

    public function testAPdfMarkAfterTheStartIsNotAPdf(): void
    {
        $binary = $this->binary("printf 'x%%PDF-1.7'");

        $this->expectException(WeasyPrintFailed::class);

        new WeasyPrintEngine($binary->path)->pdf(new PrintableHtml('<p>Hello</p>'));
    }

    public function testACommandThatTakesTooLongIsStopped(): void
    {
        $binary = $this->binary("echo 'slow' >&2\nexec sleep 30");

        try {
            new WeasyPrintEngine($binary->path, 0.2)->pdf(new PrintableHtml('<p>Hello</p>'));
            self::fail('The failure was not reported.');
        } catch (WeasyPrintFailed $weasyPrintFailed) {
            self::assertNull($weasyPrintFailed->exitCode);
            self::assertSame(\sprintf('"%s" ran out of time.', $binary->path), $weasyPrintFailed->getMessage());
            self::assertInstanceOf(ProcessTimedOutException::class, $weasyPrintFailed->getPrevious());
        }
    }

    public function testACommandThatCannotBeFoundIsAFailure(): void
    {
        $this->expectException(WeasyPrintFailed::class);

        new WeasyPrintEngine('/nonexistent/weasyprint')->pdf(new PrintableHtml('<p>Hello</p>'));
    }

    public function testACommandKilledBeforeItsEndIsAFailure(): void
    {
        $binary = $this->binary('kill -9 $$');

        try {
            new WeasyPrintEngine($binary->path)->pdf(new PrintableHtml('<p>Hello</p>'));
            self::fail('The failure was not reported.');
        } catch (WeasyPrintFailed $weasyPrintFailed) {
            self::assertNull($weasyPrintFailed->exitCode);
            self::assertStringStartsWith(\sprintf('"%s" did not run to its end: ', $binary->path), $weasyPrintFailed->getMessage());
            self::assertNotNull($weasyPrintFailed->getPrevious());
        }
    }

    public function testATimeoutMustBePositive(): void
    {
        new WeasyPrintEngine(timeout: 0.1);

        $this->expectException(InvalidArgumentException::class);

        new WeasyPrintEngine(timeout: 0.0);
    }

    public function testTheVersionIsReadFromTheCommand(): void
    {
        $binary = $this->binary('echo "WeasyPrint version 70.1"');

        self::assertSame('70.1', new WeasyPrintEngine($binary->path)->version());
        self::assertSame(['--version'], $binary->arguments());
    }

    public function testAnUnreadableVersionIsAFailure(): void
    {
        $binary = $this->binary('echo "no number here"');

        try {
            new WeasyPrintEngine($binary->path)->version();
            self::fail('The failure was not reported.');
        } catch (WeasyPrintFailed $weasyPrintFailed) {
            self::assertSame('WeasyPrint did not say which version it is.', $weasyPrintFailed->getMessage());
            self::assertSame(0, $weasyPrintFailed->exitCode);
            self::assertSame("no number here\n", $weasyPrintFailed->errorOutput);
        }
    }

    public function testASupportedVersionPasses(): void
    {
        $binary = $this->binary('echo "WeasyPrint version 70.0"');

        $this->expectNotToPerformAssertions();

        new WeasyPrintEngine($binary->path)->assertSupported();
    }

    public function testAnOlderVersionIsRefused(): void
    {
        $binary = $this->binary('echo "WeasyPrint version 69.9"');

        $this->expectException(WeasyPrintFailed::class);
        $this->expectExceptionMessageIsOrContains('WeasyPrint 69.9 is older than the 70.0 this package needs.');

        new WeasyPrintEngine($binary->path)->assertSupported();
    }

    private function binary(string $script): FakeBinary
    {
        return $this->binary = new FakeBinary($script);
    }
}
