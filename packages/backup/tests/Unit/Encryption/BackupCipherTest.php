<?php

declare(strict_types=1);

namespace Phil\Backup\Tests\Unit\Encryption;

use Phil\Backup\Encryption\BackupCipher;
use Phil\Backup\Encryption\BackupKey;
use Phil\Backup\Exception\BackupFailed;
use Phil\Backup\Exception\InvalidBackupValue;
use Phil\Backup\Tests\Unit\Fixture\Files;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * Encryption by chunks: every size round trips, and every alteration of the file is refused.
 */
#[CoversClass(BackupCipher::class)]
#[CoversClass(BackupKey::class)]
#[CoversClass(InvalidBackupValue::class)]
final class BackupCipherTest extends TestCase
{
    private string $directory;

    private BackupCipher $cipher;

    protected function setUp(): void
    {
        $this->directory = Files::directory();
        $this->cipher = new BackupCipher(BackupKey::fromBase64(str_repeat('A', 43).'='));
    }

    protected function tearDown(): void
    {
        Files::remove($this->directory);
    }

    public function testEverySizeRoundTripsWithinTheSameLayout(): void
    {
        $sealed = '';

        foreach ([0 => 1, 1 => 1, 65535 => 1, 65536 => 1, 65537 => 2, 3 * 65536 + 10 => 4] as $size => $chunks) {
            $clear = 0 === $size ? '' : random_bytes($size);
            file_put_contents($this->directory.'/clear', $clear);
            $this->cipher->encrypt($this->directory.'/clear', $this->directory.'/sealed');
            $this->cipher->decrypt($this->directory.'/sealed', $this->directory.'/opened');

            $sealed = (string) file_get_contents($this->directory.'/sealed');
            self::assertSame($clear, file_get_contents($this->directory.'/opened'), (string) $size);
            self::assertSame("PHILBAK\x01", substr($sealed, 0, 8));
            self::assertSame(8 + 24 + $chunks * (4 + 17) + $size, \strlen($sealed), (string) $size);
            self::assertSame(0o600, fileperms($this->directory.'/sealed') & 0o777);
            self::assertSame(0o600, fileperms($this->directory.'/opened') & 0o777);
        }

        self::assertSame(pack('N', 65536 + 17), substr($sealed, 32, 4), 'Each chunk starts with its length, big-endian.');
        self::assertNotSame(substr($sealed, 36, 32), substr((string) file_get_contents($this->directory.'/opened'), 0, 32));
    }

    public function testAnotherKeyOrAnAlteredFileIsRefused(): void
    {
        file_put_contents($this->directory.'/clear', random_bytes(65536 + 100));
        $this->cipher->encrypt($this->directory.'/clear', $this->directory.'/sealed');
        $sealed = (string) file_get_contents($this->directory.'/sealed');
        $second = 36 + 65536 + 17;

        $cases = [
            'another key' => [$sealed, new BackupCipher(BackupKey::generate())],
            'a flipped byte' => [substr_replace($sealed, \chr(\ord($sealed[100]) ^ 1), 100, 1), $this->cipher],
            'a flipped header byte' => [substr_replace($sealed, \chr(\ord($sealed[10]) ^ 1), 10, 1), $this->cipher],
            'a cut header' => [substr($sealed, 0, 20), $this->cipher],
            'no chunk' => [substr($sealed, 0, 32), $this->cipher],
            'a cut length' => [substr($sealed, 0, 34), $this->cipher],
            'a cut chunk' => [substr($sealed, 0, 1000), $this->cipher],
            'the final chunk dropped' => [substr($sealed, 0, $second), $this->cipher],
            'a length too large' => [substr_replace($sealed, pack('N', 65536 + 18), 32, 4), $this->cipher],
            'a negative-looking length' => [substr_replace($sealed, "\xFF\xFF\xFF\xFF", 32, 4), $this->cipher],
            'trailing bytes' => [$sealed.'x', $this->cipher],
            'the chunks swapped' => [substr($sealed, 0, 32).substr($sealed, $second).substr($sealed, 32, $second - 32), $this->cipher],
        ];

        foreach ($cases as $case => [$content, $cipher]) {
            file_put_contents($this->directory.'/altered', $content);

            try {
                $cipher->decrypt($this->directory.'/altered', $this->directory.'/opened');
                self::fail($case);
            } catch (BackupFailed $backupFailed) {
                self::assertSame($this->directory.'/altered cannot be decrypted: the key is not the one it was encrypted with, or the file is damaged.', $backupFailed->getMessage(), $case);
            }
        }
    }

    public function testAFileThatIsNotAnEncryptedBackupIsNamed(): void
    {
        foreach (['SQLite format 3' => 'is not an encrypted backup.', "PHILBAK\x02".str_repeat("\0", 40) => 'is encrypted in a format this version does not read.', '' => 'is not an encrypted backup.'] as $content => $message) {
            file_put_contents($this->directory.'/file', $content);

            try {
                $this->cipher->decrypt($this->directory.'/file', $this->directory.'/opened');
                self::fail($message);
            } catch (BackupFailed $backupFailed) {
                self::assertSame($this->directory.'/file '.$message, $backupFailed->getMessage());
            }
        }
    }

    public function testFilesThatCannotBeOpenedAreNamed(): void
    {
        file_put_contents($this->directory.'/clear', 'x');

        mkdir($this->directory.'/locked', 0o500);
        $strayBefore = glob(sys_get_temp_dir().'/.phil-backup-*');

        // A directory the tests created belongs to the user they run as.
        $cases = 0 === fileowner($this->directory) ? [] : [[$this->directory.'/clear', $this->directory.'/locked/sealed', 'The file '.$this->directory.'/locked/sealed cannot be written.']];

        foreach ([
            [$this->directory.'/missing', $this->directory.'/sealed', 'The file '.$this->directory.'/missing cannot be read.'],
            [$this->directory.'/clear', $this->directory.'/clear/sealed', 'The directory '.$this->directory.'/clear cannot be created.'],
            ...$cases,
        ] as [$source, $target, $message]) {
            foreach (['encrypt' => $this->cipher->encrypt(...), 'decrypt' => $this->cipher->decrypt(...)] as $method => $call) {
                try {
                    $call($source, $target);
                    self::fail($method.' '.$message);
                } catch (BackupFailed $backupFailed) {
                    self::assertSame($message, $backupFailed->getMessage());
                }
            }
        }

        self::assertSame([], Files::entries($this->directory.'/locked'), 'Nothing is left in a directory that cannot be written.');
        self::assertSame($strayBefore, glob(sys_get_temp_dir().'/.phil-backup-*'), 'The file the system gives elsewhere is removed.');
        self::assertSame(['clear', 'locked'], Files::entries($this->directory), 'No temporary file is left behind.');
    }

    public function testATargetIsReplacedWholeAndAFailureLeavesItAsItWas(): void
    {
        file_put_contents($this->directory.'/clear', 'first');
        file_put_contents($this->directory.'/sealed', 'an older file');
        chmod($this->directory.'/sealed', 0o644);
        $this->cipher->encrypt($this->directory.'/clear', $this->directory.'/sealed');
        self::assertSame(0o600, fileperms($this->directory.'/sealed') & 0o777, 'The target takes the mode of the temporary file.');

        file_put_contents($this->directory.'/opened', 'kept');
        $sealed = (string) file_get_contents($this->directory.'/sealed');
        file_put_contents($this->directory.'/altered', substr($sealed, 0, -1));

        try {
            $this->cipher->decrypt($this->directory.'/altered', $this->directory.'/opened');
            self::fail('A damaged file is refused.');
        } catch (BackupFailed) {
            self::assertSame('kept', file_get_contents($this->directory.'/opened'), 'A file that fails never reaches the target.');
        }

        $this->cipher->decrypt($this->directory.'/sealed', $this->directory.'/opened');
        self::assertSame('first', file_get_contents($this->directory.'/opened'));
        self::assertSame(['altered', 'clear', 'opened', 'sealed'], Files::entries($this->directory), 'No temporary file is left behind.');
    }

    public function testTheTargetDirectoryIsCreatedOrNarrowedToItsOwner(): void
    {
        file_put_contents($this->directory.'/clear', 'x');
        $this->cipher->encrypt($this->directory.'/clear', $this->directory.'/fresh/backup/sealed');
        self::assertSame(0o700, fileperms($this->directory.'/fresh/backup') & 0o777, 'A directory it creates is private.');

        mkdir($this->directory.'/open', 0o700);
        chmod($this->directory.'/open', 0o755);
        $this->cipher->decrypt($this->directory.'/fresh/backup/sealed', $this->directory.'/open/opened');
        clearstatcache();
        self::assertSame(0o700, fileperms($this->directory.'/open') & 0o777, 'An open directory is narrowed.');
        self::assertSame('x', file_get_contents($this->directory.'/open/opened'));
    }

    public function testADirectoryOpenToOneOtherUserBitAloneIsNarrowed(): void
    {
        file_put_contents($this->directory.'/clear', 'x');
        mkdir($this->directory.'/ajar', 0o700);
        chmod($this->directory.'/ajar', 0o701);

        $this->cipher->encrypt($this->directory.'/clear', $this->directory.'/ajar/sealed');

        clearstatcache();
        self::assertSame(0o700, fileperms($this->directory.'/ajar') & 0o777);
    }

    public function testAnOpenDirectoryIsRefusedWhenItIsNotToBeNarrowed(): void
    {
        file_put_contents($this->directory.'/clear', 'x');
        $cipher = new BackupCipher(BackupKey::fromBase64(str_repeat('A', 43).'='), tightenOpenDirectory: false);
        $cipher->encrypt($this->directory.'/clear', $this->directory.'/private/sealed');
        self::assertFileExists($this->directory.'/private/sealed', 'A private directory is used as it is.');
        chmod($this->directory.'/private', 0o750);

        foreach (['encrypt' => [$cipher->encrypt(...), $this->directory.'/clear'], 'decrypt' => [$cipher->decrypt(...), $this->directory.'/private/sealed']] as $method => [$call, $source]) {
            try {
                $call($source, $this->directory.'/private/again');
                self::fail($method);
            } catch (BackupFailed $backupFailed) {
                self::assertSame('The directory '.$this->directory.'/private is open to other users (mode 0750): narrow it to its owner, with mode 0700.', $backupFailed->getMessage());
            }
        }

        clearstatcache();
        self::assertSame(0o750, fileperms($this->directory.'/private') & 0o777, 'A refused directory keeps its mode.');
        self::assertSame(['sealed'], Files::entries($this->directory.'/private'), 'Nothing is written in a refused directory.');
    }

    public function testAnOpenDirectoryThatCannotBeNarrowedIsRefused(): void
    {
        if (0 === fileowner($this->directory)) {
            self::markTestSkipped('root narrows every directory.');
        }

        file_put_contents($this->directory.'/clear', 'x');

        // The system's shared temporary directory belongs to root and is open to everyone.
        $this->expectException(BackupFailed::class);
        $this->expectExceptionMessageIsOrContains('The directory /tmp is open to other users (mode 0777) and cannot be narrowed to its owner: give it to the user that writes the backups, with mode 0700.');
        $this->cipher->encrypt($this->directory.'/clear', '/tmp/phil-backup-'.bin2hex(random_bytes(6)));
    }

    public function testADirectoryReachedThroughASymbolicLinkReceivesTheTarget(): void
    {
        file_put_contents($this->directory.'/clear', 'x');
        mkdir($this->directory.'/real', 0o700);
        symlink($this->directory.'/real', $this->directory.'/link');

        $this->cipher->encrypt($this->directory.'/clear', $this->directory.'/link/sealed');

        self::assertSame(['sealed'], Files::entries($this->directory.'/real'));
    }

    public function testAKeyIsThirtyTwoBytesInBase64(): void
    {
        $key = BackupKey::generate();
        self::assertSame(32, \strlen($key->bytes));
        self::assertNotSame($key->bytes, BackupKey::generate()->bytes);
        self::assertSame($key->bytes, BackupKey::fromBase64(" \n".$key->toBase64()."\n")->bytes);
        self::assertSame(base64_encode($key->bytes), $key->toBase64());

        foreach (['', 'not base64!', base64_encode(random_bytes(31)), base64_encode(random_bytes(33)), rtrim(base64_encode(random_bytes(32)), '=').'-'] as $encoded) {
            try {
                BackupKey::fromBase64($encoded);
                self::fail($encoded);
            } catch (InvalidBackupValue $invalidBackupValue) {
                self::assertSame('A backup key is 32 random bytes in base64, such as the output of base64_encode(random_bytes(32)).', $invalidBackupValue->getMessage());
            }
        }
    }
}
