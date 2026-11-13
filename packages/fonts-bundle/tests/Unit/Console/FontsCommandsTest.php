<?php

declare(strict_types=1);

namespace Phil\FontsBundle\Tests\Unit\Console;

use Override;
use Phil\FontsBundle\Application\FontCatalogue;
use Phil\FontsBundle\Application\FontFaces;
use Phil\FontsBundle\Application\FontFiles;
use Phil\FontsBundle\Application\Licences;
use Phil\FontsBundle\Infrastructure\Console\CheckLicencesCommand;
use Phil\FontsBundle\Infrastructure\Console\FontFacesCommand;
use Phil\FontsBundle\Infrastructure\Console\ListFontsCommand;
use Phil\FontsBundle\Tests\Unit\Fixture\Directory;
use Phil\FontsBundle\Tests\Unit\Fixture\Fonts;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

#[CoversClass(ListFontsCommand::class)]
#[CoversClass(CheckLicencesCommand::class)]
#[CoversClass(FontFacesCommand::class)]
final class FontsCommandsTest extends TestCase
{
    #[Override]
    protected function tearDown(): void
    {
        Directory::clean();
    }

    public function testListsEachFaceAndWhyAWoff2FileIsNotRead(): void
    {
        $directory = Directory::with([
            'Sans.ttf' => Fonts::trueType('Example Sans', 'Bold', 700, 0x0004),
            'Sans.woff2' => Fonts::woff2('Example Sans'),
            'Both.ttc' => Fonts::collection([['One', 'Regular'], ['Two', 'Italic']]),
            'Unknown.ttf' => Fonts::trueType('Unknown', licenceUrl: ''),
        ]);
        $tester = new CommandTester(new ListFontsCommand(new FontCatalogue($directory)));

        self::assertSame(Command::SUCCESS, $tester->execute([]));
        $display = $tester->getDisplay();
        self::assertMatchesRegularExpression('/Both\.ttc\s+collection\s+One\s+normal\s+400\s+installable\s+\?/', $display);
        self::assertMatchesRegularExpression('/Both\.ttc\s+collection\s+Two\s+normal\s+400/', $display);
        self::assertMatchesRegularExpression('/Sans\.ttf\s+truetype\s+Example Sans\s+normal\s+700\s+preview-and-print\s+OFL-1\.1/', $display);
        self::assertMatchesRegularExpression('/Sans\.woff2\s+woff2\s+Not read: ext-brotli is not loaded/', $display);
        self::assertMatchesRegularExpression('/Unknown\.ttf\s+truetype\s+Unknown\s+normal\s+400\s+installable\s+\?/', $display);
        self::assertStringContainsString('File', $display);
        self::assertStringContainsString('Licence', $display);
        self::assertStringContainsString('WOFF2 files are read when ext-brotli is loaded; without it, their WOFF, TrueType or OpenType files are read instead.', $display);
    }

    public function testWithBrotliWoff2IsListedAndNoNoteIsShown(): void
    {
        $tester = new CommandTester(new ListFontsCommand(new FontCatalogue(Directory::with(['Sans.woff2' => Fonts::woff2('Example Sans')]), new FontFiles(str_pad(...)))));

        self::assertSame(Command::SUCCESS, $tester->execute([]));
        self::assertMatchesRegularExpression('/Sans\.woff2\s+woff2\s+Example Sans/', $tester->getDisplay());
        self::assertStringNotContainsString('ext-brotli', $tester->getDisplay());
    }

    public function testNoNoteWithoutWoff2Files(): void
    {
        $tester = new CommandTester(new ListFontsCommand(new FontCatalogue(Directory::with(['Sans.ttf' => Fonts::trueType('Example Sans')]))));
        $tester->execute([]);

        self::assertStringNotContainsString('ext-brotli', $tester->getDisplay());
    }

    public function testAFileThatIsNoFontFails(): void
    {
        $tester = new CommandTester(new ListFontsCommand(new FontCatalogue(Directory::with(['Sans.woff2' => Fonts::woff2('Example Sans'), 'broken.ttf' => 'no font']))));

        self::assertSame(Command::FAILURE, $tester->execute([]));
        self::assertMatchesRegularExpression('/broken\.ttf\s+truetype\s+The font file "broken\.ttf" cannot be read/', $tester->getDisplay());
    }

    public function testAWoff2FileWithoutBrotliIsNoFailure(): void
    {
        $tester = new CommandTester(new ListFontsCommand(new FontCatalogue(Directory::with(['Sans.woff2' => Fonts::woff2('Example Sans'), 'Sans.ttf' => Fonts::trueType('Example Sans')]))));

        self::assertSame(Command::SUCCESS, $tester->execute([]));
    }

    public function testAnEmptyDirectory(): void
    {
        $tester = new CommandTester(new ListFontsCommand(new FontCatalogue('/nonexistent/fonts')));

        self::assertSame(Command::SUCCESS, $tester->execute([]));
        self::assertSame("There is no font file in \"/nonexistent/fonts\".\n", $tester->getDisplay());
    }

    public function testListsTheFilesWhoseLicenceMustBeCheckedThenRecordsThem(): void
    {
        $directory = Directory::with(['fonts/Sans.ttf' => Fonts::trueType('Example Sans'), 'fonts/Serif.ttf' => Fonts::trueType('Example Serif')]);
        $licences = new Licences(new FontCatalogue($directory.'/fonts'), $directory.'/licences.json');
        $tester = new CommandTester(new CheckLicencesCommand($licences));

        self::assertSame(Command::FAILURE, $tester->execute([]));
        self::assertSame("Check the licence of Sans.ttf: added.\nCheck the licence of Serif.ttf: added.\nThen run phil:fonts:licences --write.\n", $tester->getDisplay());
        self::assertFileDoesNotExist($directory.'/licences.json');

        self::assertSame(Command::SUCCESS, $tester->execute(['--write' => true]));
        self::assertSame(\sprintf("The licence register \"%s/licences.json\" records 2 font file(s).\n", $directory), $tester->getDisplay());

        self::assertSame(Command::SUCCESS, $tester->execute([]));
        self::assertSame("The licence register records every font file as it is.\n", $tester->getDisplay());

        file_put_contents($directory.'/fonts/Sans.ttf', Fonts::trueType('Example Sans', 'Bold', 700));
        unlink($directory.'/fonts/Serif.ttf');
        $tester = new CommandTester(new CheckLicencesCommand(new Licences(new FontCatalogue($directory.'/fonts'), $directory.'/licences.json')));

        self::assertSame(Command::FAILURE, $tester->execute([]));
        self::assertSame("Check the licence of Sans.ttf: changed.\nCheck the licence of Serif.ttf: removed.\nThen run phil:fonts:licences --write.\n", $tester->getDisplay());
    }

    public function testWritesTheRulesToTheOutputOrAFile(): void
    {
        $directory = Directory::with(['fonts/Sans.ttf' => Fonts::trueType('Example Sans')]);
        $faces = new FontFaces(new FontCatalogue($directory.'/fonts'));
        $tester = new CommandTester(new FontFacesCommand($faces));

        self::assertSame(Command::SUCCESS, $tester->execute([]));
        self::assertSame($faces->css(), $tester->getDisplay());

        self::assertSame(Command::SUCCESS, $tester->execute(['--output' => $directory.'/css/fonts.css']));
        self::assertSame($faces->css(), file_get_contents($directory.'/css/fonts.css'));
        self::assertSame(\sprintf("The @font-face rules are written to \"%s/css/fonts.css\".\n", $directory), $tester->getDisplay());
    }
}
