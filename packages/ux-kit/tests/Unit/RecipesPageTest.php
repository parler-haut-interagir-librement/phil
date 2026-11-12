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
 * The web root of the browser suites: the pages in each mode, the stylesheet, Stimulus, the controllers of the kit and
 * the media files of the resources.
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
        $this->put('resources/page.html.twig', '<p>{{ dir }} {{ theme }}</p>');
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
        self::assertStringEqualsFile($root.'/index.html', '<p>ltr light</p>');
        self::assertStringEqualsFile($root.'/dark.html', '<p>ltr dark</p>');
        self::assertStringEqualsFile($root.'/rtl.html', '<p>rtl light</p>');
        self::assertStringEqualsFile($root.'/sheet.css', 'sheet');
        self::assertStringEqualsFile($root.'/stimulus.js', 'stimulus');
        self::assertStringEqualsFile($root.'/app.js', 'app');
        self::assertStringEqualsFile($root.'/controllers/one_controller.js', 'one');
        self::assertFileDoesNotExist($root.'/controllers/notes.txt');
        self::assertStringEqualsFile($root.'/media/clip.webm', 'webm');
        self::assertStringEqualsFile($root.'/media/clip.vtt', 'WEBVTT');
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
