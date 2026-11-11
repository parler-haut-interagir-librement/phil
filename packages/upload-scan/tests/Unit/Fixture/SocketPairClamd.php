<?php

declare(strict_types=1);

namespace Phil\UploadScan\Tests\Unit\Fixture;

use LogicException;
use Override;
use Phil\UploadScan\Contract\ClamdConnector;

/**
 * A clamd at the other end of a real socket pair that answers nothing: the scanner's reads wait for their timeout.
 */
final class SocketPairClamd implements ClamdConnector
{
    /** @var list<resource> the ends kept open, so that the connection is not closed */
    public array $peers = [];

    #[Override]
    public function connect(): mixed
    {
        $pair = stream_socket_pair(\STREAM_PF_UNIX, \STREAM_SOCK_STREAM, \STREAM_IPPROTO_IP);

        if (false === $pair) {
            throw new LogicException('No socket pair.');
        }

        $this->peers[] = $pair[1];

        return $pair[0];
    }
}
