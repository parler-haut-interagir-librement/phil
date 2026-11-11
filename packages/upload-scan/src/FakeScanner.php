<?php

declare(strict_types=1);

namespace Phil\UploadScan;

use Override;
use Phil\UploadScan\Clamd\ClamdScanner;
use Phil\UploadScan\Contract\Scanner;
use Phil\UploadScan\Contract\ScannerProbe;
use Phil\UploadScan\Exception\InvalidArgument;
use Phil\UploadScan\Internal\Io;

/**
 * A scanner for tests, without an engine: a file that holds the EICAR test string anywhere is infected with
 * `Eicar-Test-Signature`, as is a file that holds one of the needles given, with its signature; any other file is clean.
 * Given an error, it answers that error for every file; unreachable, its probe says so.
 */
final readonly class FakeScanner implements Scanner, ScannerProbe
{
    public const string EICAR_SIGNATURE = 'Eicar-Test-Signature';

    /**
     * @param array<array-key, string> $signatures the signature of each needle a file may hold, a numeric needle given as an integer key
     * @param string|null              $error      the reason of the error answered for every file; none by default
     * @param int                      $maxBytes   the largest file read; a larger one is an error result
     * @param bool                     $reachable  what the probe answers
     *
     * @throws InvalidArgument when a needle is empty or the limit is below 1 byte
     */
    public function __construct(
        private array $signatures = [],
        private ?string $error = null,
        private int $maxBytes = ClamdScanner::DEFAULT_MAX_BYTES,
        private bool $reachable = true,
    ) {
        if (\array_key_exists('', $signatures)) {
            throw InvalidArgument::because('A needle of the fake scanner is never empty: it would be found in every file.');
        }

        if ($maxBytes < 1) {
            throw InvalidArgument::because('A scanner reads at least 1 byte.');
        }
    }

    /**
     * The EICAR test string, which every antivirus reports as a test signature: written in parts, so that this source
     * file is not reported itself.
     */
    public static function eicar(): string
    {
        return implode('', ['X5O!P%@AP[4\PZX54(P^)7CC)7}$', 'EICAR-STANDARD-ANTIVIRUS-', 'TEST-FILE!$H+H*']);
    }

    #[Override]
    public function scanStream(mixed $stream): ScanResult
    {
        $stream = Io::stream($stream);

        if (!Io::atStart($stream)) {
            return ScanResult::error(Io::READ_BEFORE);
        }

        if (null !== $this->error) {
            return ScanResult::error($this->error);
        }

        $contents = '';

        while (\strlen($contents) <= $this->maxBytes) {
            $chunk = Io::quietly(static fn (): string|false => fread($stream, ClamdScanner::DEFAULT_CHUNK_BYTES));

            if (false === $chunk) {
                return ScanResult::error('The file cannot be read.');
            }

            if ('' === $chunk) {
                break;
            }

            $contents .= $chunk;
        }

        if (\strlen($contents) > $this->maxBytes) {
            return ScanResult::error(\sprintf('The file is larger than the %d bytes the scanner reads.', $this->maxBytes));
        }

        if (str_contains($contents, self::eicar())) {
            return ScanResult::infected(self::EICAR_SIGNATURE);
        }

        foreach ($this->signatures as $needle => $signature) {
            if (str_contains($contents, (string) $needle)) {
                return ScanResult::infected($signature);
            }
        }

        return ScanResult::clean();
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
        return $this->reachable;
    }

    #[Override]
    public function version(): ?string
    {
        return $this->reachable ? 'FakeScanner' : null;
    }
}
