<?php

declare(strict_types=1);

namespace Phil\SafeHttp;

use Phil\SafeHttp\Exception\FetchFailed;
use Phil\SafeHttp\Exception\InvalidRequest;
use Phil\SafeHttp\Exception\RefusedAddress;
use Phil\SafeHttp\Exception\RefusedRedirect;
use Phil\SafeHttp\Exception\TooManyRedirects;
use Phil\Url\Url;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\HttpClient\NoPrivateNetworkHttpClient;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;
use Symfony\Contracts\Service\ResetInterface;

/**
 * Reads addresses that come from members, editors or other sites, guarded against server-side request forgery:
 *
 * - every address, the first and each redirect, passes `AddressGuard` — an HTTP or HTTPS address of a host name;
 * - the client is wrapped in Symfony's `NoPrivateNetworkHttpClient`, which refuses a host that is or resolves to a
 *   loopback, private, link-local or reserved address, a cloud metadata endpoint among them, and pins the address it
 *   resolved for the connection;
 * - no proxy is used, not even one of the environment's `http_proxy` or `https_proxy`, which would resolve the host
 *   again past the address checked;
 * - the redirects are followed here, one by one, each checked, up to the limits' count; a request with a body, or of
 *   a method other than `GET`, `HEAD` or `OPTIONS`, follows none: a redirect answered to it throws `RefusedRedirect`;
 * - the headers a request gives go to the scheme, host and port of its first address only: a redirect elsewhere
 *   carries none of them, an `Authorization` or a `Cookie` among them; no message of the client names their values;
 * - the silence of the host, and the whole duration of the read and its redirects, are bounded;
 * - the headers of an answer are counted as the transport reports them, by its progress, and once more when they are
 *   all in: past the limits' bytes or count of headers, the transfer is aborted;
 * - the body is read in chunks, only for the media types the rule reads, up to the limits' bytes or the rule's stop
 *   mark, and the transfer is then cancelled; a chunk is only kept up to the bytes that remain, so the body held never
 *   exceeds the limits' bytes.
 *
 * The client keeps nothing of a read: the addresses its guard resolved are forgotten by `reset()`, which a worker calls
 * between messages.
 */
final readonly class SafeHttpClient implements ResetInterface
{
    private const array REDIRECTS = [301, 302, 303, 307, 308];

    /** The methods that follow a redirect, when they carry no body: they change nothing, and send nothing twice. */
    private const array FOLLOWING = ['GET', 'HEAD', 'OPTIONS'];

    /** Headers the client, or the transport, sets: `Accept` by the rule, `User-Agent` by the limits, the rest by HTTP. */
    private const array RESERVED = ['accept', 'user-agent', 'host', 'content-length', 'transfer-encoding', 'connection', 'keep-alive', 'upgrade', 'te', 'trailer', 'expect'];

    private NoPrivateNetworkHttpClient $client;

    /**
     * @param array<string, string> $resolve fixed addresses for host names, as curl's `--resolve`; still checked
     *                                       against the private networks
     */
    public function __construct(
        ?HttpClientInterface $client = null,
        private Limits $limits = new Limits(),
        private array $resolve = [],
    ) {
        $this->client = new NoPrivateNetworkHttpClient($client ?? HttpClient::create());
    }

    /**
     * @throws RefusedAddress   when an address, or a redirect, is not public
     * @throws TooManyRedirects when the redirects outnumber the limits
     * @throws FetchFailed      when the host does not answer within the limits, or its headers exceed them
     */
    public function get(Url|string $url, ReadRule $rule = new ReadRule()): SafeResponse
    {
        return $this->request('GET', $url, $rule, [], '');
    }

    /**
     * The status and headers of an address, after its redirects, without its body.
     *
     * @throws RefusedAddress   when an address, or a redirect, is not public
     * @throws TooManyRedirects when the redirects outnumber the limits
     * @throws FetchFailed      when the host does not answer within the limits, or its headers exceed them
     */
    public function head(Url|string $url): SafeResponse
    {
        return $this->request('HEAD', $url, new ReadRule(), [], '');
    }

    /**
     * A request of any method, with its headers and body, under the guards of `get()`: the address and each redirect
     * checked, the private networks refused, the time, the redirects and the bytes bounded. The method is written in
     * capitals, letters only, as Symfony HttpClient takes it; `CONNECT` and `TRACE` are refused. The headers are held
     * to the limits' bytes and count of headers, the body to their bytes. Only a request of `GET`, `HEAD` or `OPTIONS`
     * without a body follows redirects, and the headers given here never leave the scheme, host and port of the first
     * address.
     *
     * @param array<array-key, string> $headers by name, `Authorization` and `Content-Type` among them; `Accept` comes
     *                                          from the rule and `User-Agent` from the limits; a name of digits only,
     *                                          which PHP keeps as an integer key, is refused
     *
     * @throws InvalidRequest   when the method, a header or the body is refused, before any request
     * @throws RefusedAddress   when an address, or a redirect, is not public
     * @throws RefusedRedirect  when a request with a body, or of another method, is answered by a redirect
     * @throws TooManyRedirects when the redirects outnumber the limits
     * @throws FetchFailed      when the host does not answer within the limits, or its headers exceed them
     */
    public function send(string $method, Url|string $url, array $headers = [], string $body = '', ReadRule $rule = new ReadRule()): SafeResponse
    {
        $method = strtoupper($method);

        if (1 !== preg_match('/^[A-Z]{1,20}$/D', $method) || \in_array($method, ['CONNECT', 'TRACE'], true)) {
            throw InvalidRequest::method($method);
        }

        $headers = $this->checkRequestHeaders($headers, $this->limits);

        if (\strlen($body) > $this->limits->maxBytes) {
            throw InvalidRequest::bodyTooLarge(\strlen($body), $this->limits->maxBytes);
        }

        return $this->request($method, $url, $rule, $headers, $body);
    }

    public function reset(): void
    {
        $this->client->reset();
    }

    /**
     * @param array<string, string> $headers
     */
    private function request(string $method, Url|string $address, ReadRule $rule, array $headers, string $body): SafeResponse
    {
        $url = $address instanceof Url ? $address : AddressGuard::address($address);
        $origin = $url;
        $first = $url->toString();
        $deadline = hrtime(true) + $this->limits->maxDuration * 1e9;
        $redirects = [];

        while (true) {
            $remaining = ($deadline - hrtime(true)) / 1e9;

            if ($remaining <= 0) {
                throw FetchFailed::outOfTime($url->toString());
            }

            $address = $url->toString();
            $limits = $this->limits;

            try {
                $response = $this->client->request($method, $address, [
                    'timeout' => min($this->limits->timeout, $remaining),
                    'max_duration' => $remaining,
                    'max_redirects' => 0,
                    'buffer' => false,
                    'resolve' => $this->resolve,
                    // A proxy of the environment would resolve the host itself, past the address the guard checked.
                    'proxy' => null,
                    'no_proxy' => '*',
                    // The request's own headers stay on the origin of its first address.
                    'headers' => [...($this->sameOrigin($url, $origin) ? $headers : []), 'Accept' => $rule->accept, 'User-Agent' => $this->limits->userAgent],
                    'body' => $body,
                    // The headers as they arrive, before the transport has them all: thrown here, the transfer stops.
                    'on_progress' => static function (int $downloaded, int $size, array $info) use ($limits, $address): void {
                        self::checkHeaders($info['response_headers'] ?? [], $limits, $address);
                    },
                ]);
                $status = $response->getStatusCode();
                self::checkHeaders($response->getInfo('response_headers'), $this->limits, $address);
                $answered = $response->getHeaders(false);
                $location = $answered['location'][0] ?? null;

                if (null === $location || !\in_array($status, self::REDIRECTS, true)) {
                    return $this->read($method, $url, $response, $status, $answered, $rule, $redirects);
                }

                $response->cancel();

                if ('' !== $body || !\in_array($method, self::FOLLOWING, true)) {
                    throw RefusedRedirect::forRequest($method, $address, $status);
                }
            } catch (ExceptionInterface $exception) {
                $previous = $exception->getPrevious();

                if ($previous instanceof FetchFailed) {
                    throw $previous;
                }

                throw 1 === preg_match('/^(?:Host|IP) ".*" is blocked for /', $exception->getMessage()) ? RefusedAddress::privateNetwork($url->toString(), $exception) : FetchFailed::transport($url->toString(), $exception);
            }

            if (\count($redirects) >= $this->limits->maxRedirects) {
                throw TooManyRedirects::after($this->limits->maxRedirects, $first);
            }

            $url = AddressGuard::redirect($url, $location);
            $redirects[] = $url;
        }
    }

    /**
     * @param array<string, list<string>> $headers
     * @param list<Url>                   $redirects
     *
     * @throws ExceptionInterface
     */
    private function read(string $method, Url $url, ResponseInterface $response, int $status, array $headers, ReadRule $rule, array $redirects): SafeResponse
    {
        if ('HEAD' === $method || !$rule->reads(SafeResponse::mediaTypeOf($headers['content-type'][0] ?? null))) {
            $response->cancel();

            return new SafeResponse($url, $status, $headers, '', false, $redirects);
        }

        $body = '';
        $complete = true;
        $mark = $rule->stopAfter;

        foreach ($this->client->stream($response) as $chunk) {
            $content = $chunk->getContent();
            $before = \strlen($body);
            $room = $this->limits->maxBytes - $before;

            // Only what remains of the bytes is kept of a chunk, before it joins the body: the body never holds more.
            if (\strlen($content) > $room) {
                $body .= substr($content, 0, $room);
                $response->cancel();
                $complete = false;

                break;
            }

            $body .= $content;

            if (null !== $mark && false !== stripos($body, $mark, max(0, $before - \strlen($mark) + 1))) {
                $response->cancel();
                $complete = false;

                break;
            }
        }

        return new SafeResponse($url, $status, $headers, $body, $complete, $redirects);
    }

    private function sameOrigin(Url $url, Url $origin): bool
    {
        return $url->scheme === $origin->scheme && $url->host === $origin->host && $url->port === $origin->port;
    }

    /**
     * The headers a request gives: names that are tokens and not reserved, values on one line, the whole within the
     * limits' bytes and count of headers, each line counted as `Name: value`.
     *
     * @param array<array-key, string> $headers what the caller passed: a name of digits only arrives as an integer key
     *
     * @return array<string, string> the same headers, their names known to be strings
     *
     * @throws InvalidRequest when a header is refused
     */
    private function checkRequestHeaders(array $headers, Limits $limits): array
    {
        $bytes = 0;
        $checked = [];

        foreach ($headers as $name => $value) {
            // A name of digits only is an integer key in a PHP array, which Symfony HttpClient would read as a whole
            // header line given as the value: it is refused.
            if (\is_int($name) || 1 !== preg_match("/^[!#$%&'*+.^_`|~0-9A-Za-z-]{1,256}$/D", $name)) {
                throw InvalidRequest::headerName((string) $name);
            }

            $lower = strtolower($name);

            if (\in_array($lower, self::RESERVED, true) || str_starts_with($lower, 'proxy-')) {
                throw InvalidRequest::reservedHeader($name);
            }

            if (!Limits::isHeaderValue($value)) {
                throw InvalidRequest::headerValue($name);
            }

            $bytes += \strlen($name) + 2 + \strlen($value);
            $checked[$name] = $value;
        }

        if ($bytes > $limits->maxHeaderBytes || \count($headers) > $limits->maxHeaders) {
            throw InvalidRequest::headersTooLarge($limits->maxHeaderBytes, $limits->maxHeaders);
        }

        return $checked;
    }

    /**
     * The raw header lines of an answer, status lines included, against the limits: their bytes all counted, the status
     * lines left out of the count of headers.
     *
     * @throws FetchFailed when the headers exceed the limits
     */
    private static function checkHeaders(mixed $lines, Limits $limits, string $address): void
    {
        if (!\is_array($lines)) {
            return;
        }

        $bytes = 0;
        $count = 0;

        foreach ($lines as $line) {
            if (!\is_string($line)) {
                continue;
            }

            $bytes += \strlen($line);

            if (!str_starts_with($line, 'HTTP/')) {
                ++$count;
            }
        }

        if ($bytes > $limits->maxHeaderBytes || $count > $limits->maxHeaders) {
            throw FetchFailed::headersTooLarge($address, $limits->maxHeaderBytes, $limits->maxHeaders);
        }
    }
}
