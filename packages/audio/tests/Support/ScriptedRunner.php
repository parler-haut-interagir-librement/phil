<?php

declare(strict_types=1);

namespace Phil\Audio\Tests\Support;

use Closure;
use Override;
use Phil\Audio\CommandResult;
use Phil\Audio\Contract\CommandRunner;

/**
 * A runner that starts nothing: it records each command and answers with the result its script gives, the script free
 * to write the files ffmpeg would have written.
 */
final class ScriptedRunner implements CommandRunner
{
    /** @var list<array{command: list<string>, timeout: float, maxOutputBytes: int}> */
    public array $runs = [];

    /**
     * @param Closure(list<string>): CommandResult $script
     */
    public function __construct(
        private readonly Closure $script,
    ) {
    }

    /**
     * A script that answers ffprobe with this report and ffmpeg by writing these bytes to the file it names last.
     */
    public static function ffmpeg(string $report, string $written = 'audio'): self
    {
        return new self(static function (array $command) use ($report, $written): CommandResult {
            if (str_contains($command[0], 'ffprobe')) {
                return new CommandResult(0, $report);
            }

            file_put_contents(substr((string) array_last($command), \strlen('file:')), $written);

            return new CommandResult(0);
        });
    }

    #[Override]
    public function run(array $command, float $timeout, int $maxOutputBytes): CommandResult
    {
        $this->runs[] = ['command' => $command, 'timeout' => $timeout, 'maxOutputBytes' => $maxOutputBytes];

        return ($this->script)($command);
    }

    /**
     * @return list<string>
     */
    public function command(int $index): array
    {
        return $this->runs[$index]['command'];
    }
}
