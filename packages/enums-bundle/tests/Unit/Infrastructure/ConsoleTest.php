<?php

declare(strict_types=1);

namespace Phil\EnumsBundle\Tests\Unit\Infrastructure;

use Phil\EnumsBundle\Infrastructure\Console\CheckSetColumnsCommand;
use Phil\EnumsBundle\Infrastructure\Console\DebugEnumsCommand;
use Phil\EnumsBundle\Infrastructure\Console\ExportIcuCommand;
use Phil\EnumsBundle\Infrastructure\Console\ExportTypeScriptCommand;
use Phil\EnumsBundle\Tests\Unit\Fixture\Access;
use Phil\EnumsBundle\Tests\Unit\Fixture\TicketKind;
use Phil\EnumsBundle\Tests\Unit\Icu\Hyphenated;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Translation\IdentityTranslator;
use Symfony\Component\Translation\Loader\ArrayLoader;
use Symfony\Component\Translation\Translator;

#[CoversClass(DebugEnumsCommand::class)]
#[CoversClass(ExportTypeScriptCommand::class)]
#[CoversClass(ExportIcuCommand::class)]
#[CoversClass(CheckSetColumnsCommand::class)]
final class ConsoleTest extends TestCase
{
    private const string FIXTURES = __DIR__.'/../Fixture';

    public function testTheCommandsAreNamed(): void
    {
        self::assertSame('debug:enums', new DebugEnumsCommand([])->getName());
        self::assertSame('phil:enums:typescript', new ExportTypeScriptCommand([])->getName());
    }

    public function testDebugListsTheEnumsOfThePaths(): void
    {
        $tester = new CommandTester(new DebugEnumsCommand([self::FIXTURES]));

        self::assertSame(Command::SUCCESS, $tester->execute([]));
        $display = $this->display($tester);
        self::assertStringContainsString(' Enum Backing Cases Translatable ', $display);
        self::assertMatchesRegularExpression('/Fixture\\\Access +int +3 +no/', $display);
        self::assertMatchesRegularExpression('/Fixture\\\OrderStatus +string +4 +yes/', $display);
        self::assertMatchesRegularExpression('/Fixture\\\Shape +pure +4 +no/', $display);
    }

    public function testDebugDescribesTheCasesOfAnEnumNamedByItsShortName(): void
    {
        $tester = new CommandTester(new DebugEnumsCommand([self::FIXTURES]));

        self::assertSame(Command::SUCCESS, $tester->execute(['enum' => 'Access']));
        $display = $this->display($tester);
        self::assertStringContainsString(Access::class, $display);
        self::assertStringContainsString(' Case Value Label Description Groups Weight Marks ', $display);
        self::assertMatchesRegularExpression('/Read +1 +Read +basic +1 +default/', $display);
        self::assertMatchesRegularExpression('/Write +2 +Write +basic, editor +1 /', $display);
        self::assertMatchesRegularExpression('/Admin +4 +Admin +Every right\. +0 +deprecated/', $display);
    }

    public function testDebugAcceptsTheClassWithOrWithoutItsLeadingBackslash(): void
    {
        self::assertSame(Command::SUCCESS, new CommandTester(new DebugEnumsCommand([self::FIXTURES]))->execute(['enum' => '\\'.Access::class]));
        self::assertSame(Command::SUCCESS, new CommandTester(new DebugEnumsCommand([self::FIXTURES]))->execute(['enum' => Access::class]));
    }

    public function testDebugFailsOnAnUnknownOrAmbiguousName(): void
    {
        $tester = new CommandTester(new DebugEnumsCommand([self::FIXTURES]));

        self::assertSame(Command::FAILURE, $tester->execute(['enum' => 'Kind']));
        self::assertSame(Command::FAILURE, $tester->execute(['enum' => 'Nothing']));
        self::assertStringContainsString('No enum named "Nothing" in the configured paths.', $this->display($tester));

        $tester = new CommandTester(new DebugEnumsCommand([self::FIXTURES, __DIR__.'/../Ambiguous']));

        self::assertSame(Command::FAILURE, $tester->execute(['enum' => 'Shape']));
        self::assertStringContainsString('"Shape" names several enums', $this->display($tester));
    }

    public function testDebugReportsTheRequiredMetadataCasesMiss(): void
    {
        $tester = new CommandTester(new DebugEnumsCommand([__DIR__.'/../Ambiguous']));

        self::assertSame(Command::FAILURE, $tester->execute(['enum' => 'Shape']));
        self::assertStringContainsString('Square misses the required Phil\Enums\Attribute\Label.', $this->display($tester));
    }

    public function testDebugChecksTheTranslationsOfALocale(): void
    {
        $translator = new Translator('fr');
        $translator->addLoader('array', new ArrayLoader());
        $translator->addResource('array', ['ticket.ticket_kind.bug_report' => 'Anomalie'], 'fr', 'support');
        $translator->addResource('array', ['ticket.ticket_kind.bug_report' => 'Bug', 'ticket.ticket_kind.feature_request' => 'Feature'], 'en', 'support');

        $tester = new CommandTester(new DebugEnumsCommand([self::FIXTURES], $translator));

        self::assertSame(Command::FAILURE, $tester->execute(['enum' => 'TicketKind', '--locale' => 'fr']));
        self::assertStringContainsString(' Case Missing key Domain ', $this->display($tester));
        self::assertMatchesRegularExpression('/FEATURE_REQUEST +ticket\.ticket_kind\.feature_request +support/', $this->display($tester));
        self::assertStringNotContainsString('BugReport  ', substr($this->display($tester), (int) strpos($this->display($tester), 'Missing key')));
        self::assertStringContainsString('1 key(s) of '.TicketKind::class.' have no translation in "fr".', $this->display($tester));

        self::assertSame(Command::SUCCESS, $tester->execute(['enum' => 'TicketKind', '--locale' => 'en']));
        self::assertStringContainsString('Every key of '.TicketKind::class.' is translated in "en".', $this->display($tester));
    }

    public function testDebugChecksTheDescriptionKeysToo(): void
    {
        $translator = new Translator('fr');
        $translator->addLoader('array', new ArrayLoader());
        $translator->addResource('array', ['Pending' => 'En attente', 'In transit' => 'En transit', 'Cancelled' => 'Annulée', 'enum.order_status.refunded' => 'Remboursée', 'The parcel has left.' => 'Parti.'], 'fr', 'enums');

        $tester = new CommandTester(new DebugEnumsCommand([self::FIXTURES], $translator));

        self::assertSame(Command::FAILURE, $tester->execute(['enum' => 'OrderStatus', '--locale' => 'fr']));
        self::assertMatchesRegularExpression('/PENDING +Waiting for payment\. +enums/', $this->display($tester));
        self::assertStringContainsString('1 key(s)', $this->display($tester));
    }

    public function testDebugCannotCheckTranslationsWithoutCataloguesOrOfAnEnumThatIsNotTranslatable(): void
    {
        $tester = new CommandTester(new DebugEnumsCommand([self::FIXTURES]));
        self::assertSame(Command::SUCCESS, $tester->execute(['enum' => 'TicketKind', '--locale' => 'fr']));
        self::assertStringContainsString('No translator with catalogues', $this->display($tester));

        $tester = new CommandTester(new DebugEnumsCommand([self::FIXTURES], new IdentityTranslator()));
        self::assertSame(Command::SUCCESS, $tester->execute(['enum' => 'TicketKind', '--locale' => 'fr']));
        self::assertStringContainsString('No translator with catalogues', $this->display($tester));

        $tester = new CommandTester(new DebugEnumsCommand([self::FIXTURES], new Translator('fr')));
        self::assertSame(Command::SUCCESS, $tester->execute(['enum' => 'Access', '--locale' => 'fr']));
        self::assertStringContainsString(Access::class.' is not translatable.', $this->display($tester));
    }

    public function testTypeScriptGoesToTheOutputForEveryEnumOfThePaths(): void
    {
        $tester = new CommandTester(new ExportTypeScriptCommand([self::FIXTURES]));

        self::assertSame(Command::SUCCESS, $tester->execute([]));
        $display = $this->display($tester);
        self::assertStringStartsWith('// Generated by phil/enums-bundle; do not edit.', $display);
        self::assertStringContainsString('export const Access = {', $display);
        self::assertStringContainsString('export const TicketKind = {', $display);
        self::assertStringContainsString('"pending": "Pending",', $display);
    }

    public function testTypeScriptWritesTheNamedEnumsToAFileWithTranslatedLabels(): void
    {
        $translator = new Translator('en');
        $translator->addLoader('array', new ArrayLoader());
        $translator->addResource('array', ['ticket.ticket_kind.bug_report' => 'Anomalie'], 'fr', 'support');

        $file = sys_get_temp_dir().'/phil-enums-'.bin2hex(random_bytes(4)).'.ts';

        $tester = new CommandTester(new ExportTypeScriptCommand([self::FIXTURES], $translator));

        try {
            self::assertSame(Command::SUCCESS, $tester->execute(['enums' => [TicketKind::class, Access::class], '--output' => $file, '--locale' => 'fr']));
            // SymfonyStyle wraps a long path anywhere, so the comparison ignores every space.
            self::assertStringContainsString(str_replace(' ', '', '2 enum(s) written to '.$file.'.'), str_replace(' ', '', $this->display($tester)));
            $source = (string) file_get_contents($file);
            self::assertStringContainsString('  1: "Anomalie",', $source);
            self::assertStringContainsString('  2: "ticket.ticket_kind.feature_request",', $source);
            self::assertStringContainsString('  1: "Read",', $source);
            self::assertStringNotContainsString('OrderStatus', $source);
        } finally {
            @unlink($file);
        }
    }

    public function testTypeScriptWithoutLocaleOrTranslatorKeepsTheLabelsAsWritten(): void
    {
        $translator = new Translator('fr');
        $translator->addLoader('array', new ArrayLoader());
        $translator->addResource('array', ['ticket.ticket_kind.bug_report' => 'Anomalie'], 'fr', 'support');

        $tester = new CommandTester(new ExportTypeScriptCommand([self::FIXTURES], $translator));
        $tester->execute(['enums' => [TicketKind::class]]);
        self::assertStringContainsString('1: "BugReport",', $this->display($tester));

        $tester = new CommandTester(new ExportTypeScriptCommand([self::FIXTURES]));
        $tester->execute(['enums' => [TicketKind::class], '--locale' => 'fr']);
        self::assertStringContainsString('1: "BugReport",', $this->display($tester));
    }

    public function testTypeScriptCheckComparesWithTheFileWithoutWriting(): void
    {
        $file = sys_get_temp_dir().'/phil-enums-check-'.bin2hex(random_bytes(4)).'.ts';
        $tester = new CommandTester(new ExportTypeScriptCommand([self::FIXTURES]));

        try {
            self::assertSame(Command::FAILURE, $tester->execute(['enums' => [Access::class], '--output' => $file, '--check' => true]));
            self::assertStringContainsString('is not up to date: run the command without --check.', $this->display($tester));
            self::assertFileDoesNotExist($file);

            $tester->execute(['enums' => [Access::class], '--output' => $file]);
            self::assertSame(Command::SUCCESS, $tester->execute(['enums' => [Access::class], '--output' => $file, '--check' => true]));
            self::assertStringContainsString('[OK]', $this->display($tester));
            self::assertStringContainsString('is up to date.', $this->display($tester));

            file_put_contents($file, "// edited\n", \FILE_APPEND);
            self::assertSame(Command::FAILURE, $tester->execute(['enums' => [Access::class], '--output' => $file, '--check' => true]));
        } finally {
            @unlink($file);
        }
    }

    public function testTypeScriptCheckNeedsAFile(): void
    {
        $tester = new CommandTester(new ExportTypeScriptCommand([self::FIXTURES]));

        self::assertSame(Command::FAILURE, $tester->execute(['enums' => [Access::class], '--check' => true]));
        self::assertStringContainsString('--check compares with a file: give it with --output.', $this->display($tester));
    }

    public function testCheckSetsWithoutDoctrineSaysSo(): void
    {
        $tester = new CommandTester(new CheckSetColumnsCommand());

        self::assertSame(Command::FAILURE, $tester->execute([]));
        self::assertStringContainsString('Doctrine is not configured: register DoctrineBundle to check the EnumSet columns.', $this->display($tester));
    }

    public function testIcuWritesASelectOverTheCases(): void
    {
        $tester = new CommandTester(new ExportIcuCommand());

        self::assertSame('phil:enums:icu', new ExportIcuCommand()->getName());
        self::assertSame(Command::SUCCESS, $tester->execute(['enum' => Access::class]));
        self::assertSame("{value, select, 1 {Read} 2 {Write} 4 {Admin} other {}}\n", $tester->getDisplay());
        self::assertSame(Command::SUCCESS, $tester->execute(['enum' => Access::class, '--argument' => 'access']));
        self::assertStringStartsWith('{access, select, ', $tester->getDisplay());
    }

    public function testIcuStartsFromTranslatedLabelsWithALocale(): void
    {
        $translator = new Translator('en');
        $translator->addLoader('array', new ArrayLoader());
        $translator->addResource('array', ['ticket.ticket_kind.bug_report' => 'Anomalie'], 'fr', 'support');

        $tester = new CommandTester(new ExportIcuCommand($translator));

        $tester->execute(['enum' => TicketKind::class, '--locale' => 'fr']);
        self::assertSame("{value, select, 1 {Anomalie} 2 {ticket.ticket_kind.feature_request} other {}}\n", $tester->getDisplay());

        $tester->execute(['enum' => Access::class, '--locale' => 'fr']);
        self::assertStringContainsString('1 {Read}', $tester->getDisplay());

        $tester->execute(['enum' => TicketKind::class]);
        self::assertStringContainsString('1 {BugReport}', $tester->getDisplay());
    }

    public function testIcuRefusesAClassThatIsNotAnEnum(): void
    {
        $tester = new CommandTester(new ExportIcuCommand());

        self::assertSame(Command::FAILURE, $tester->execute(['enum' => self::class]));
        self::assertStringContainsString('Not an enum: '.self::class.'.', $this->display($tester));
    }

    public function testIcuRefusesAnEnumWhoseKeysIcuWouldRefuse(): void
    {
        $tester = new CommandTester(new ExportIcuCommand());

        self::assertSame(Command::FAILURE, $tester->execute(['enum' => Hyphenated::class]));
        self::assertStringContainsString('is not an ICU keyword', $this->display($tester));
    }

    public function testTypeScriptRefusesAClassThatIsNotAnEnum(): void
    {
        $tester = new CommandTester(new ExportTypeScriptCommand([self::FIXTURES]));

        self::assertSame(Command::FAILURE, $tester->execute(['enums' => [Access::class, self::class, 'Nope']]));
        self::assertStringContainsString('Not an enum: '.self::class.', Nope.', $this->display($tester));
    }

    public function testTypeScriptFailsWhenTheFileCannotBeWritten(): void
    {
        $tester = new CommandTester(new ExportTypeScriptCommand([self::FIXTURES]));

        self::assertSame(Command::FAILURE, $tester->execute(['enums' => [Access::class], '--output' => __DIR__.'/missing/dir/enums.ts']));
        self::assertStringContainsString('Cannot write', $this->display($tester));
    }

    /**
     * The output with its whitespace collapsed: a long message wraps at the terminal's width.
     */
    private function display(CommandTester $tester): string
    {
        return (string) preg_replace('/\s+/', ' ', $tester->getDisplay());
    }
}
