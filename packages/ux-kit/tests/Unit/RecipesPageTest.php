<?php

declare(strict_types=1);

namespace Phil\UxKit\Tests\Unit;

use FilesystemIterator;
use LogicException;
use Override;
use Phil\UxKit\Test\RecipesPage;
use Phil\UxKit\Tests\Functional\App\TestKernel;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use SplFileInfo;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;

/**
 * The web root of the browser suites: the pages in each mode, plain and under the strict policy with its watcher, the
 * stylesheet, that of the page, Stimulus, the controllers of the kit and the media files of the resources.
 */
#[CoversClass(RecipesPage::class)]
final class RecipesPageTest extends TestCase
{
    private string $work;

    #[Override]
    protected function setUp(): void
    {
        if (!class_exists(FrameworkBundle::class)) {
            self::markTestSkipped('FrameworkBundle is installed for development only: no kernel can boot.');
        }

        $this->work = sys_get_temp_dir().'/phil-ux-kit-recipes-page-'.bin2hex(random_bytes(4));
        $this->put('resources/app.js', 'app');
        $this->put('resources/page.html.twig', '<p>{{ dir }} {{ theme }} {{ strict }}</p>');
        $this->put('kit/one/assets/controllers/one_controller.js', 'one');
        $this->put('kit/one/assets/controllers/notes.txt', 'left out');
        $this->put('sheet.css', 'sheet');
        $this->put('stimulus.js', 'stimulus');
    }

    #[Override]
    protected function tearDown(): void
    {
        if (!isset($this->work) || !is_dir($this->work)) {
            return;
        }

        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($this->work, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST) as $file) {
            self::assertInstanceOf(SplFileInfo::class, $file);
            $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
        }

        rmdir($this->work);
    }

    public function testItWritesEachModeBesideTheSheetStimulusTheControllersAndTheMedia(): void
    {
        $this->put('resources/media/clip.webm', 'webm');
        $this->put('resources/media/clip.vtt', 'WEBVTT');

        $root = $this->page()->write();

        self::assertSame($this->work.'/root', $root);
        self::assertStringEqualsFile($root.'/index.html', '<p>ltr light </p>');
        self::assertStringEqualsFile($root.'/dark.html', '<p>ltr dark </p>');
        self::assertStringEqualsFile($root.'/rtl.html', '<p>rtl light </p>');
        self::assertStringEqualsFile($root.'/sheet.css', 'sheet');
        self::assertStringEqualsFile($root.'/stimulus.js', 'stimulus');
        self::assertStringEqualsFile($root.'/app.js', 'app');
        self::assertStringEqualsFile($root.'/controllers/one_controller.js', 'one');
        self::assertFileDoesNotExist($root.'/controllers/notes.txt');
        self::assertStringEqualsFile($root.'/media/clip.webm', 'webm');
        self::assertStringEqualsFile($root.'/media/clip.vtt', 'WEBVTT');
    }

    public function testEachModeIsWrittenUnderTheStrictPolicyWithItsWatcher(): void
    {
        $root = $this->page()->write();

        $policy = '<meta http-equiv="Content-Security-Policy" content="'.\sprintf(RecipesPage::POLICY, '').'"><script src="strict.js"></script>';
        self::assertStringEqualsFile($root.'/strict.html', $policy.'<p>ltr light 1</p>');
        self::assertStringEqualsFile($root.'/strict-dark.html', $policy.'<p>ltr dark 1</p>');
        self::assertStringEqualsFile($root.'/strict-rtl.html', $policy.'<p>rtl light 1</p>');
        self::assertStringEqualsFile($root.'/index.html', '<p>ltr light </p>');
        self::assertStringContainsString("document.addEventListener('securitypolicyviolation'", (string) file_get_contents($root.'/strict.js'));
        self::assertStringContainsString('window.violations.push(`${event.effectiveDirective} ${event.blockedURI || \'inline\'} ${event.sourceFile}:${event.lineNumber}`);', (string) file_get_contents($root.'/strict.js'));
        self::assertStringStartsWith('window.violations = [];', (string) file_get_contents($root.'/strict.js'));
    }

    public function testThePolicyComesFirstInTheHeadAndAllowsEachInlineScriptThatRunsByANonce(): void
    {
        $map = '{"imports": {"a": "./a.js"}}';
        $this->put('resources/page.html.twig', '<html><HEAD lang="en"><script></script><script type="importmap">'.$map.'</script><script src="app.js"></script><script type="module">go()</script><script>go()</script><script type=\'TEXT/JavaScript\'>run()</script><script type="application/javascript">start()</script><script type="application/ld+json">{}</script><script data-src="x">later()</script></HEAD></html>');

        $root = $this->page()->write();
        $page = (string) file_get_contents($root.'/strict.html');

        self::assertSame(1, preg_match("#'nonce-([0-9a-f]{32})'#", $page, $nonce));
        self::assertSame(
            '<html><HEAD lang="en"><meta http-equiv="Content-Security-Policy" content="'.\sprintf(RecipesPage::POLICY, " 'nonce-".$nonce[1]."'").'"><script src="strict.js"></script><script></script><script nonce="N" type="importmap">'.$map.'</script><script src="app.js"></script><script nonce="N" type="module">go()</script><script nonce="N">go()</script><script nonce="N" type=\'TEXT/JavaScript\'>run()</script><script nonce="N" type="application/javascript">start()</script><script type="application/ld+json">{}</script><script nonce="N" data-src="x">later()</script></HEAD></html>',
            str_replace('nonce="'.$nonce[1].'"', 'nonce="N"', $page),
        );
        self::assertNotSame($nonce[1], $this->nonce($root.'/strict-dark.html'), 'each page has a nonce of its own');
    }

    public function testOnlyTheAttributesOfAScriptSayWhetherItRuns(): void
    {
        $this->put('resources/page.html.twig', '<head><script>let src="a"</script><script>let a, type="json"</script><script TYPE="application/ld+json">{}</script><script SRC="b.js">later()</script></head><head></head>');

        $page = (string) file_get_contents($this->page()->write().'/strict.html');
        $nonce = 'nonce="'.$this->nonce($this->work.'/root/strict.html').'"';

        self::assertSame(
            '<head><meta http-equiv="Content-Security-Policy" content="'.\sprintf(RecipesPage::POLICY, " 'nonce-".substr($nonce, 7, -1)."'").'"><script src="strict.js"></script><script '.$nonce.'>let src="a"</script><script '.$nonce.'>let a, type="json"</script><script TYPE="application/ld+json">{}</script><script SRC="b.js">later()</script></head><head></head>',
            $page,
            'the policy goes in the first head alone',
        );
    }

    public function testANonceIsAnyOfTheSixteenHexadecimalDigits(): void
    {
        $this->put('resources/page.html.twig', '<head><script>go()</script></head>');
        $digits = '';

        for ($write = 0; $write < 4; ++$write) {
            $root = $this->page()->write();

            foreach (['strict.html', 'strict-dark.html', 'strict-rtl.html'] as $file) {
                $digits .= $this->nonce($root.'/'.$file);
            }
        }

        self::assertSame('0123456789abcdef', count_chars($digits, 3));
    }

    public function testTheStrictPolicyAllowsNothingInlineButTheScriptsOfTheNonce(): void
    {
        self::assertSame(
            "default-src 'self'; script-src 'self'%s; style-src 'self'; img-src 'self' data:; font-src 'self'; connect-src 'self'; media-src 'self'; object-src 'none'; base-uri 'self'; form-action 'self'",
            RecipesPage::POLICY,
        );
    }

    public function testTheStylesheetOfThePageIsCopiedWhenThereIsOne(): void
    {
        self::assertFileDoesNotExist($this->page()->write().'/page.css');

        $this->put('resources/page.css', 'page');

        self::assertStringEqualsFile($this->page()->write().'/page.css', 'page');
    }

    public function testWithoutMediaNoDirectoryIsWrittenForThem(): void
    {
        $root = $this->page()->write();

        self::assertDirectoryDoesNotExist($root.'/media');

        mkdir($this->work.'/resources/media');

        self::assertDirectoryDoesNotExist($this->page()->write().'/media', 'an empty directory writes none');
    }

    public function testItWritesAgainOverAnExistingRoot(): void
    {
        $this->put('resources/media/clip.vtt', 'WEBVTT');
        $this->page()->write();
        $this->put('resources/media/clip.vtt', 'WEBVTT again');

        self::assertStringEqualsFile($this->page()->write().'/media/clip.vtt', 'WEBVTT again');
    }

    public function testTheKernelIsShutOnceThePagesAreWritten(): void
    {
        $kernel = new TestKernel('test', true);
        $page = new RecipesPage($kernel, $this->work.'/kit', $this->work.'/resources', $this->work.'/sheet.css', $this->work.'/root', $this->work.'/stimulus.js');

        $page->write();

        $this->expectException(LogicException::class);

        $kernel->getContainer();
    }

    public function testWithoutStimulusItSaysWhatToRun(): void
    {
        unlink($this->work.'/stimulus.js');

        try {
            $this->page()->write();
            self::fail('A page was written without Stimulus.');
        } catch (RuntimeException $runtimeException) {
            self::assertSame('Stimulus is missing at '.$this->work."/stimulus.js: run `npm ci --ignore-scripts` in the directory of the suite's package.json.", $runtimeException->getMessage());
        }
    }

    private function nonce(string $file): string
    {
        self::assertSame(1, preg_match("#'nonce-([0-9a-f]{32})'#", (string) file_get_contents($file), $nonce));

        return $nonce[1];
    }

    private function page(): RecipesPage
    {
        return new RecipesPage(
            new TestKernel('test', true),
            $this->work.'/kit',
            $this->work.'/resources',
            $this->work.'/sheet.css',
            $this->work.'/root',
            $this->work.'/stimulus.js',
        );
    }

    private function put(string $path, string $content): void
    {
        $file = $this->work.'/'.$path;

        if (!is_dir(\dirname($file))) {
            mkdir(\dirname($file), 0o777, true);
        }

        file_put_contents($file, $content);
    }
}
