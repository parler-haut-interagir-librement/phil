<?php

declare(strict_types=1);

namespace Phil\SafeHttp\Tests\Unit;

use Exception;
use Phil\SafeHttp\Exception\FetchFailed;
use Phil\SafeHttp\Exception\RefusedAddress;
use Phil\SafeHttp\Exception\TooManyRedirects;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Throwable;

#[CoversClass(FetchFailed::class)]
#[CoversClass(RefusedAddress::class)]
#[CoversClass(TooManyRedirects::class)]
final class ExceptionsTest extends TestCase
{
    /**
     * An address a member wrote is cut to 100 characters in the message, whole characters, and the code stays 0.
     */
    public function testTheAddressIsCutToWholeCharactersInEachMessage(): void
    {
        $address = str_repeat('é', 150);
        $cut = str_repeat('é', 100);
        $previous = new Exception('why');

        $cases = [
            [FetchFailed::transport($address, $previous), '"'.$cut.'" could not be read: why'],
            [FetchFailed::outOfTime($address), '"'.$cut.'" took longer than the whole duration allowed.'],
            [RefusedAddress::notPublic($address, $previous), '"'.$cut.'" is not an http or https address of a host name.'],
            [RefusedAddress::redirect($address, $previous), 'The redirect to "'.$cut.'" is not an http or https address of a host name.'],
            [RefusedAddress::privateNetwork($address, $previous), '"'.$cut.'" is or resolves to a private, loopback, link-local or reserved network.'],
            [TooManyRedirects::after(3, $address), '"'.$cut.'" redirects more than 3 times.'],
        ];

        foreach ($cases as [$exception, $message]) {
            self::assertInstanceOf(Throwable::class, $exception);
            self::assertSame($message, $exception->getMessage());
            self::assertSame(0, $exception->getCode());
        }
    }

    public function testThePreviousExceptionIsKept(): void
    {
        $previous = new Exception('why');

        self::assertSame($previous, FetchFailed::transport('https://example.com/', $previous)->getPrevious());
        self::assertSame($previous, RefusedAddress::notPublic('x', $previous)->getPrevious());
        self::assertSame($previous, RefusedAddress::redirect('x', $previous)->getPrevious());
        self::assertSame($previous, RefusedAddress::privateNetwork('x', $previous)->getPrevious());
        self::assertNull(RefusedAddress::notPublic('x')->getPrevious());
    }
}
