<?php

declare(strict_types=1);

namespace Phil\UploadScan\Tests\Unit;

use Phil\UploadScan\Clamd\SocketConnector;
use Phil\UploadScan\Exception\InvalidArgument;
use Phil\UploadScan\Exception\ScannerUnavailable;
use Phil\UploadScan\Internal\Io;
use Phil\UploadScan\Tests\Unit\Fixture\Files;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(SocketConnector::class)]
#[CoversClass(ScannerUnavailable::class)]
#[CoversClass(Io::class)]
final class SocketConnectorTest extends TestCase
{
    public function testItConnectsToAUnixSocket(): void
    {
        $path = Files::socketPath();
        $server = stream_socket_server('unix://'.$path);
        self::assertIsResource($server);

        try {
            $connector = new SocketConnector('unix://'.$path);
            $socket = $connector->connect();

            self::assertSame('unix://'.$path, $connector->address);
            self::assertSame(5.0, $connector->connectTimeoutSeconds);
            self::assertIsResource($socket);
            self::assertFalse(stream_get_meta_data($socket)['eof']);
        } finally {
            fclose($server);
            unlink($path);
        }
    }

    public function testItConnectsToATcpPort(): void
    {
        $server = stream_socket_server('tcp://127.0.0.1:0');
        self::assertIsResource($server);
        $name = (string) stream_socket_get_name($server, false);

        try {
            self::assertIsResource(new SocketConnector('tcp://'.$name, 0.5)->connect());
        } finally {
            fclose($server);
        }
    }

    public function testTheWarningOfARefusedConnectionIsSilenced(): void
    {
        error_clear_last();

        try {
            new SocketConnector('unix:///no/such/clamd.sock', 0.5)->connect();
            self::fail('An unreachable clamd was reached.');
        } catch (ScannerUnavailable) {
            self::assertNull(error_get_last());
        }
    }

    public function testAnUnreachableClamdIsUnavailable(): void
    {
        $this->expectException(ScannerUnavailable::class);
        $this->expectExceptionMessageMatches('#^clamd cannot be reached at unix:///no/such/clamd\.sock \(.+\)\.$#');
        $this->expectOutputString('');

        new SocketConnector('unix:///no/such/clamd.sock', 0.5)->connect();
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function refusedAddresses(): iterable
    {
        yield 'no scheme' => ['/run/clamav/clamd.ctl'];
        yield 'another scheme' => ['udp://clamav:3310'];
        yield 'an empty path' => ['unix://'];
        yield 'a path with a space' => ['unix:///run/clam av.ctl'];
        yield 'no port' => ['tcp://clamav'];
        yield 'a path after the port' => ['tcp://clamav:3310/x'];
        yield 'a port of six digits' => ['tcp://clamav:331000'];
        yield 'a line after it' => ["tcp://clamav:3310\n"];
        yield 'something before it' => [' tcp://clamav:3310'];
    }

    #[DataProvider('refusedAddresses')]
    public function testAnAddressIsAUnixPathOrAHostAndPort(string $address): void
    {
        $this->expectException(InvalidArgument::class);
        $this->expectExceptionMessageIsOrContains('A clamd address is unix://<path> or tcp://<host>:<port>; "'.addcslashes($address, "\x00..\x1F\x7F").'" is not.');

        new SocketConnector($address);
    }

    public function testTheTimeoutIsPositive(): void
    {
        self::assertSame(0.001, new SocketConnector('tcp://clamav:3310', 0.001)->connectTimeoutSeconds);

        $this->expectException(InvalidArgument::class);
        $this->expectExceptionMessageIsOrContains('The connect timeout of clamd is above 0 seconds.');

        new SocketConnector('tcp://clamav:3310', 0.0);
    }
}
