<?php

declare(strict_types=1);

namespace Phil\ErrorTracking\Tests\Benchmark;

use DateTimeImmutable;
use Phil\ErrorTracking\ErrorEvent;
use Phil\ErrorTracking\ExceptionRecord;
use Phil\ErrorTracking\Fingerprinter;
use Phil\ErrorTracking\Frame;
use Phil\ErrorTracking\MessageNormalizer;
use Phil\ErrorTracking\Severity;
use Phil\ErrorTracking\Source;

/**
 * Every captured error is fingerprinted: a chain of two exceptions of forty frames each, half of them the
 * application's, with a message full of values to normalize.
 */
final readonly class FingerprintBench
{
    private ErrorEvent $event;

    private Fingerprinter $fingerprinter;

    private MessageNormalizer $normalizer;

    public function __construct()
    {
        $frames = [];

        for ($index = 0; $index < 40; ++$index) {
            $frames[] = 0 === $index % 2
                ? new Frame('src/Service/Step'.$index.'.php', $index, 'App\Service\Step'.$index.'::run', true)
                : new Frame('vendor/framework/kernel/Kernel'.$index.'.php', $index, 'Vendor\\Framework\\Kernel::handle');
        }

        $this->event = new ErrorEvent(str_repeat('a', 32), new DateTimeImmutable(), Source::Http, Severity::Error, [
            new ExceptionRecord('RuntimeException', 'Order 3f2c1a9e-0b1d-4c2e-9f3a-1b2c3d4e5f60 of user 42 failed at https://api.example.com/v2/items?page=3', $frames),
            new ExceptionRecord('PDOException', "SQLSTATE[23000]: Duplicate entry 'jane@example.com' for key 'email'", $frames),
        ]);
        $this->fingerprinter = new Fingerprinter();
        $this->normalizer = new MessageNormalizer();
    }

    public function benchFingerprint(): void
    {
        $this->fingerprinter->fingerprint($this->event);
    }

    public function benchNormalizeAMessage(): void
    {
        $this->normalizer->normalize("SQLSTATE[23000]: Duplicate entry 'jane@example.com' for key 'email' at row 1234 of 0xdeadbeef");
    }
}
