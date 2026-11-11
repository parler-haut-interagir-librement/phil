<?php

declare(strict_types=1);

namespace Phil\UploadScan\Tests\Benchmark;

use Phil\UploadScan\Clamd\ClamdResponse;
use Phil\UploadScan\Clamd\ClamdScanner;
use Phil\UploadScan\FakeScanner;
use Phil\UploadScan\Tests\Unit\Fixture\Files;
use Phil\UploadScan\Tests\Unit\Fixture\ScriptedClamd;

/**
 * Every upload goes through a scanner: the framing of a 1 MiB file into chunks, its answer read, and the fake scanner of
 * the tests over the same file. The engine's own time is not measured: a scripted clamd answers at once.
 */
final readonly class ClamdScannerBench
{
    private ClamdScanner $scanner;

    private FakeScanner $fake;

    private string $file;

    public function __construct()
    {
        $this->scanner = new ClamdScanner(new ScriptedClamd());
        $this->fake = new FakeScanner();
        $this->file = str_repeat('0123456789abcdef', 65_536);
    }

    public function benchClamdScanOfOneMebibyte(): void
    {
        $this->scanner->scanStream(Files::stream($this->file));
    }

    public function benchFakeScanOfOneMebibyte(): void
    {
        $this->fake->scanStream(Files::stream($this->file));
    }

    public function benchAnswerRead(): void
    {
        ClamdResponse::scan("stream: Win.Test.EICAR_HDB-1 FOUND\0");
    }
}
