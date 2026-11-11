<?php

declare(strict_types=1);

namespace Phil\UploadScan\Clamd;

use Override;
use Phil\UploadScan\Contract\ClamdConnector;
use Phil\UploadScan\Contract\Scanner;
use Phil\UploadScan\Contract\ScannerProbe;
use Phil\UploadScan\Exception\InvalidArgument;
use Phil\UploadScan\Exception\ScannerUnavailable;
use Phil\UploadScan\Internal\Io;
use Phil\UploadScan\ScanResult;

/**
 * An antivirus scan by clamd, the daemon of ClamAV, over the protocol it documents: `zINSTREAM`, then the file in
 * chunks, each preceded by its length in four bytes in network order, then a chunk of length zero; clamd answers once
 * it has scanned the whole. The file never touches clamd's disk, so clamd may run in another container or host.
 *
 * Each read and write on the connection waits at most the timeout. A file larger than the size limit is not sent
 * whole: the scan stops and answers an error result. Keep the limit at or below clamd's `StreamMaxLength`, which
 * otherwise answers its own error.
 */
final readonly class ClamdScanner implements Scanner, ScannerProbe
{
    /** @var int<1, max> */
    private int $chunkBytes;

    /** 25 MiB, the default `StreamMaxLength` of clamd. */
    public const int DEFAULT_MAX_BYTES = 26_214_400;

    public const int DEFAULT_CHUNK_BYTES = 65_536;

    /**
     * @param float $timeoutSeconds how long each read or write on the connection may wait
     * @param int   $maxBytes       the largest file sent to clamd
     * @param int   $chunkBytes     the size of each chunk sent
     *
     * @throws InvalidArgument when the timeout is not positive, or a size below 1 byte
     */
    public function __construct(
        private ClamdConnector $connector,
        private float $timeoutSeconds = 30.0,
        private int $maxBytes = self::DEFAULT_MAX_BYTES,
        int $chunkBytes = self::DEFAULT_CHUNK_BYTES,
    ) {
        if ($timeoutSeconds <= 0.0) {
            throw InvalidArgument::because('The timeout of clamd is above 0 seconds.');
        }

        if ($maxBytes < 1 || $chunkBytes < 1) {
            throw InvalidArgument::because('The size limit and the chunks of clamd are at least 1 byte.');
        }

        $this->chunkBytes = $chunkBytes;
    }

    /**
     * A scanner of clamd at a `unix://` or `tcp://` address.
     *
     * @throws InvalidArgument when the address or a limit is refused
     */
    public static function at(string $address, float $timeoutSeconds = 30.0, int $maxBytes = self::DEFAULT_MAX_BYTES, int $chunkBytes = self::DEFAULT_CHUNK_BYTES): self
    {
        return new self(new SocketConnector($address, min($timeoutSeconds, 5.0)), $timeoutSeconds, $maxBytes, $chunkBytes);
    }

    #[Override]
    public function scanStream(mixed $stream): ScanResult
    {
        $stream = Io::stream($stream);

        if (!Io::atStart($stream)) {
            return ScanResult::error(Io::READ_BEFORE);
        }

        try {
            $socket = $this->connector->connect();
        } catch (ScannerUnavailable $scannerUnavailable) {
            return ScanResult::error(substr($scannerUnavailable->getMessage(), 0, ScanResult::MAX_TEXT_BYTES));
        }

        try {
            return $this->send($stream, $socket);
        } finally {
            fclose($socket);
        }
    }

    #[Override]
    public function scanPath(string $path): ScanResult
    {
        $stream = Io::open($path);

        if (null === $stream) {
            return ScanResult::error('The file cannot be read.');
        }

        try {
            return $this->scanStream($stream);
        } finally {
            fclose($stream);
        }
    }

    #[Override]
    public function ping(): bool
    {
        return ClamdResponse::isPong($this->command('PING') ?? '');
    }

    #[Override]
    public function version(): ?string
    {
        $answer = $this->command('VERSION');

        return null === $answer ? null : ClamdResponse::version($answer);
    }

    /**
     * @param resource $stream
     * @param resource $socket
     */
    private function send(mixed $stream, mixed $socket): ScanResult
    {
        $this->limit($socket);

        if (!$this->write($socket, "zINSTREAM\0")) {
            return $this->answer($socket);
        }

        $sent = 0;

        while (true) {
            $chunk = Io::quietly(fn (): string|false => fread($stream, $this->chunkBytes));

            if (false === $chunk) {
                return ScanResult::error('The file cannot be read.');
            }

            if ('' === $chunk) {
                break;
            }

            $sent += \strlen($chunk);

            if ($sent > $this->maxBytes) {
                return ScanResult::error(\sprintf('The file is larger than the %d bytes the scanner reads.', $this->maxBytes));
            }

            if (!$this->write($socket, pack('N', \strlen($chunk)).$chunk)) {
                return $this->answer($socket);
            }
        }

        $this->write($socket, pack('N', 0));

        return $this->answer($socket);
    }

    /**
     * What clamd answered, read up to its null byte; it may answer early, such as when a file exceeds its own limit,
     * and close the connection, so a write that failed is followed by this read too.
     *
     * @param resource $socket
     */
    private function answer(mixed $socket): ScanResult
    {
        $answer = $this->read($socket);

        return null === $answer
            ? ScanResult::error(\sprintf('clamd did not answer within %s seconds.', $this->timeoutSeconds))
            : ClamdResponse::scan($answer);
    }

    /**
     * One command without a stream, its answer, or `null` when clamd does not answer it.
     */
    private function command(string $command): ?string
    {
        try {
            $socket = $this->connector->connect();
        } catch (ScannerUnavailable) {
            return null;
        }

        try {
            $this->limit($socket);

            return $this->write($socket, 'z'.$command."\0") ? $this->read($socket) : null;
        } finally {
            fclose($socket);
        }
    }

    /**
     * @param resource $socket
     */
    private function limit(mixed $socket): void
    {
        $seconds = (int) $this->timeoutSeconds;
        stream_set_timeout($socket, $seconds, (int) (($this->timeoutSeconds - $seconds) * 1_000_000));
    }

    /**
     * @param resource $socket
     */
    private function write(mixed $socket, string $bytes): bool
    {
        while ('' !== $bytes) {
            $written = Io::quietly(static fn (): int|false => fwrite($socket, $bytes));

            if (false === $written || 0 === $written) {
                return false;
            }

            $bytes = substr($bytes, $written);
        }

        return true;
    }

    /**
     * The answer up to its null byte or the end of the connection, at most `ClamdResponse::MAX_BYTES`, or `null` when
     * the timeout passed first.
     *
     * @param resource $socket
     */
    private function read(mixed $socket): ?string
    {
        $answer = '';

        while (!str_contains($answer, "\0") && \strlen($answer) < ClamdResponse::MAX_BYTES) {
            $bytes = Io::quietly(static fn (): string|false => fread($socket, ClamdResponse::MAX_BYTES - \strlen($answer)));

            if (false === $bytes || '' === $bytes) {
                if (stream_get_meta_data($socket)['timed_out']) {
                    return null;
                }

                break;
            }

            $answer .= $bytes;
        }

        return $answer;
    }
}
