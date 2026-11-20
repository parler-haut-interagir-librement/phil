<?php

declare(strict_types=1);

namespace Phil\BrailleExportLiblouis;

use InvalidArgumentException;
use Phil\BrailleExport\Contract\Translator;
use Phil\BrailleExport\Contraction;
use Phil\BrailleExport\Exception\UnsupportedTranslation;
use Phil\BrailleExport\Translation\Braille;
use Phil\BrailleExportLiblouis\Exception\ExecutableNotFound;
use Phil\BrailleExportLiblouis\Exception\LiblouisException;
use Phil\BrailleExportLiblouis\Exception\LiblouisFailed;
use Phil\BrailleExportLiblouis\Exception\OutdatedLiblouis;
use Phil\BrailleExportLiblouis\Exception\TableNotFound;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\Process\Exception\ExceptionInterface;
use Symfony\Component\Process\Exception\ProcessTimedOutException;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;

/**
 * The translator of `phil/braille-export` that runs `lou_translate`, the translation program of liblouis: contracted
 * and uncontracted braille of every language liblouis has tables for.
 *
 * A whole batch goes to one run of the program, one text per line of its standard input, and comes back one line per
 * text on its standard output, written as Unicode braille by the `unicode.dis` display table. The program is started
 * with its arguments as a list, never through a shell; its time and the size of what goes in and out are bounded.
 *
 * Before its first translation, the translator asks the program its version, once, and keeps the answer. A liblouis
 * older than {@see self::MINIMUM_VERSION}, which fixes memory-safety defects, is refused with an
 * {@see OutdatedLiblouis}, and an answer without a version with a {@see LiblouisFailed}: the texts come from documents,
 * so the program reads input nobody checked. `$allowOutdatedLiblouis` turns both refusals into a warning in the log.
 */
final class LiblouisTranslator implements Translator
{
    /** The oldest liblouis the translator runs by default. */
    public const string MINIMUM_VERSION = '3.39.0';

    /** The most bytes the program may write when it gives its version. */
    private const int MAX_VERSION_OUTPUT = 4_096;

    /** The longest line sent to the program, in bytes; a longer text is sent in pieces cut between words. */
    private const int MAX_LINE = 1_000;

    private const string NAME = '/^[A-Za-z0-9][A-Za-z0-9._-]{0,127}$/D';

    private readonly LiblouisTables $tables;

    private readonly LoggerInterface $logger;

    /** What the program answered when asked its version, kept once asked. */
    private ?string $versionOutput = null;

    /** Whether the version was checked and accepted, or let through with a warning. */
    private bool $checked = false;

    /**
     * @param LiblouisTables|null  $tables                the table of each language and contraction; {@see LiblouisTables::defaults()} without it
     * @param string               $binary                the program, a name found on the `PATH` or a path
     * @param float                $timeout               the seconds one batch, or the question of the version, may take
     * @param int                  $maxInputBytes         the most bytes of text one batch may send
     * @param int                  $maxOutputBytes        the most bytes the program may write for one batch
     * @param string               $displayTable          the display table, which must write Unicode braille
     * @param string|null          $tablePath             the directories liblouis looks for tables in, as `LOUIS_TABLEPATH` gives them;
     *                                                    its own directory without it
     * @param bool                 $allowOutdatedLiblouis whether a liblouis older than {@see self::MINIMUM_VERSION}, or one whose version
     *                                                    cannot be read, translates anyway, with a warning in the log, instead of being refused
     * @param LoggerInterface|null $logger                where that warning goes
     */
    public function __construct(
        ?LiblouisTables $tables = null,
        private readonly string $binary = 'lou_translate',
        private readonly float $timeout = 30.0,
        private readonly int $maxInputBytes = 4_194_304,
        private readonly int $maxOutputBytes = 33_554_432,
        private readonly string $displayTable = 'unicode.dis',
        private readonly ?string $tablePath = null,
        private readonly bool $allowOutdatedLiblouis = false,
        ?LoggerInterface $logger = null,
    ) {
        if ($timeout <= 0.0) {
            throw new InvalidArgumentException('The timeout of liblouis must be positive.');
        }

        if ($maxInputBytes < 1 || $maxOutputBytes < 1) {
            throw new InvalidArgumentException('The limits of liblouis must be positive.');
        }

        if (1 !== preg_match(self::NAME, $displayTable)) {
            throw new InvalidArgumentException(\sprintf('"%s" is not the name of a liblouis table.', $displayTable));
        }

        $this->tables = $tables ?? LiblouisTables::defaults();
        $this->logger = $logger ?? new NullLogger();
    }

    public function supports(string $language, Contraction $contraction): bool
    {
        return null !== $this->tables->table($language, $contraction);
    }

    public function translate(array $texts, string $language, Contraction $contraction): array
    {
        $table = $this->tables->table($language, $contraction) ?? throw UnsupportedTranslation::of($language, $contraction);
        $lines = [];
        $pieces = [];

        foreach ($texts as $index => $text) {
            $pieces[$index] = [];

            foreach ($this->pieces($text) as $piece) {
                $pieces[$index][] = \count($lines);
                $lines[] = $piece;
            }
        }

        if ([] === $lines) {
            return array_map(static fn (): string => '', $texts);
        }

        $input = implode("\n", $lines)."\n";

        if (\strlen($input) > $this->maxInputBytes) {
            throw LiblouisFailed::inputTooLarge($this->maxInputBytes);
        }

        $this->checkVersion();

        $process = new Process(
            [$this->executable(), '--forward', $this->displayTable.','.$table],
            env: $this->environment(),
            input: $input,
            timeout: $this->timeout,
        );
        $braille = $this->braille($this->run($process, $table), \count($lines));

        return array_values(array_map(static fn (array $indexes): string => implode(Braille::BLANK, array_map(static fn (int $line): string => $braille[$line], $indexes)), $pieces));
    }

    /**
     * Whether the program is found, so that a chain of translators can leave this one out on a machine without it.
     */
    public function isAvailable(): bool
    {
        try {
            $this->executable();

            return true;
        } catch (ExecutableNotFound) {
            return false;
        }
    }

    /**
     * The version of liblouis the program reports, such as `3.39.0`; asked once, then kept.
     *
     * @throws LiblouisException
     */
    public function version(): string
    {
        return $this->reportedVersion() ?? throw LiblouisFailed::noVersion((string) $this->versionOutput);
    }

    /**
     * The version the program reports, null when its answer names none; asked once, then kept.
     *
     * @throws LiblouisException
     */
    private function reportedVersion(): ?string
    {
        $this->versionOutput ??= $this->run(
            new Process([$this->executable(), '--version'], env: $this->environment(), timeout: $this->timeout),
            '',
            self::MAX_VERSION_OUTPUT,
        );

        return 1 === preg_match('/\d+\.\d+(?:\.\d+)?/', $this->versionOutput, $match) ? $match[0] : null;
    }

    /**
     * Refuses a liblouis older than {@see self::MINIMUM_VERSION}, or one whose answer names no version, unless
     * `$allowOutdatedLiblouis` lets it through with a warning; once let through, never asked again.
     *
     * @throws LiblouisException
     */
    private function checkVersion(): void
    {
        if ($this->checked) {
            return;
        }

        $version = $this->reportedVersion();

        if (null === $version) {
            if (!$this->allowOutdatedLiblouis) {
                throw LiblouisFailed::noVersion((string) $this->versionOutput);
            }

            $this->logger->warning('liblouis did not say which version it is; it translates anyway, as allowOutdatedLiblouis lets it.', ['minimum' => self::MINIMUM_VERSION]);
        } elseif (version_compare($version, self::MINIMUM_VERSION, '<')) {
            if (!$this->allowOutdatedLiblouis) {
                throw OutdatedLiblouis::of($version, self::MINIMUM_VERSION);
            }

            $this->logger->warning('liblouis {version} is older than {minimum}; it translates anyway, as allowOutdatedLiblouis lets it.', ['version' => $version, 'minimum' => self::MINIMUM_VERSION]);
        }

        $this->checked = true;
    }

    /**
     * The lines of one text: control characters made spaces, the ends trimmed, pieces of at most {@see self::MAX_LINE}
     * bytes cut at a space, else between two characters.
     *
     * @return list<string>
     */
    private function pieces(string $text): array
    {
        $text = trim((string) preg_replace('/[\x00-\x1F\x7F]+/', ' ', mb_scrub($text, 'UTF-8')));
        $pieces = [];

        while (\strlen($text) > self::MAX_LINE) {
            $space = strrpos(substr($text, 0, self::MAX_LINE + 1), ' ');

            if (false === $space || 0 === $space) {
                $piece = mb_strcut($text, 0, self::MAX_LINE, 'UTF-8');
                $pieces[] = $piece;
                $text = substr($text, \strlen($piece));
            } else {
                $pieces[] = substr($text, 0, $space);
                $text = substr($text, $space + 1);
            }
        }

        if ('' !== $text) {
            $pieces[] = $text;
        }

        return $pieces;
    }

    private function executable(): string
    {
        if (str_contains($this->binary, \DIRECTORY_SEPARATOR)) {
            if (is_file($this->binary) && is_executable($this->binary)) {
                return $this->binary;
            }

            throw ExecutableNotFound::named($this->binary);
        }

        return new ExecutableFinder()->find($this->binary) ?? throw ExecutableNotFound::named($this->binary);
    }

    /**
     * @return array<string, string>
     */
    private function environment(): array
    {
        return null === $this->tablePath ? [] : ['LOUIS_TABLEPATH' => $this->tablePath];
    }

    private function run(Process $process, string $table, ?int $maxOutputBytes = null): string
    {
        $limit = $maxOutputBytes ?? $this->maxOutputBytes;
        $written = 0;

        try {
            $process->start();
            $over = $process->waitUntil(static function (string $type, string $data) use (&$written, $limit): bool {
                $written += \strlen($data);

                return $written > $limit;
            });

            if ($over) {
                $process->stop(0);

                throw LiblouisFailed::outputTooLarge($limit);
            }

            $process->wait();
        } catch (ProcessTimedOutException $exception) {
            throw LiblouisFailed::timedOut($this->binary, $process->getErrorOutput(), $exception);
        } catch (ExceptionInterface $exception) {
            throw LiblouisFailed::notRun($this->binary, $exception);
        }

        if (!$process->isSuccessful()) {
            $exitCode = (int) $process->getExitCode();
            $errorOutput = $process->getErrorOutput();

            if (1 === preg_match('/cannot (?:resolve|find|open) table/i', $errorOutput)) {
                throw TableNotFound::named($table, $exitCode, $errorOutput);
            }

            throw LiblouisFailed::exited($exitCode, $errorOutput);
        }

        return $process->getOutput();
    }

    /**
     * @return list<string>
     */
    private function braille(string $output, int $count): array
    {
        $lines = explode("\n", rtrim(str_replace("\r", '', $output), "\n"));

        if (\count($lines) !== $count) {
            throw LiblouisFailed::lineCount($count, \count($lines));
        }

        foreach ($lines as $index => $line) {
            $lines[$index] = str_replace(' ', Braille::BLANK, $line);

            if (!Braille::isBraille($lines[$index])) {
                throw LiblouisFailed::notBraille($index + 1);
            }
        }

        return $lines;
    }
}
