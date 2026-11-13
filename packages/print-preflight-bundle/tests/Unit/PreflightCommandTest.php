<?php

declare(strict_types=1);

namespace Phil\PrintPreflightBundle\Tests\Unit;

use Override;
use Phil\PrintPreflight\PrintProfile;
use Phil\PrintPreflightBundle\Application\MeasuredDocument;
use Phil\PrintPreflightBundle\Application\Preflighter;
use Phil\PrintPreflightBundle\Application\PrintProfiles;
use Phil\PrintPreflightBundle\Infrastructure\Console\PreflightCommand;
use Phil\PrintPreflightBundle\Tests\Unit\Fixture\Directory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

#[CoversClass(PreflightCommand::class)]
final class PreflightCommandTest extends TestCase
{
    private const string DOCUMENT = '{"unit": "mm", "pages": [{"width": 148, "height": 210, "bleed": 3, "boxes": [{"id": "<b>title</b>", "x": 1, "y": 20, "width": 100, "height": 10}]}], "fonts": [{"name": "Fallback Serif", "embedded": false}]}';

    #[Override]
    protected function tearDown(): void
    {
        Directory::clean();
    }

    public function testPrintsTheFindingsAndFailsOnAnError(): void
    {
        $tester = $this->tester();

        self::assertSame(Command::FAILURE, $tester->execute(['file' => Directory::with(['doc.json' => self::DOCUMENT]).'/doc.json'], ['decorated' => true]));
        self::assertSame(
            "error  document  font.not-embedded  Fallback Serif: The font \"Fallback Serif\" is not embedded; the printer would replace it.\n"
            ."warning  page 1  placement.in-safety-margin  <b>title</b>: \"<b>title</b>\" on page 1 is inside the 5 mm safety margin at the left edge.\n"
            ."1 error(s), 1 warning(s), 0 notice(s).\n",
            $tester->getDisplay(),
        );
    }

    public function testAProfileByNameAndTheReportAsJson(): void
    {
        $tester = $this->tester();

        self::assertSame(Command::SUCCESS, $tester->execute(['file' => Directory::with(['doc.json' => self::DOCUMENT]).'/doc.json', '--profile' => 'lenient', '--format' => 'json']));
        self::assertSame("[]\n", $tester->getDisplay());
    }

    public function testTheJsonReportIsReadable(): void
    {
        $tester = $this->tester();
        $tester->execute(['file' => Directory::with(['doc.json' => self::DOCUMENT]).'/doc.json', '-f' => 'json']);

        $report = json_decode($tester->getDisplay(), true, flags: \JSON_THROW_ON_ERROR);

        self::assertIsArray($report);
        self::assertCount(2, $report);
        self::assertStringContainsString("\n    {", $tester->getDisplay());
        self::assertStringContainsString('"subject": "<b>title</b>"', $tester->getDisplay());
    }

    public function testAFileThatCannotBeReadIsInvalid(): void
    {
        $tester = $this->tester();

        self::assertSame(Command::INVALID, $tester->execute(['file' => '/nonexistent/doc.json']));
        self::assertStringContainsString('/nonexistent/doc.json', $tester->getDisplay());

        self::assertSame(Command::INVALID, $tester->execute(['file' => Directory::with(['doc.json' => '{}']).'/doc.json']));
        self::assertSame("The \"pages\" of the document must be a list of objects.\n", $tester->getDisplay());

        self::assertSame(Command::INVALID, $tester->execute(['file' => Directory::with(['doc.json' => self::DOCUMENT]).'/doc.json', '-p' => 'zine']));
        self::assertSame("There is no printer profile \"zine\"; the profiles are \"strict\", \"lenient\".\n", $tester->getDisplay());
    }

    private function tester(): CommandTester
    {
        $profiles = new PrintProfiles(['strict' => new PrintProfile(), 'lenient' => new PrintProfile(ignore: ['font.not-embedded', 'placement.in-safety-margin'])], 'strict');

        return new CommandTester(new PreflightCommand(new Preflighter($profiles), new MeasuredDocument()));
    }
}
