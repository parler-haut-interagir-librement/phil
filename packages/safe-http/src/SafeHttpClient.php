<?php

declare(strict_types=1);

namespace Phil\SafeHttp;

use Phil\SafeHttp\Exception\FetchFailed;
use Phil\SafeHttp\Exception\RefusedAddress;
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
 * - the redirects are followed here, one by one, each checked, up to the limits' count;
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
        return $this->send('GET', $url, $rule);
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
        return $this->send('HEAD', $url, new ReadRule());
    }

    public function reset(): void
    {
        $this->client->reset();
    }

    private function send(string $method, Url|string $address, ReadRule $rule): SafeResponse
    {
        $url = $address instanceof Url ? $address : AddressGuard::address($address);
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
                    'headers' => ['Accept' => $rule->accept, 'User-Agent' => $this->limits->userAgent],
                    // The headers as they arrive, before the transport has them all: thrown here, the transfer stops.
                    'on_progress' => static function (int $downloaded, int $size, array $info) use ($limits, $address): void {
                        self::checkHeaders($info['response_headers'] ?? [], $limits, $address);
                    },
                ]);
                $status = $response->getStatusCode();
                self::checkHeaders($response->getInfo('response_headers'), $this->limits, $address);
                $headers = $response->getHeaders(false);
                $location = $headers['location'][0] ?? null;

                if (null === $location || !\in_array($status, self::REDIRECTS, true)) {
                    return $this->read($method, $url, $response, $status, $headers, $rule, $redirects);
                }

                $response->cancel();
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
