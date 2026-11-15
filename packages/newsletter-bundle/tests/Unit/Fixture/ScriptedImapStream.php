<?php

declare(strict_types=1);

namespace Phil\NewsletterBundle\Tests\Unit\Fixture;

/**
 * A stream wrapper that plays an IMAP server from a script, hands the bytes over a few at a time, never past the end
 * of a line, and keeps what the client writes: `imapscript://name`.
 */
final class ScriptedImapStream
{
    /** @var array<string, string> */
    public static array $scripts = [];

    /** @var array<string, string> */
    public static array $written = [];

    public static int $chunk = 2;

    /** @var resource|null */
    public $context;

    private string $name = '';

    private int $position = 0;

    public static function register(): void
    {
        if (!\in_array('imapscript', stream_get_wrappers(), true)) {
            stream_wrapper_register('imapscript', self::class);
        }
    }

    /**
     * @return resource
     */
    public static function open(string $name, string $script): mixed
    {
        self::register();
        self::$scripts[$name] = $script;
        self::$written[$name] = '';
        $stream = fopen('imapscript://'.$name, 'r+');
        \assert(\is_resource($stream));

        return $stream;
    }

    public function stream_open(string $path): bool
    {
        $this->name = substr($path, \strlen('imapscript://'));

        return isset(self::$scripts[$this->name]);
    }

    public function stream_read(int $count): string
    {
        $chunk = substr(self::$scripts[$this->name], $this->position, min($count, self::$chunk));
        $break = strpos($chunk, "\n");

        // A chunk ends with its line, so that nothing is read ahead of what the client has asked for: PHP drops what
        // it has read ahead when the client writes, as it never does with a socket.
        if (false !== $break) {
            $chunk = substr($chunk, 0, $break + 1);
        }

        $this->position += \strlen($chunk);

        return $chunk;
    }

    public function stream_write(string $data): int
    {
        self::$written[$this->name] .= $data;

        return \strlen($data);
    }

    public function stream_eof(): bool
    {
        return $this->position >= \strlen(self::$scripts[$this->name]);
    }

    /**
     * @return array<string, int>
     */
    public function stream_stat(): array
    {
        return [];
    }

    public function stream_set_option(): bool
    {
        return false;
    }
}
