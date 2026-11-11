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

        foreach ([
            [$this->directory.'/missing', $this->directory.'/sealed', 'The file '.$this->directory.'/missing cannot be read.'],
            [$this->directory.'/clear', $this->directory.'/no/sealed', 'The file '.$this->directory.'/no/sealed cannot be written.'],
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
