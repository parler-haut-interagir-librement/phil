<?php

declare(strict_types=1);

namespace Phil\SitesConsoleBundle\Tests\Unit\Infrastructure;

use Phil\SitesConsoleBundle\Infrastructure\Composer\LockFilePackages;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(LockFilePackages::class)]
final class LockFilePackagesTest extends TestCase
{
    private const string LOCK = '{"packages":[{"name":"symfony/http-kernel","version":"v8.2.0"},{"name":"phil/url","version":"0.1.4"},{"name":"Bad/Name","version":"1"},{"name":"bad/version","version":"1 0"},{"name":"no/version"},"x",{"name":1,"version":"1"}],"packages-dev":[{"name":"phpunit/phpunit","version":"13.4.6"}]}';

    private string $path = '';

    protected function setUp(): void
    {
        $this->path = sys_get_temp_dir().'/phil-sites-console-'.bin2hex(random_bytes(6)).'.lock';
    }

    protected function tearDown(): void
    {
        if (is_file($this->path)) {
            unlink($this->path);
        }
    }

    public function testTheProductionPackagesSortedAndTheDevelopmentOnesWhenAsked(): void
    {
        file_put_contents($this->path, self::LOCK);

        self::assertSame(['phil/url' => '0.1.4', 'symfony/http-kernel' => 'v8.2.0'], new LockFilePackages($this->path)->versions());
        self::assertSame(['phil/url' => '0.1.4', 'phpunit/phpunit' => '13.4.6', 'symfony/http-kernel' => 'v8.2.0'], new LockFilePackages($this->path, true)->versions());
    }

    public function testAMissingOrMalformedFileGivesNothing(): void
    {
        self::assertSame([], new LockFilePackages($this->path)->versions());
        self::assertSame([], LockFilePackages::read('{'));
        self::assertSame([], LockFilePackages::read('"x"'));
        self::assertSame([], LockFilePackages::read('{"packages":"x"}'));
        self::assertSame([], LockFilePackages::read('{}', true));
    }

    public function testAFileTooLargeGivesNothing(): void
    {
        file_put_contents($this->path, str_pad(self::LOCK, LockFilePackages::MAX_BYTES + 1));
        self::assertSame([], new LockFilePackages($this->path)->versions());
    }

    public function testAFileAtTheLimitIsRead(): void
    {
        file_put_contents($this->path, str_pad(self::LOCK, LockFilePackages::MAX_BYTES));
        self::assertSame(['phil/url' => '0.1.4', 'symfony/http-kernel' => 'v8.2.0'], new LockFilePackages($this->path)->versions());
    }

    public function testTheDevelopmentPackagesAreLeftOutByDefault(): void
    {
        self::assertSame(['phil/url' => '0.1.4', 'symfony/http-kernel' => 'v8.2.0'], LockFilePackages::read(self::LOCK));
    }

    public function testADirectoryIsNotReadAsAFile(): void
    {
        $errors = [];
        set_error_handler(static function (int $level, string $message) use (&$errors): bool {
            $errors[] = $message;

            return true;
        });

        try {
            self::assertSame([], new LockFilePackages(sys_get_temp_dir())->versions());
        } finally {
            restore_error_handler();
        }

        self::assertSame([], $errors);
    }

    public function testTheDepthOfTheDocumentIsLimitedToSixtyFourLevels(): void
    {
        $packages = '"packages":[{"name":"phil/url","version":"0.1.4"}]';

        self::assertSame(['phil/url' => '0.1.4'], LockFilePackages::read('{'.$packages.',"x":'.str_repeat('[', 62).str_repeat(']', 62).'}'));
        self::assertSame([], LockFilePackages::read('{'.$packages.',"x":'.str_repeat('[', 63).str_repeat(']', 63).'}'));
    }
}
