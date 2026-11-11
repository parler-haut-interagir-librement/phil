<?php

declare(strict_types=1);

namespace Phil\UploadScan\Clamd;

use Override;
use Phil\UploadScan\Contract\ClamdConnector;
use Phil\UploadScan\Exception\InvalidArgument;
use Phil\UploadScan\Exception\ScannerUnavailable;
use Phil\UploadScan\Internal\Io;

/**
 * A connection to clamd on its Unix socket, `unix:///run/clamav/clamd.ctl`, or on its TCP port,
 * `tcp://clamav:3310`, given up after the connect timeout.
 */
final readonly class SocketConnector implements ClamdConnector
{
    /**
     * @throws InvalidArgument when the address is not a unix:// or tcp:// address, or the timeout not positive
     */
    public function __construct(
        public string $address,
        public float $connectTimeoutSeconds = 5.0,
    ) {
        if (1 !== preg_match('#^(?:unix://[^\x00-\x20]+|tcp://[^\x00-\x20/]+:\d{1,5})$#D', $address)) {
            throw InvalidArgument::because(\sprintf('A clamd address is unix://<path> or tcp://<host>:<port>; "%s" is not.', addcslashes($address, "\x00..\x1F\x7F")));
        }

        if ($connectTimeoutSeconds <= 0.0) {
            throw InvalidArgument::because('The connect timeout of clamd is above 0 seconds.');
        }
    }

    #[Override]
    public function connect(): mixed
    {
        $code = null;
        $message = '';
        $socket = Io::quietly(function () use (&$code, &$message): mixed {
            return stream_socket_client($this->address, $code, $message, $this->connectTimeoutSeconds);
        });

        if (!\is_resource($socket)) {
            throw ScannerUnavailable::because(\sprintf('clamd cannot be reached at %s (%s).', $this->address, $message));
        }

        return $socket;
    }
}
