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
use Phil\BrailleExportLiblouis\Exception\TableNotFound;
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
 */
final readonly class LiblouisTranslator implements Translator
{
    private const float VERSION_TIMEOUT = 10.0;

    /** The longest line sent to the program, in bytes; a longer text is sent in pieces cut between words. */
    private const int MAX_LINE = 1_000;

    private const string NAME = '/^[A-Za-z0-9][A-Za-z0-9._-]{0,127}$/D';

    private LiblouisTables $tables;

    /**
     * @param LiblouisTables|null $tables         the table of each language and contraction; {@see LiblouisTables::defaults()} without it
     * @param string              $binary         the program, a name found on the `PATH` or a path
     * @param float               $timeout        the seconds one batch may take
     * @param int                 $maxInputBytes  the most bytes of text one batch may send
     * @param int                 $maxOutputBytes the most bytes the program may write for one batch
     * @param string              $displayTable   the display table, which must write Unicode braille
     * @param string|null         $tablePath      the directories liblouis looks for tables in, as `LOUIS_TABLEPATH` gives them;
     *                                            its own directory without it
     */
    public function __construct(
        ?LiblouisTables $tables = null,
        private string $binary = 'lou_translate',
        private float $timeout = 30.0,
        private int $maxInputBytes = 4_194_304,
        private int $maxOutputBytes = 33_554_432,
        private string $displayTable = 'unicode.dis',
        private ?string $tablePath = null,
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

        $process = new Process(
            [$this->executable(), '--forward', $this->displayTable.','.$table],
            env: null === $this->tablePath ? [] : ['LOUIS_TABLEPATH' => $this->tablePath],
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
     * The version of liblouis the program reports, such as `3.21.0`.
     *
     * @throws LiblouisException
     */
    public function version(): string
    {
        $output = $this->run(new Process([$this->executable(), '--version'], timeout: self::VERSION_TIMEOUT), '');

        if (1 !== preg_match('/\d+\.\d+(?:\.\d+)?/', $output, $match)) {
            throw LiblouisFailed::noVersion($output);
        }

        return $match[0];
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

    private function run(Process $process, string $table): string
    {
        $written = 0;

        try {
            $process->start();
            $over = $process->waitUntil(function (string $type, string $data) use (&$written): bool {
                $written += \strlen($data);

                return $written > $this->maxOutputBytes;
            });

            if ($over) {
                $process->stop(0);

                throw LiblouisFailed::outputTooLarge($this->maxOutputBytes);
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
