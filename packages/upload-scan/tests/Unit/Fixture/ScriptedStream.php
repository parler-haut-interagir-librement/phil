<?php

declare(strict_types=1);

namespace Phil\UploadScan\Tests\Unit\Fixture;

use LogicException;

/**
 * The stream wrapper behind `ScriptedClamd`: what a connection to it reads and writes.
 */
final class ScriptedStream
{
    public const string SCHEME = 'phil-upload-scan-test';

    /** @var resource|null */
    public $context;

    private ?ScriptedClamd $script = null;

    private int $read = 0;

    private int $written = 0;

    private bool $truncated = false;

    public function stream_open(string $path, string $mode, int $options, ?string &$openedPath): bool
    {
        $options = \is_resource($this->context) ? stream_context_get_options($this->context) : [];
        $clamd = \is_array($options[self::SCHEME] ?? null) ? ($options[self::SCHEME]['clamd'] ?? null) : null;
        $this->script = $clamd instanceof ScriptedClamd ? $clamd : throw new LogicException('A scripted stream is opened by ScriptedClamd.');

        return true;
    }

    public function stream_read(int $count): string
    {
        $bytes = substr($this->clamd()->answer, $this->read, min($count, $this->clamd()->piece ?? $count));
        $this->read += \strlen($bytes);

        return $bytes;
    }

    public function stream_write(string $data): int
    {
        if ($this->truncated) {
            $this->truncated = false;

            return 0;
        }

        if ($this->clamd()->stutter && \strlen($data) > 3) {
            $this->truncated = true;
            $data = substr($data, 0, 3);
        }

        $length = null === $this->clamd()->accepted ? \strlen($data) : max(0, min(\strlen($data), $this->clamd()->accepted - $this->written));
        $this->written += $length;
        $this->clamd()->sent .= substr($data, 0, $length);

        return $length;
    }

    public function stream_eof(): bool
    {
        return $this->read >= \strlen($this->clamd()->answer);
    }

    public function stream_set_option(): bool
    {
        return false;
    }

    private function clamd(): ScriptedClamd
    {
        return $this->script ?? throw new LogicException('The scripted stream is not open.');
    }
}
