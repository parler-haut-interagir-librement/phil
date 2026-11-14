<?php

declare(strict_types=1);

namespace Phil\UploadScanBundle\Application;

use Phil\UploadScan\Contract\Scanner;
use Phil\UploadScan\Decision;
use Phil\UploadScan\ScanPolicy;
use Phil\UploadScan\ScanResult;
use Phil\UploadScanBundle\Application\Contract\Quarantine;
use Phil\UploadScanBundle\Application\Event\FileFlagged;
use Phil\UploadScanBundle\Application\Event\FileInfected;
use Phil\UploadScanBundle\Application\Exception\QuarantineFailed;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;

/**
 * A file inspected: scanned, decided by the policy, an infected file quarantined and `FileInfected` dispatched, a file
 * accepted unscanned flagged and `FileFlagged` dispatched. Keeps nothing between two files.
 */
final readonly class UploadScanner
{
    public function __construct(
        private Scanner $scanner,
        private ScanPolicy $policy = new ScanPolicy(),
        private ?Quarantine $quarantine = null,
        private ?EventDispatcherInterface $events = null,
        private ?LoggerInterface $logger = null,
    ) {
    }

    /**
     * @param string $name    the name the sender gave the file
     * @param string $context where the file comes from, given to the events
     */
    public function inspectPath(string $path, string $name, string $context = 'upload'): Inspection
    {
        $stream = is_file($path) ? fopen($path, 'r') : false;

        if (false === $stream) {
            return $this->conclude($this->scanner->scanPath($path), null, $name, $context);
        }

        try {
            return $this->inspectStream($stream, $name, $context);
        } finally {
            fclose($stream);
        }
    }

    public function inspectContents(string $contents, string $name, string $context = 'upload'): Inspection
    {
        $stream = fopen('php://temp', 'w+');
        \assert(false !== $stream);
        // The scanner reads the stream from its start, and the quarantine rewinds it again.
        fwrite($stream, $contents);

        try {
            return $this->inspectStream($stream, $name, $context);
        } finally {
            fclose($stream);
        }
    }

    /**
     * Inspects the whole stream: the scanner rewinds a stream read before, to sniff its type or hash it, and refuses
     * one that cannot be rewound once read. An infected file is quarantined only from a stream that can be read
     * again from its start.
     *
     * @param resource $stream
     */
    public function inspectStream(mixed $stream, string $name, string $context = 'upload'): Inspection
    {
        return $this->conclude($this->scanner->scanStream($stream), $stream, $name, $context);
    }

    /**
     * @param resource|null $source the stream of the file, to quarantine it from
     */
    private function conclude(ScanResult $result, mixed $source, string $name, string $context): Inspection
    {
        $decision = $this->policy->decide($result);

        if (null !== $result->signature) {
            $quarantineId = $this->quarantine($source, $name, $result);
            $this->events?->dispatch(new FileInfected($name, $result->signature, $context, $quarantineId));

            return new Inspection($result, $decision, $quarantineId);
        }

        if (Decision::Flag === $decision) {
            $this->events?->dispatch(new FileFlagged($name, (string) $result->reason, $context));
        }

        return new Inspection($result, $decision);
    }

    /**
     * @param resource|null $source
     */
    private function quarantine(mixed $source, string $name, ScanResult $result): ?string
    {
        if (!$this->quarantine instanceof Quarantine || !\is_resource($source) || !stream_get_meta_data($source)['seekable'] || !rewind($source)) {
            return null;
        }

        try {
            return $this->quarantine->isolate($source, $name, $result);
        } catch (QuarantineFailed $quarantineFailed) {
            $this->logger?->error('An infected file could not be quarantined: {reason}', ['reason' => $quarantineFailed->getMessage(), 'signature' => $result->signature]);

            return null;
        }
    }
}
