<?php

declare(strict_types=1);

namespace Phil\UploadScan\Tests\Unit\Fixture;

use LogicException;
use Override;
use Phil\UploadScan\Contract\ClamdConnector;

/**
 * A clamd that answers what the test wrote for it, through a stream wrapper of its own: it records every byte the
 * scanner sends, can refuse writes after a number of bytes, and can accept a write in parts.
 */
final class ScriptedClamd implements ClamdConnector
{
    /** The bytes sent on every connection, in order. */
    public string $sent = '';

    public int $connections = 0;

    /**
     * @param string   $answer   the bytes clamd answers on every connection
     * @param int|null $accepted how many bytes a connection accepts before its writes fail; no limit by default
     * @param bool     $stutter  whether a write accepts 3 bytes at most, and the call after a cut one nothing
     * @param int|null $piece    the most bytes a read answers; all it is asked for by default
     */
    public function __construct(
        public readonly string $answer = "stream: OK\0",
        public readonly ?int $accepted = null,
        public readonly bool $stutter = false,
        public readonly ?int $piece = null,
    ) {
        if (!\in_array(ScriptedStream::SCHEME, stream_get_wrappers(), true)) {
            stream_wrapper_register(ScriptedStream::SCHEME, ScriptedStream::class);
        }
    }

    #[Override]
    public function connect(): mixed
    {
        ++$this->connections;
        $stream = fopen(ScriptedStream::SCHEME.'://clamd', 'r+', false, stream_context_create([ScriptedStream::SCHEME => ['clamd' => $this]]));

        if (!\is_resource($stream)) {
            throw new LogicException('The scripted stream did not open.');
        }

        return $stream;
    }
}
