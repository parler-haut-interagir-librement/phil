<?php

declare(strict_types=1);

namespace Phil\BrailleExportLiblouis\Tests\Unit;

use Closure;
use InvalidArgumentException;
use Phil\BrailleExport\Contraction;
use Phil\BrailleExport\Exception\UnsupportedTranslation;
use Phil\BrailleExportLiblouis\Exception\ExecutableNotFound;
use Phil\BrailleExportLiblouis\Exception\LiblouisException;
use Phil\BrailleExportLiblouis\Exception\LiblouisFailed;
use Phil\BrailleExportLiblouis\Exception\OutdatedLiblouis;
use Phil\BrailleExportLiblouis\Exception\TableNotFound;
use Phil\BrailleExportLiblouis\LiblouisTables;
use Phil\BrailleExportLiblouis\LiblouisTranslator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\LogLevel;
use RuntimeException;
use Symfony\Component\Process\Exception\ProcessSignaledException;
use Symfony\Component\Process\ExecutableFinder;

#[CoversClass(LiblouisTranslator::class)]
#[CoversClass(LiblouisFailed::class)]
#[CoversClass(LiblouisException::class)]
#[CoversClass(ExecutableNotFound::class)]
#[CoversClass(TableNotFound::class)]
#[CoversClass(OutdatedLiblouis::class)]
final class LiblouisTranslatorTest extends TestCase
{
    private ?FakeBinary $binary = null;

    protected function tearDown(): void
    {
        $this->binary?->remove();
    }

    public function testABatchGoesToOneRunOneTextPerLine(): void
    {
        $binary = $this->binary("printf '⠁⠃ ⠉\\n⠙\\n'");

        $braille = new LiblouisTranslator(binary: $binary->path)->translate(['ab c', '', " d\t\x01"], 'en-GB', Contraction::Contracted);

        self::assertSame(["\u{2801}\u{2803}\u{2800}\u{2809}", '', "\u{2819}"], $braille);
        self::assertSame(['--forward', 'unicode.dis,en-ueb-g2.ctb'], $binary->arguments());
        self::assertSame("ab c\nd\n", $binary->input());
        self::assertSame('', $binary->tablePath());
    }

    public function testTheTablesTheDisplayTableAndTheTablePathAreConfigurable(): void
    {
        $binary = $this->binary("printf '⠁\\r\\n'");
        $translator = new LiblouisTranslator(
            LiblouisTables::defaults()->with('de', Contraction::Uncontracted, 'de-g1.ctb'),
            $binary->path,
            displayTable: 'my-unicode.dis',
            tablePath: '/opt/tables,/srv/tables',
        );

        self::assertSame(["\u{2801}"], $translator->translate(['a'], 'de', Contraction::Uncontracted));
        self::assertSame(['--forward', 'my-unicode.dis,de-g1.ctb'], $binary->arguments());
        self::assertSame('/opt/tables,/srv/tables', $binary->tablePath());
    }

    public function testALongTextIsSentInPiecesCutBetweenWords(): void
    {
        $binary = $this->binary("printf '⠁\\n⠃\\n⠉\\n⠙\\n'");
        $long = str_repeat('a', 990).' '.str_repeat('b', 20).' c';
        $unbroken = str_repeat('é', 600);

        $braille = new LiblouisTranslator(binary: $binary->path)->translate([$long, $unbroken], 'fr', Contraction::Uncontracted);

        self::assertSame(["\u{2801}\u{2800}\u{2803}", "\u{2809}\u{2800}\u{2819}"], $braille);
        self::assertSame(str_repeat('a', 990)."\n".str_repeat('b', 20).' c'."\n".str_repeat('é', 500)."\n".str_repeat('é', 100)."\n", $binary->input());
    }

    public function testAPieceIsCutAtTheLastSpaceThatFits(): void
    {
        $binary = $this->binary("printf '⠁\\n⠃\\n'");
        $text = str_repeat('a', 1000).' '.str_repeat('b', 5);

        new LiblouisTranslator(binary: $binary->path)->translate([$text], 'fr', Contraction::Uncontracted);

        self::assertSame(str_repeat('a', 1000)."\n".str_repeat('b', 5)."\n", $binary->input());
    }

    public function testATextOfExactlyOneLineIsNotCut(): void
    {
        $binary = $this->binary("printf '⠁\\n'");

        new LiblouisTranslator(binary: $binary->path)->translate([str_repeat('a', 1000)], 'fr', Contraction::Uncontracted);

        self::assertSame(str_repeat('a', 1000)."\n", $binary->input());
    }

    public function testASpaceAtTheStartOfAPieceIsNoPlaceToCut(): void
    {
        $binary = $this->binary("printf '⠁\\n⠃\\n⠉\\n'");
        $text = str_repeat('a', 1000).'  '.str_repeat('b', 1200);

        new LiblouisTranslator(binary: $binary->path)->translate([$text], 'fr', Contraction::Uncontracted);

        self::assertSame(str_repeat('a', 1000)."\n".' '.str_repeat('b', 999)."\n".str_repeat('b', 201)."\n", $binary->input());
    }

    public function testNothingToTranslateRunsNothing(): void
    {
        $binary = $this->binary('exit 1');
        $translator = new LiblouisTranslator(binary: $binary->path);

        self::assertSame(['', ''], $translator->translate(['', " \n "], 'fr', Contraction::Uncontracted));
        self::assertSame([], $translator->translate([], 'fr', Contraction::Uncontracted));
        self::assertFalse($binary->ran());
    }

    public function testTheLanguagesAreThoseOfTheTables(): void
    {
        $translator = new LiblouisTranslator();

        self::assertTrue($translator->supports('fr-CA', Contraction::Contracted));
        self::assertTrue($translator->supports('en', Contraction::Uncontracted));
        self::assertFalse($translator->supports('de', Contraction::Uncontracted));

        $this->expectException(UnsupportedTranslation::class);
        $this->expectExceptionMessageIsOrContains('No translator writes contracted braille for the language "de".');

        $translator->translate(['a'], 'de', Contraction::Contracted);
    }

    public function testAMissingProgramIsReported(): void
    {
        $translator = new LiblouisTranslator(binary: 'phil-no-such-liblouis-program');

        self::assertFalse($translator->isAvailable());

        try {
            $translator->translate(['a'], 'fr', Contraction::Uncontracted);
            self::fail('The program does not exist.');
        } catch (ExecutableNotFound $executableNotFound) {
            self::assertSame('The liblouis program "phil-no-such-liblouis-program" is not installed or not executable.', $executableNotFound->getMessage());
            self::assertNull($executableNotFound->exitCode);
        }
    }

    public function testAMissingPathIsReported(): void
    {
        $binary = $this->binary('true');
        chmod($binary->path, 0o600);

        self::assertFalse(new LiblouisTranslator(binary: $binary->directory.'/missing')->isAvailable());
        self::assertFalse(new LiblouisTranslator(binary: $binary->path)->isAvailable());
        self::assertFalse(new LiblouisTranslator(binary: $binary->directory)->isAvailable());

        $this->expectException(ExecutableNotFound::class);

        new LiblouisTranslator(binary: $binary->path)->translate(['a'], 'fr', Contraction::Uncontracted);
    }

    public function testAProgramOnThePathIsFound(): void
    {
        self::assertTrue(new LiblouisTranslator(binary: 'sh')->isAvailable());
    }

    public function testAMissingTableIsReported(): void
    {
        $binary = $this->binary("echo \"ERROR: Cannot resolve table 'fr-bfu-g2.ctb'\" >&2\nexit 1");

        try {
            new LiblouisTranslator(binary: $binary->path)->translate(['a'], 'fr', Contraction::Contracted);
            self::fail('The table does not exist.');
        } catch (TableNotFound $tableNotFound) {
            self::assertSame('liblouis cannot find the table "fr-bfu-g2.ctb".', $tableNotFound->getMessage());
            self::assertSame(1, $tableNotFound->exitCode);
            self::assertSame("ERROR: Cannot resolve table 'fr-bfu-g2.ctb'\n", $tableNotFound->errorOutput);
        }
    }

    public function testAFailureIsReportedWithItsErrorOutput(): void
    {
        $binary = $this->binary("echo 'broken' >&2\nexit 3");

        try {
            new LiblouisTranslator(binary: $binary->path)->translate(['a'], 'fr', Contraction::Contracted);
            self::fail('The program failed.');
        } catch (LiblouisException $liblouisException) {
            self::assertInstanceOf(LiblouisFailed::class, $liblouisException);
            self::assertSame('liblouis stopped with exit code 3.', $liblouisException->getMessage());
            self::assertSame(3, $liblouisException->exitCode);
            self::assertSame("broken\n", $liblouisException->errorOutput);
        }
    }

    public function testTheErrorOutputIsBounded(): void
    {
        $binary = $this->binary("head -c 70000 /dev/zero | tr '\\\\0' x >&2\nexit 2");

        try {
            new LiblouisTranslator(binary: $binary->path)->translate(['a'], 'fr', Contraction::Contracted);
            self::fail('The program failed.');
        } catch (LiblouisFailed $liblouisFailed) {
            self::assertSame(65_536, \strlen($liblouisFailed->errorOutput));
        }
    }

    public function testTheTimeIsBounded(): void
    {
        $binary = $this->binary("echo 'slow' >&2\nsleep 5");

        try {
            new LiblouisTranslator(binary: $binary->path, timeout: 0.3)->translate(['a'], 'fr', Contraction::Contracted);
            self::fail('The program ran out of time.');
        } catch (LiblouisFailed $liblouisFailed) {
            self::assertSame(\sprintf('"%s" ran out of time.', $binary->path), $liblouisFailed->getMessage());
            self::assertNull($liblouisFailed->exitCode);
            self::assertSame("slow\n", $liblouisFailed->errorOutput);
        }
    }

    public function testTheInputIsBounded(): void
    {
        $binary = $this->binary("printf '⠁\\n'");
        $translator = new LiblouisTranslator(binary: $binary->path, maxInputBytes: 4);

        self::assertSame(["\u{2801}"], $translator->translate(['abc'], 'fr', Contraction::Uncontracted));

        $this->expectException(LiblouisFailed::class);
        $this->expectExceptionMessageIsOrContains('The texts are over the limit of 4 bytes.');

        $translator->translate(['abcd'], 'fr', Contraction::Uncontracted);
    }

    public function testTheOutputIsBounded(): void
    {
        $binary = $this->binary("head -c 200000 /dev/zero | tr '\\\\0' x\nsleep 5");

        $this->expectException(LiblouisFailed::class);
        $this->expectExceptionMessageIsOrContains('liblouis wrote more than the limit of 1000 bytes.');

        new LiblouisTranslator(binary: $binary->path, timeout: 20.0, maxOutputBytes: 1000)->translate(['a'], 'fr', Contraction::Uncontracted);
    }

    public function testAnOutputAtTheLimitIsRead(): void
    {
        $binary = $this->binary("printf '⠁\\n'");

        self::assertSame(["\u{2801}"], new LiblouisTranslator(binary: $binary->path, maxOutputBytes: 4)->translate(['a'], 'fr', Contraction::Uncontracted));
    }

    public function testTheErrorOutputCountsInTheLimit(): void
    {
        $binary = $this->binary("printf 'xx' >&2\nprintf '⠁\\n'");

        $this->expectException(LiblouisFailed::class);
        $this->expectExceptionMessageIsOrContains('liblouis wrote more than the limit of 5 bytes.');

        new LiblouisTranslator(binary: $binary->path, maxOutputBytes: 5)->translate(['a'], 'fr', Contraction::Uncontracted);
    }

    public function testAnswersOfTheWrongCountAreRefused(): void
    {
        $binary = $this->binary("printf '⠁\\n⠃\\n'");

        try {
            new LiblouisTranslator(binary: $binary->path)->translate(['a'], 'fr', Contraction::Uncontracted);
            self::fail('The answer has too many lines.');
        } catch (LiblouisFailed $liblouisFailed) {
            self::assertSame('liblouis gave 2 lines back for 1.', $liblouisFailed->getMessage());
            self::assertSame(0, $liblouisFailed->exitCode);
        }
    }

    public function testAnswersThatAreNotBrailleAreRefused(): void
    {
        $binary = $this->binary("printf '⠁\\nb\\n'");

        try {
            new LiblouisTranslator(binary: $binary->path)->translate(['a', 'b'], 'fr', Contraction::Uncontracted);
            self::fail('The answer is not braille.');
        } catch (LiblouisFailed $liblouisFailed) {
            self::assertSame('liblouis gave line 2 back with characters that are not braille cells: is the display table unicode.dis?', $liblouisFailed->getMessage());
            self::assertSame(0, $liblouisFailed->exitCode);
        }
    }

    public function testTheVersionIsRead(): void
    {
        $binary = $this->binary("echo 'lou_translate (liblouis) 3.21.0'", null);

        self::assertSame('3.21.0', new LiblouisTranslator(binary: $binary->path)->version());
        self::assertSame(['--version'], $binary->arguments());
    }

    public function testAShortVersionIsRead(): void
    {
        $binary = $this->binary("echo 'liblouis 3.9'", null);

        self::assertSame('3.9', new LiblouisTranslator(binary: $binary->path)->version());
    }

    public function testAnAnswerWithoutAVersionIsRefused(): void
    {
        $binary = $this->binary("echo 'lou_translate 3'", null);

        try {
            new LiblouisTranslator(binary: $binary->path)->version();
            self::fail('There is no version.');
        } catch (LiblouisFailed $liblouisFailed) {
            self::assertSame('liblouis did not say which version it is.', $liblouisFailed->getMessage());
            self::assertSame("lou_translate 3\n", $liblouisFailed->errorOutput);
            self::assertSame(0, $liblouisFailed->exitCode);
        }
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function acceptedVersions(): iterable
    {
        yield 'the oldest accepted' => ['lou_translate (liblouis) 3.39.0'];
        yield 'a later patch' => ['lou_translate (liblouis) 3.39.1'];
        yield 'a later minor' => ['liblouis 3.40'];
        yield 'a later major' => ['lou_translate (liblouis) 4.0.0'];
    }

    #[DataProvider('acceptedVersions')]
    public function testALiblouisFrom3390OnTranslates(string $answer): void
    {
        $binary = $this->binary("printf '⠁\\n'", $answer);
        $logger = new RecordingLogger();

        self::assertSame(["\u{2801}"], new LiblouisTranslator(binary: $binary->path, logger: $logger)->translate(['a'], 'fr', Contraction::Uncontracted));
        self::assertTrue($binary->ran());
        self::assertSame([], $logger->records);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function outdatedVersions(): iterable
    {
        yield 'the last patch before' => ['lou_translate (liblouis) 3.38.9', '3.38.9'];
        yield 'the minor before' => ['lou_translate (liblouis) 3.38.0', '3.38.0'];
        yield 'a short version' => ['liblouis 3.9', '3.9'];
        yield 'an older major' => ['liblouis 2.6.5', '2.6.5'];
    }

    #[DataProvider('outdatedVersions')]
    public function testALiblouisOlderThan3390IsRefusedBeforeTranslating(string $answer, string $version): void
    {
        $binary = $this->binary("printf '⠁\\n'", $answer);

        try {
            new LiblouisTranslator(binary: $binary->path)->translate(['a'], 'fr', Contraction::Uncontracted);
            self::fail('liblouis is too old.');
        } catch (OutdatedLiblouis $outdatedLiblouis) {
            self::assertSame(\sprintf('liblouis %s is older than 3.39.0, the oldest version accepted: update liblouis.', $version), $outdatedLiblouis->getMessage());
            self::assertSame(0, $outdatedLiblouis->exitCode);
            self::assertSame('', $outdatedLiblouis->errorOutput);
        }

        self::assertFalse($binary->ran(), 'Nothing is sent to an outdated liblouis.');
    }

    public function testAVersionThatCannotBeReadIsRefusedBeforeTranslating(): void
    {
        $binary = $this->binary("printf '⠁\\n'", 'lou_translate, a version of its own');

        try {
            new LiblouisTranslator(binary: $binary->path)->translate(['a'], 'fr', Contraction::Uncontracted);
            self::fail('There is no version.');
        } catch (LiblouisFailed $liblouisFailed) {
            self::assertSame('liblouis did not say which version it is.', $liblouisFailed->getMessage());
            self::assertSame("lou_translate, a version of its own\n", $liblouisFailed->errorOutput);
        }

        self::assertFalse($binary->ran());
    }

    public function testAnOutdatedLiblouisAllowedTranslatesWithAWarning(): void
    {
        $binary = $this->binary("printf '⠁\\n'", 'lou_translate (liblouis) 3.38.2');
        $logger = new RecordingLogger();
        $translator = new LiblouisTranslator(binary: $binary->path, allowOutdatedLiblouis: true, logger: $logger);

        self::assertSame(["\u{2801}"], $translator->translate(['a'], 'fr', Contraction::Uncontracted));
        self::assertSame(["\u{2801}"], $translator->translate(['a'], 'fr', Contraction::Uncontracted));
        self::assertSame([[
            LogLevel::WARNING,
            'liblouis {version} is older than {minimum}; it translates anyway, as allowOutdatedLiblouis lets it.',
            ['version' => '3.38.2', 'minimum' => '3.39.0'],
        ]], $logger->records, 'One warning, the first time.');
    }

    public function testAVersionThatCannotBeReadAllowedTranslatesWithAWarning(): void
    {
        $binary = $this->binary("printf '⠁\\n'", 'no version here');
        $logger = new RecordingLogger();
        $translator = new LiblouisTranslator(binary: $binary->path, allowOutdatedLiblouis: true, logger: $logger);

        self::assertSame(["\u{2801}"], $translator->translate(['a'], 'fr', Contraction::Uncontracted));
        self::assertSame(["\u{2801}"], $translator->translate(['a'], 'fr', Contraction::Uncontracted));
        self::assertSame([[
            LogLevel::WARNING,
            'liblouis did not say which version it is; it translates anyway, as allowOutdatedLiblouis lets it.',
            ['minimum' => '3.39.0'],
        ]], $logger->records);
    }

    public function testAnOutdatedLiblouisAllowedNeedsNoLogger(): void
    {
        $binary = $this->binary("printf '⠁\\n'", 'liblouis 3.20.0');

        self::assertSame(["\u{2801}"], new LiblouisTranslator(binary: $binary->path, allowOutdatedLiblouis: true)->translate(['a'], 'fr', Contraction::Uncontracted));
    }

    public function testTheVersionIsAskedOnceWithTheTablePath(): void
    {
        $binary = $this->binary(<<<'SH'
            if [ "$1" = --version ]; then
              echo asked >> "$(dirname "$0")/versions"
              printf '%s' "$LOUIS_TABLEPATH" > "$(dirname "$0")/version-tablepath"
              echo 'lou_translate (liblouis) 3.39.0'
            else
              printf '⠁\n'
            fi
            SH, null);
        $translator = new LiblouisTranslator(binary: $binary->path, tablePath: '/srv/tables');

        $translator->translate(['a'], 'fr', Contraction::Uncontracted);
        $translator->translate(['b'], 'fr', Contraction::Uncontracted);
        self::assertSame('3.39.0', $translator->version());

        self::assertSame("asked\n", file_get_contents($binary->directory.'/versions'));
        self::assertSame('/srv/tables', file_get_contents($binary->directory.'/version-tablepath'));
        self::assertSame(['--forward', 'unicode.dis,fr-bfu-comp6.utb'], $binary->arguments());
    }

    public function testARefusedLiblouisIsRefusedAgain(): void
    {
        $binary = $this->binary("printf '⠁\\n'", 'liblouis 3.38.0');
        $translator = new LiblouisTranslator(binary: $binary->path);

        foreach ([1, 2] as $attempt) {
            try {
                $translator->translate(['a'], 'fr', Contraction::Uncontracted);
                self::fail('liblouis is too old, attempt '.$attempt.'.');
            } catch (OutdatedLiblouis) {
                self::assertFalse($binary->ran());
            }
        }
    }

    public function testTheVersionAnswerIsBounded(): void
    {
        $binary = $this->binary('exit 0', str_repeat('3', 5_000));

        try {
            new LiblouisTranslator(binary: $binary->path)->version();
            self::fail('The answer is over its limit.');
        } catch (LiblouisFailed $liblouisFailed) {
            self::assertSame('liblouis wrote more than the limit of 4096 bytes.', $liblouisFailed->getMessage());
        }
    }

    public function testAVersionAnswerAtItsLimitIsRead(): void
    {
        $binary = $this->binary('exit 0', '3.39.0'.str_repeat(' ', 4_089));

        self::assertSame('3.39.0', new LiblouisTranslator(binary: $binary->path, maxOutputBytes: 1)->version());
    }

    public function testTheVersionQuestionHasTheTimeOfABatch(): void
    {
        $binary = $this->binary('sleep 5', null);

        try {
            new LiblouisTranslator(binary: $binary->path, timeout: 0.3)->version();
            self::fail('The program ran out of time.');
        } catch (LiblouisFailed $liblouisFailed) {
            self::assertSame(\sprintf('"%s" ran out of time.', $binary->path), $liblouisFailed->getMessage());
        }
    }

    /**
     * @return iterable<string, array{Closure(): LiblouisTranslator, string}>
     */
    public static function invalidArguments(): iterable
    {
        yield 'a zero timeout' => [static fn (): LiblouisTranslator => new LiblouisTranslator(timeout: 0.0), 'The timeout of liblouis must be positive.'];
        yield 'a zero input' => [static fn (): LiblouisTranslator => new LiblouisTranslator(maxInputBytes: 0), 'The limits of liblouis must be positive.'];
        yield 'a zero output' => [static fn (): LiblouisTranslator => new LiblouisTranslator(maxOutputBytes: 0), 'The limits of liblouis must be positive.'];
        yield 'a display table path' => [static fn (): LiblouisTranslator => new LiblouisTranslator(displayTable: '../unicode.dis'), '"../unicode.dis" is not the name of a liblouis table.'];
        yield 'a display table list' => [static fn (): LiblouisTranslator => new LiblouisTranslator(displayTable: 'a.dis,b.dis'), '"a.dis,b.dis" is not the name of a liblouis table.'];
        yield 'a display table with a line feed' => [static fn (): LiblouisTranslator => new LiblouisTranslator(displayTable: "unicode.dis\n"), 'is not the name of a liblouis table.'];
    }

    /**
     * @param Closure(): LiblouisTranslator $make
     */
    #[DataProvider('invalidArguments')]
    public function testTheArgumentsAreChecked(Closure $make, string $message): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageIsOrContains($message);

        $make();
    }

    public function testTheSmallestLimitsAreAccepted(): void
    {
        $binary = $this->binary("printf '⠁'");

        self::assertSame(["\u{2801}"], new LiblouisTranslator(binary: $binary->path, timeout: 0.5, maxInputBytes: 2, maxOutputBytes: 3, displayTable: 'u')->translate(['a'], 'fr', Contraction::Uncontracted));
    }

    public function testNotRunNamesTheCause(): void
    {
        $previous = new RuntimeException('no pipe');
        $exception = LiblouisFailed::notRun('lou_translate', $previous);

        self::assertSame('"lou_translate" did not run to its end: no pipe', $exception->getMessage());
        self::assertSame($previous, $exception->getPrevious());
        self::assertNull($exception->exitCode);
        self::assertSame('', $exception->errorOutput);
    }

    public function testTheLimitsOfOneByteAreAccepted(): void
    {
        $translator = new LiblouisTranslator(maxInputBytes: 1, maxOutputBytes: 1);

        self::assertTrue($translator->supports('fr', Contraction::Uncontracted));
    }

    public function testTheAnswerIsAListWhateverTheKeysOfTheTexts(): void
    {
        $binary = $this->binary("printf '⠁\\n⠃\\n'");

        // @phpstan-ignore argument.type (texts whose keys are not a list)
        $braille = new LiblouisTranslator(binary: $binary->path)->translate([3 => 'a', 7 => 'b'], 'fr', Contraction::Uncontracted);

        self::assertSame(["\u{2801}", "\u{2803}"], $braille);
    }

    public function testATextOfOneLineWithASpaceIsNotCut(): void
    {
        $binary = $this->binary("printf '⠁\\n'");
        $text = str_repeat('a', 499).' '.str_repeat('b', 500);

        new LiblouisTranslator(binary: $binary->path)->translate([$text], 'fr', Contraction::Uncontracted);

        self::assertSame($text."\n", $binary->input());
    }

    public function testAPieceIsNeverCutInsideACharacter(): void
    {
        $binary = $this->binary("printf '⠁\\n⠃\\n'");
        $text = 'a'.str_repeat('é', 600);

        new LiblouisTranslator(binary: $binary->path)->translate([$text], 'fr', Contraction::Uncontracted);

        self::assertSame('a'.str_repeat('é', 499)."\n".str_repeat('é', 101)."\n", $binary->input());
    }

    public function testAProgramNamedWithASlashIsNotLookedForOnThePath(): void
    {
        $shell = new ExecutableFinder()->find('sh');
        self::assertNotNull($shell);

        // `../bin/sh` reaches the shell from the directory of the path that holds it; from a new directory of its own,
        // the working directory, it reaches nothing.
        $relative = '../'.basename(\dirname($shell)).'/sh';
        $directory = $this->binary('exit 0')->directory;
        $working = getcwd();
        self::assertIsString($working);
        chdir($directory);

        try {
            self::assertFileDoesNotExist($relative);
            self::assertFalse(new LiblouisTranslator(binary: $relative)->isAvailable());
            self::assertTrue(new LiblouisTranslator(binary: './lou_translate')->isAvailable(), 'a program named from the working directory');
        } finally {
            chdir($working);
        }
    }

    public function testAProcessKilledBySignalIsReportedAsNotRun(): void
    {
        $binary = $this->binary('kill -9 $$');

        try {
            new LiblouisTranslator(binary: $binary->path)->translate(['a'], 'fr', Contraction::Uncontracted);
            self::fail('The program was killed.');
        } catch (LiblouisFailed $liblouisFailed) {
            self::assertStringStartsWith(\sprintf('"%s" did not run to its end: ', $binary->path), $liblouisFailed->getMessage());
            self::assertInstanceOf(ProcessSignaledException::class, $liblouisFailed->getPrevious());
            self::assertNull($liblouisFailed->exitCode);
        }
    }

    private function binary(string $script, ?string $version = 'lou_translate (liblouis) '.LiblouisTranslator::MINIMUM_VERSION): FakeBinary
    {
        return $this->binary = new FakeBinary($script, $version);
    }
}
