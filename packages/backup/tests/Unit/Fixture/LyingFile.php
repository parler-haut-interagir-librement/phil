<?php

declare(strict_types=1);

namespace Phil\Backup\Tests\Unit\Fixture;

/**
 * A stream whose size, as `fstat()` gives it, is not the length of its content: `lying://<size>/<content>`. It
 * stands for a file that changes while it is archived, or one too large to create in a test.
 */
final class LyingFile
{
    public const string SCHEME = 'lying';

    /** @var resource|null */
    public $context;

    private int $size = 0;

    private string $content = '';

    public static function register(): void
    {
        if (!\in_array(self::SCHEME, stream_get_wrappers(), true)) {
            stream_wrapper_register(self::SCHEME, self::class);
        }
    }

    public function stream_open(string $path): bool
    {
        [$size, $content] = explode('/', substr($path, \strlen(self::SCHEME) + 3), 2);
        $this->size = (int) $size;
        $this->content = $content;

        return true;
    }

    public function stream_read(int $count): string
    {
        $read = substr($this->content, 0, $count);
        $this->content = substr($this->content, \strlen($read));

        return $read;
    }

    public function stream_eof(): bool
    {
        return '' === $this->content;
    }

    /**
     * @return array{size: int}
     */
    public function stream_stat(): array
    {
        return ['size' => $this->size];
    }
}
