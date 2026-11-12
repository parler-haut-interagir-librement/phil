<?php

declare(strict_types=1);

namespace Phil\AccessibilityCheck\Tests\Unit\Console;

use Phil\AccessibilityCheck\AccessibilityChecker;
use Phil\AccessibilityCheck\Console\CheckCommand;
use Phil\AccessibilityCheck\Policy;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

#[CoversClass(CheckCommand::class)]
final class CheckCommandTest extends TestCase
{
    private string $directory = '';

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir().'/phil-accessibility-check-'.bin2hex(random_bytes(4));
        mkdir($this->directory);
    }

    protected function tearDown(): void
    {
        $files = glob($this->directory.'/*');

        foreach (false === $files ? [] : $files as $file) {
            unlink($file);
        }

        rmdir($this->directory);
    }

    public function testTheCommandIsNamed(): void
    {
        self::assertSame('phil:accessibility:check', new CheckCommand()->getName());
    }

    public function testAnHtmlFileIsCheckedFindingByFinding(): void
    {
        $file = $this->file('page.html', "<p>x</p>\n<img src=\"a.png\"><h2>a</h2><h4>b</h4>");
        $tester = new CommandTester(new CheckCommand());

        self::assertSame(Command::FAILURE, $tester->execute(['file' => $file]));
        self::assertSame(
            "[error] image-alt (WCAG 1.1.1, A) line 2, img: The image a.png has no text alternative: describe it in an alt text, or give it alt=\"\" when it is only decoration.\n"
            ."[warning] heading-order (WCAG 1.3.1, A) line 2, h4: This h4 heading follows an h2: a level is skipped.\n"
            ."2 finding(s): 1 error(s), 1 warning(s), 0 notice(s).\n",
            $tester->getDisplay(),
        );
    }

    public function testTheMinimumSeverityDecidesTheOutcome(): void
    {
        $tester = new CommandTester(new CheckCommand());

        self::assertSame(Command::SUCCESS, $tester->execute(['file' => '<h2>a</h2><h4>b</h4>']));
        self::assertSame(Command::FAILURE, $tester->execute(['file' => '<h2>a</h2><h4>b</h4>', '--min-severity' => 'warning']));
        self::assertSame(Command::SUCCESS, $tester->execute(['file' => '<p lang="">x</p>', '--min-severity' => 'warning']));
        self::assertStringContainsString('1 finding(s): 0 error(s), 0 warning(s), 1 notice(s).', $tester->getDisplay());
    }

    public function testTheReportIsWrittenInJsonInTheLocale(): void
    {
        $tester = new CommandTester(new CheckCommand());
        $tester->execute(['file' => '<a href="/x">ici</a>', '--format' => 'json', '--locale' => 'fr', '--language' => 'fr']);

        /** @var array{locale: string, counts: array{warning: int}, findings: list<array{message: string}>} $report */
        $report = json_decode($tester->getDisplay(), true, 512, \JSON_THROW_ON_ERROR);

        self::assertSame('fr', $report['locale']);
        self::assertSame(1, $report['counts']['warning']);
        self::assertSame('Le texte du lien vers /x, « ici », ne dit pas où il mène : décrivez sa destination.', $report['findings'][0]['message']);
        self::assertStringContainsString('"href": "/x"', $tester->getDisplay());
        self::assertStringContainsString('« ici »', $tester->getDisplay());
    }

    public function testTheLanguageChoosesTheGenericLinkTexts(): void
    {
        $tester = new CommandTester(new CheckCommand());

        self::assertSame(Command::SUCCESS, $tester->execute(['file' => '<a href="https://example.com/">mehr</a>', '--min-severity' => 'warning', '--language' => 'fr']));
        self::assertSame(Command::FAILURE, $tester->execute(['file' => '<a href="https://example.com/">mehr</a>', '--min-severity' => 'warning']));
    }

    public function testAUjoDocumentIsReadFromAJsonFileOrOnRequest(): void
    {
        $json = (string) json_encode(['blocks' => [['id' => 'i1', 'type' => 'image', 'data' => ['file' => ['url' => '/a.png'], 'caption' => '']]]]);
        $tester = new CommandTester(new CheckCommand());

        self::assertSame(Command::FAILURE, $tester->execute(['file' => $this->file('Article.JSON', $json)]));
        self::assertStringContainsString('[error] image-alt (WCAG 1.1.1, A) block "i1" (image), blocks[0]:', $tester->getDisplay());
        self::assertSame(Command::FAILURE, $tester->execute(['file' => $this->file('article.ujo', $json), '--ujo' => true]));
        self::assertSame(Command::FAILURE, $tester->execute(['file' => $json, '--ujo' => true]));
        self::assertSame(Command::FAILURE, $tester->execute(['file' => "\n  ".$json, '--ujo' => true]));
    }

    public function testAnInvalidDocumentIsReported(): void
    {
        $tester = new CommandTester(new CheckCommand());

        self::assertSame(Command::INVALID, $tester->execute(['file' => '{"blocks": 3}', '--ujo' => true]));
        self::assertStringContainsString('blocks', $tester->getDisplay());
    }

    public function testAContentTheCheckerRefusesIsInvalid(): void
    {
        $tester = new CommandTester(new CheckCommand(new AccessibilityChecker(new Policy(maxBytes: 8))));

        self::assertSame(Command::INVALID, $tester->execute(['file' => $this->file('long.html', '<p>123456</p>')]));
        self::assertStringContainsString('The content holds 9 bytes; at most 8 are checked.', $this->display($tester));
    }

    public function testAnEmptyFileHasNothingToFind(): void
    {
        $tester = new CommandTester(new CheckCommand());

        self::assertSame(Command::SUCCESS, $tester->execute(['file' => $this->file('empty.html', '')]));
        self::assertStringContainsString('0 finding(s)', $tester->getDisplay());
    }

    public function testAnAddressIsNeverFetched(): void
    {
        $tester = new CommandTester(new CheckCommand());

        self::assertSame(Command::INVALID, $tester->execute(['file' => 'https://example.com/<page>']));
        self::assertStringContainsString('never addresses', $this->display($tester));
        self::assertSame(Command::INVALID, $tester->execute(['file' => 'HTTPS://example.com/<page>']));
        self::assertSame(Command::INVALID, $tester->execute(['file' => 'no-such-file.html']));
        self::assertSame(Command::INVALID, $tester->execute(['file' => $this->directory]));
    }

    public function testTheOptionsAreChecked(): void
    {
        $tester = new CommandTester(new CheckCommand());

        self::assertSame(Command::INVALID, $tester->execute(['file' => '<p>x</p>', '--format' => 'xml']));
        self::assertStringContainsString('The format is text or json', $this->display($tester));
        self::assertSame(Command::INVALID, $tester->execute(['file' => '<p>x</p>', '--min-severity' => 'fatal']));
    }

    /**
     * The output, its lines joined: a console block wraps at the width of the terminal.
     */
    private function display(CommandTester $tester): string
    {
        return (string) preg_replace('/\s+/', ' ', $tester->getDisplay());
    }

    private function file(string $name, string $content): string
    {
        file_put_contents($this->directory.'/'.$name, $content);

        return $this->directory.'/'.$name;
    }
}
