<?php

declare(strict_types=1);

namespace Phil\UxKit\Tests\Unit;

use FilesystemIterator;
use Override;
use Phil\UxKit\Application\ImageMigration;
use Phil\UxKit\Infrastructure\Console\MigrateImagesCommand;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

#[CoversClass(MigrateImagesCommand::class)]
final class MigrateImagesCommandTest extends TestCase
{
    private const string PROPOSED = '<img src="/a.png" alt="A" width="1" height="1">';

    private const string LEFT = '<img src="/b.png">';

    private string $project = '';

    #[Override]
    protected function setUp(): void
    {
        $this->project = sys_get_temp_dir().'/phil-ux-kit-migrate-'.bin2hex(random_bytes(4));
        mkdir($this->project.'/templates/page', 0o777, true);
        file_put_contents($this->project.'/templates/page/show.html.twig', "<main>\n    ".self::PROPOSED."\n    ".self::LEFT."\n</main>\n");
        file_put_contents($this->project.'/templates/base.html.twig', '<p>No image</p>');
        file_put_contents($this->project.'/templates/notes.txt', self::PROPOSED);
        file_put_contents($this->project.'/templates/old.twig.bak', self::PROPOSED);
        mkdir($this->project.'/other');
        file_put_contents($this->project.'/other/card.html.twig', "<picture>\n<source srcset=\"/c.webp\">\n<img src=\"/c.png\"\n alt=\"C\" width=\"2\" height=\"2\">\n</picture>");
    }

    #[Override]
    protected function tearDown(): void
    {
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($this->project, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);

        foreach ($files as $file) {
            if ($file instanceof SplFileInfo) {
                $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
            }
        }

        rmdir($this->project);
    }

    public function testTheTemplatesOfTheProjectAreReadAndLeftUnchanged(): void
    {
        $tester = $this->tester();

        self::assertSame(Command::SUCCESS, $tester->execute([]));
        self::assertSame(
            $this->project."/templates/page/show.html.twig:2 proposed\n"
            .'  - '.self::PROPOSED."\n"
            ."  + <twig:Image src=\"/a.png\" alt=\"A\" :width=\"1\" :height=\"1\" />\n"
            .$this->project."/templates/page/show.html.twig:3 left as it is\n"
            .'  - '.self::LEFT."\n"
            ."    The image has no text alternative: write its alt, or alt=\"\" for a decorative image.\n"
            ."    The image has no width: give its size, so that its space is kept while it loads.\n"
            ."    The image has no height: give its size, so that its space is kept while it loads.\n"
            ."1 element(s) proposed, 1 left as they are.\n"
            ."Read the proposals, then run again with --write to rewrite them.\n",
            $tester->getDisplay(),
        );
        self::assertSame("<main>\n    ".self::PROPOSED."\n    ".self::LEFT."\n</main>\n", file_get_contents($this->project.'/templates/page/show.html.twig'));
    }

    public function testWriteRewritesTheProposalsOnly(): void
    {
        $tester = $this->tester();

        self::assertSame(Command::SUCCESS, $tester->execute(['--write' => true]));
        self::assertStringContainsString('show.html.twig:2 rewritten', $tester->getDisplay());
        self::assertStringContainsString('show.html.twig:3 left as it is', $tester->getDisplay());
        self::assertStringEndsWith("1 element(s) rewritten, 1 left as they are.\n", $tester->getDisplay());
        self::assertSame("<main>\n    <twig:Image src=\"/a.png\" alt=\"A\" :width=\"1\" :height=\"1\" />\n    ".self::LEFT."\n</main>\n", file_get_contents($this->project.'/templates/page/show.html.twig'));
        self::assertSame('<p>No image</p>', file_get_contents($this->project.'/templates/base.html.twig'));
        self::assertSame(self::PROPOSED, file_get_contents($this->project.'/templates/notes.txt'), 'a file that is no Twig template');
        self::assertSame(self::PROPOSED, file_get_contents($this->project.'/templates/old.twig.bak'), 'nor a copy of one');
    }

    public function testThePathsGivenAreReadInTheOrderOfTheirNamesAndAMultilineElementOnOneLine(): void
    {
        $tester = $this->tester();

        self::assertSame(Command::SUCCESS, $tester->execute(['paths' => [$this->project.'/missing', $this->project.'/templates/page/show.html.twig', $this->project.'/other', $this->project.'/other/card.html.twig']]));

        $display = $tester->getDisplay();
        self::assertStringStartsWith($this->project."/missing: no template or directory there.\n".$this->project."/other/card.html.twig:1 proposed\n  - <picture> <source srcset=\"/c.webp\"> <img src=\"/c.png\" alt=\"C\" width=\"2\" height=\"2\"> </picture>\n", $display);
        self::assertSame(1, substr_count($display, 'card.html.twig:1'));
        self::assertStringContainsString('show.html.twig:2 proposed', $display);
        self::assertStringContainsString("2 element(s) proposed, 1 left as they are.\n", $display);
    }

    public function testWriteLeavesATemplateWithNothingProposedUntouched(): void
    {
        $file = $this->project.'/templates/page/show.html.twig';
        file_put_contents($file, self::LEFT);
        touch($file, 1_000_000_000);
        clearstatcache();

        self::assertSame(Command::SUCCESS, $this->tester()->execute(['paths' => [$file], '--write' => true]));
        clearstatcache();
        self::assertSame(1_000_000_000, filemtime($file));
    }

    public function testNothingToProposeSaysSo(): void
    {
        $tester = $this->tester();

        self::assertSame(Command::SUCCESS, $tester->execute(['paths' => [$this->project.'/templates/base.html.twig']]));
        self::assertSame("0 element(s) proposed, 0 left as they are.\n", $tester->getDisplay());
    }

    public function testTheCommandIsNamedAndDescribed(): void
    {
        $command = new MigrateImagesCommand(new ImageMigration(), $this->project);

        self::assertSame('phil:ux-kit:migrate-images', $command->getName());
        self::assertTrue($command->getDefinition()->getArgument('paths')->isArray());
        self::assertFalse($command->getDefinition()->getArgument('paths')->isRequired());
        self::assertFalse($command->getDefinition()->getOption('write')->acceptValue());
    }

    private function tester(): CommandTester
    {
        return new CommandTester(new MigrateImagesCommand(new ImageMigration(), $this->project));
    }
}
