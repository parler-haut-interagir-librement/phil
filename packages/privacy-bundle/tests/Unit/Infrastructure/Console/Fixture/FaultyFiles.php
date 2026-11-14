<?php

declare(strict_types=1);

namespace Phil\PrivacyBundle\Tests\Unit\Infrastructure\Console\Fixture;

use RuntimeException;

/**
 * Files held in memory under `phil-faulty://`, one step of their writing made to fail: the opening, which throws,
 * the change of mode, the write, the truncation or the flush. It remembers the umask in force when a file is opened.
 */
final class FaultyFiles
{
    public const string SCHEME = 'phil-faulty';

    /** @var resource|null */
    public $context;

    /** The step that fails: `open`, `chmod`, `write`, `truncate`, `flush`, or none. */
    public static string $failing = '';

    public static ?int $umask = null;

    /** @var array<string, string> */
    public static array $files = [];

    private string $path = '';

    public static function register(string $failing = ''): void
    {
        self::unregister();
        stream_wrapper_register(self::SCHEME, self::class);
        self::$failing = $failing;
        self::$umask = null;
        self::$files = [];
    }

    public static function unregister(): void
    {
        if (\in_array(self::SCHEME, stream_get_wrappers(), true)) {
            stream_wrapper_unregister(self::SCHEME);
        }
    }

    public function stream_open(string $path, string $mode): bool
    {
        self::$umask = umask();

        if ('open' === self::$failing) {
            throw new RuntimeException('The disk is gone.');
        }

        if (str_starts_with($mode, 'x') && isset(self::$files[$path])) {
            return false;
        }

        $this->path = $path;
        self::$files[$path] ??= '';

        return true;
    }

    public function stream_metadata(): bool
    {
        return 'chmod' !== self::$failing;
    }

    public function stream_write(string $data): int
    {
        if ('write' === self::$failing) {
            return 0;
        }

        $held = self::$files[$this->path] ?? '';
        self::$files[$this->path] = $data.substr($held, \strlen($data));

        return \strlen($data);
    }

    public function stream_truncate(int $size): bool
    {
        if ('truncate' === self::$failing) {
            return false;
        }

        self::$files[$this->path] = substr(self::$files[$this->path] ?? '', 0, $size);

        return true;
    }

    public function stream_flush(): bool
    {
        return 'flush' !== self::$failing;
    }

    /**
     * @return array<int|string, int>|false
     */
    public function url_stat(string $path): array|false
    {
        return isset(self::$files[$path]) ? ['mode' => 0o100600] : false;
    }

    public function unlink(string $path): bool
    {
        unset(self::$files[$path]);

        return true;
    }
}
