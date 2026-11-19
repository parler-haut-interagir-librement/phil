<?php

declare(strict_types=1);

namespace Phil\UploadScan\Tests\Unit\Fixture;

use LogicException;
use Override;
use Phil\UploadScan\Contract\ClamdConnector;

/**
 * A clamd at the other end of a real socket pair that writes its bytes, then closes the connection: an answer cut
 * before its null byte, as when the connection drops after `stream: OK`.
 */
final readonly class ClosingClamd implements ClamdConnector
{
    public function __construct(
        public string $answer,
    ) {
    }

    #[Override]
    public function connect(): mixed
    {
        $pair = stream_socket_pair(\STREAM_PF_UNIX, \STREAM_SOCK_STREAM, \STREAM_IPPROTO_IP);

        if (false === $pair) {
            throw new LogicException('No socket pair.');
        }

        fwrite($pair[1], $this->answer);
        fclose($pair[1]);

        return $pair[0];
    }
}
