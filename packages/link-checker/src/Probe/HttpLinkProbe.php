<?php

declare(strict_types=1);

namespace Phil\LinkChecker\Probe;

use Override;
use Phil\LinkChecker\Contract\HostThrottle;
use Phil\LinkChecker\Contract\LinkProbe;
use Phil\LinkChecker\LinkResult;
use Phil\LinkChecker\LinkStatus;
use Phil\LinkChecker\Throttle\IntervalThrottle;
use Phil\SafeHttp\Exception\FetchFailed;
use Phil\SafeHttp\Exception\RefusedAddress;
use Phil\SafeHttp\Exception\SafeHttpException;
use Phil\SafeHttp\Exception\TooManyRedirects;
use Phil\SafeHttp\Limits;
use Phil\SafeHttp\ReadRule;
use Phil\SafeHttp\SafeHttpClient;
use Phil\SafeHttp\SafeResponse;
use Phil\Url\Url;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\Service\ResetInterface;

/**
 * Asks an address itself, through phil/safe-http, which refuses the private networks and bounds the time, the bytes
 * and the redirects: `HEAD` first, then a `GET` that reads no body when the server refuses `HEAD` (405 or 501). Asked
 * for the HTML of a page, it sends a `GET` at once and reads the body, if it is HTML, up to the bytes of the limits.
 *
 * Before each request, the throttle paces the requests to the host. The `User-Agent` is the limits' own:
 * `PhilLinkChecker/0.1` unless the limits given say otherwise.
 *
 * The codes of the answers classify the link: a success is alive, or redirected — permanently when every redirect was
 * 301 or 308; 404 and 410 are dead; anything else is to review. A host name that does not resolve is dead; a timeout,
 * a TLS error, a broken connection or too many redirects are to review; an address phil/safe-http refuses is refused.
 */
final readonly class HttpLinkProbe implements LinkProbe, ResetInterface
{
    public const string USER_AGENT = 'PhilLinkChecker/0.1';

    /** A media type no answer carries: the `GET` that replaces a refused `HEAD` reads no body. */
    private const string NO_BODY = 'application/x-phil-link-checker-no-body';

    private const array PERMANENT = [301, 308];

    private SafeHttpClient $client;

    private HopRecorder $recorder;

    /**
     * @param array<string, string> $resolve fixed addresses for host names, as curl's `--resolve`; still checked
     *                                       against the private networks
     */
    public function __construct(
        ?HttpClientInterface $client = null,
        Limits $limits = new Limits(userAgent: self::USER_AGENT),
        array $resolve = [],
        private HostThrottle $throttle = new IntervalThrottle(),
    ) {
        $this->recorder = new HopRecorder($client ?? HttpClient::create());
        $this->client = new SafeHttpClient($this->recorder, $limits, $resolve);
    }

    #[Override]
    public function probe(Url $url, bool $withBody = false): ProbeResult
    {
        try {
            if (!$withBody) {
                $this->throttle->wait($url->host);
                $response = $this->client->head($url);

                if (!\in_array($response->status, [405, 501], true)) {
                    return new ProbeResult($this->classify($response));
                }

                $this->recorder->forget();
            }

            $this->throttle->wait($url->host);
            $response = $this->client->get($url, $withBody ? new ReadRule('text/html, application/xhtml+xml;q=0.9, */*;q=0.1', ['text/html', 'application/xhtml+xml']) : new ReadRule('*/*', [self::NO_BODY]));
            $html = $withBody && \in_array($response->mediaType(), ['text/html', 'application/xhtml+xml'], true) ? $response->body : null;

            return new ProbeResult($this->classify($response), $html, null === $html || $response->complete);
        } catch (RefusedAddress) {
            return new ProbeResult(new LinkResult(LinkStatus::Refused, reason: 'not a public address'));
        } catch (TooManyRedirects) {
            return new ProbeResult(new LinkResult(LinkStatus::Review, reason: 'too many redirects'));
        } catch (FetchFailed $fetchFailed) {
            return new ProbeResult($this->failure($fetchFailed));
        } catch (SafeHttpException) {
            return new ProbeResult(new LinkResult(LinkStatus::Review, reason: 'request failed'));
        } finally {
            $this->recorder->forget();
        }
    }

    #[Override]
    public function reset(): void
    {
        $this->client->reset();

        if ($this->throttle instanceof ResetInterface) {
            $this->throttle->reset();
        }
    }

    private function classify(SafeResponse $response): LinkResult
    {
        $status = $response->status;
        $redirects = \count($response->redirects);
        $target = 0 === $redirects ? null : $response->url;

        if ($status >= 200 && $status < 300) {
            if (!$target instanceof Url) {
                return new LinkResult(LinkStatus::Alive, $status);
            }

            $permanent = [] === array_diff(\array_slice($this->recorder->codes(), 0, $redirects), self::PERMANENT);

            return new LinkResult($permanent ? LinkStatus::PermanentRedirect : LinkStatus::TemporaryRedirect, $status, $target);
        }

        if (404 === $status || 410 === $status) {
            return new LinkResult(LinkStatus::Dead, $status, $target);
        }

        return new LinkResult(LinkStatus::Review, $status, $target);
    }

    /**
     * A read that did not end, by the words of the transport: a host name that does not resolve is dead; a TLS error,
     * a timeout or any other failure is to review.
     */
    private function failure(FetchFailed $failure): LinkResult
    {
        // The addresses the messages quote are left out: a host named `tls.example` is no TLS error.
        $message = preg_replace('/"[^"]*"/', '', $failure->getMessage()) ?? '';

        return match (true) {
            1 === preg_match('/\b(?:ssl|tls)\b|certificate/i', $message) => new LinkResult(LinkStatus::Review, reason: 'TLS error'),
            1 === preg_match('/timeout|timed out|took longer|max duration/i', $message) => new LinkResult(LinkStatus::Review, reason: 'timeout'),
            1 === preg_match('/resolve host|getaddrinfo|name or service not known|nodename nor servname|no address associated|name resolution/i', $message) => new LinkResult(LinkStatus::Dead, reason: 'host not found'),
            default => new LinkResult(LinkStatus::Review, reason: 'connection failed'),
        };
    }
}
