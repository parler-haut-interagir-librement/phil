<?php

declare(strict_types=1);

namespace Phil\SafeHttp\Tests\Unit;

use Exception;
use Phil\SafeHttp\Exception\FetchFailed;
use Phil\SafeHttp\Exception\InvalidRequest;
use Phil\SafeHttp\Exception\RefusedAddress;
use Phil\SafeHttp\Exception\RefusedRedirect;
use Phil\SafeHttp\Exception\ShownAddress;
use Phil\SafeHttp\Exception\TooManyRedirects;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Throwable;

#[CoversClass(FetchFailed::class)]
#[CoversClass(RefusedAddress::class)]
#[CoversClass(TooManyRedirects::class)]
#[CoversClass(InvalidRequest::class)]
#[CoversClass(RefusedRedirect::class)]
#[CoversClass(ShownAddress::class)]
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
            [FetchFailed::headersTooLarge($address, 1024, 10), '"'.$cut.'" sent headers past 1024 bytes or 10 headers.'],
            [RefusedAddress::notPublic($address, $previous), '"'.$cut.'" is not an http or https address of a host name.'],
            [RefusedAddress::redirect($address, $previous), 'The redirect to "'.$cut.'" is not an http or https address of a host name.'],
            [RefusedAddress::downgrade($address, $address), 'The redirect from "'.$cut.'" to "'.$cut.'" leaves https for http: its headers, body and answer would travel in clear text.'],
            [RefusedAddress::privateNetwork($address, $previous), '"'.$cut.'" is or resolves to a private, loopback, link-local or reserved network.'],
            [TooManyRedirects::after(3, $address), '"'.$cut.'" redirects more than 3 times.'],
            [RefusedRedirect::forRequest('POST', $address, 303), '"'.$cut.'" answered POST with the redirect 303, which a request with a body or a method other than GET, HEAD or OPTIONS does not follow.'],
            [InvalidRequest::method(str_repeat('é', 40)), '"'.str_repeat('é', 30).'" is not a method the client sends.'],
            [InvalidRequest::headerName(str_repeat('é', 70)), '"'.str_repeat('é', 60).'" is not a header name.'],
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

        self::assertSame($previous, RefusedAddress::notPublic('x', $previous)->getPrevious());
        self::assertSame($previous, RefusedAddress::redirect('x', $previous)->getPrevious());
        self::assertNull(RefusedAddress::notPublic('x')->getPrevious());
    }

    public function testATransportErrorIsNotKeptAsItHoldsTheWholeAddress(): void
    {
        $previous = new Exception('Host "intranet.example" is blocked for "https://intranet.example/?token=t0k3n".');

        self::assertNull(FetchFailed::transport('https://example.com/', $previous)->getPrevious());
        self::assertNull(RefusedAddress::privateNetwork('https://intranet.example/?token=t0k3n', $previous)->getPrevious());
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function addresses(): iterable
    {
        yield 'a plain address' => ['https://example.com/feed.ics', 'https://example.com/feed.ics'];
        yield 'a query' => ['https://example.com/feed.ics?token=t0k3n&x=1', 'https://example.com/feed.ics?…'];
        yield 'a fragment' => ['https://example.com/cb#access_token=t0k3n', 'https://example.com/cb?…'];
        yield 'user information' => ['https://user:pa55@example.com/x', 'https://…@example.com/x'];
        yield 'an at sign in the path stays' => ['https://example.com/@ada/posts', 'https://example.com/@ada/posts'];
        yield 'a line break' => ["https://example.com/a\r\nb", 'https://example.com/ab'];
        yield 'not an address' => ['x', 'x'];
    }

    #[DataProvider('addresses')]
    public function testAnAddressIsShownWithoutItsSecrets(string $address, string $shown): void
    {
        self::assertSame($shown, ShownAddress::of($address));
        self::assertSame('"'.$shown.'" redirects more than 3 times.', TooManyRedirects::after(3, $address)->getMessage());
    }

    public function testTheAddressesOfATransportErrorAreShownWithoutTheirSecrets(): void
    {
        $failed = FetchFailed::transport('https://u:p@example.com/feed?key=k3y', new Exception("Timeout was reached for \"https://u:p@example.com/feed?key=k3y\".\nThen https://cdn.example/x#sig=s1g failed"));

        self::assertSame('"https://…@example.com/feed?…" could not be read: Timeout was reached for "https://…@example.com/feed?…". Then https://cdn.example/x?… failed', $failed->getMessage());
    }
}
