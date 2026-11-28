<?php

declare(strict_types=1);

namespace Phil\CalendarImport\Source;

use Override;
use Phil\CalendarImport\Exception\LimitExceeded;
use Phil\CalendarImport\Exception\SourceUnavailable;
use Phil\CalendarImport\Exception\UnreadableCalendar;
use Phil\CalendarImport\ICalendar\ICalendarReader;
use Phil\CalendarImport\ICalendar\Limit;
use Phil\CalendarImport\ICalendar\ReaderLimits;
use Phil\SafeHttp\Exception\RefusedAddress;
use Phil\SafeHttp\Exception\SafeHttpException;
use Phil\SafeHttp\Exception\TooManyRedirects;
use Phil\SafeHttp\Limits;
use Phil\SafeHttp\ReadRule;
use Phil\SafeHttp\SafeHttpClient;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * An iCalendar feed read by its address, through `phil/safe-http`: no private network, every redirect checked, time and
 * bytes bounded. The read is conditional when validators are given — `If-None-Match`, `If-Modified-Since` —, and a
 * `304 Not Modified` changes nothing. A failure names the feed by its address without its query and fragment, `?…`
 * standing for them, since a private feed often keeps its secret there.
 */
final readonly class IcsUrlSource implements EventSource
{
    public const string ACCEPT = 'text/calendar, text/plain;q=0.5, */*;q=0.1';

    public function __construct(
        private SafeHttpClient $client,
        private ICalendarReader $reader = new ICalendarReader(),
    ) {
    }

    /**
     * A source on its own client: 10 seconds to connect and wait, 30 for the whole read, as many bytes as the reader
     * reads, and the redirects given — none by default, so that a feed that moved is seen rather than followed.
     */
    public static function create(?HttpClientInterface $client = null, ReaderLimits $limits = new ReaderLimits(), int $redirects = 0): self
    {
        return new self(
            new SafeHttpClient($client, new Limits(timeout: 10.0, maxDuration: 30.0, maxBytes: max(Limits::MIN_BYTES, $limits->bytes), maxRedirects: $redirects, userAgent: 'PhilCalendarImport/0.1')),
            new ICalendarReader($limits),
        );
    }

    #[Override]
    public function fetch(CalendarSource $source, ?FetchValidators $validators = null): Fetched
    {
        $validators ??= new FetchValidators();
        $url = $source->url;
        // A private feed often keeps its secret in its query: a message shows the address without it.
        $address = $url->scheme.'://'.$url->host.(null === $url->port ? '' : ':'.$url->port).$url->path.(null === $url->query && null === $url->fragment ? '' : '?…');

        try {
            $response = $this->client->send('GET', $source->url, $validators->headers(), '', new ReadRule(self::ACCEPT));
        } catch (RefusedAddress|TooManyRedirects $refused) {
            throw SourceUnavailable::of(FailureKind::Refused, $refused->getMessage(), $refused);
        } catch (SafeHttpException $failed) {
            throw SourceUnavailable::of(FailureKind::Unreachable, $failed->getMessage(), $failed);
        }

        $answered = new FetchValidators($response->header('etag'), $response->header('last-modified'));

        if (304 === $response->status) {
            return Fetched::notModified($answered->isEmpty() ? $validators : $answered);
        }

        if (!$response->isSuccessful()) {
            throw SourceUnavailable::status($address, $response->status);
        }

        if (!$response->complete) {
            throw SourceUnavailable::of(FailureKind::TooLarge, \sprintf('"%s" answered with more bytes than allowed.', $address));
        }

        try {
            return Fetched::read($this->reader->read($response->body, $source->zone), $answered);
        } catch (UnreadableCalendar $unreadableCalendar) {
            $kind = $unreadableCalendar instanceof LimitExceeded && Limit::Bytes === $unreadableCalendar->limit ? FailureKind::TooLarge : FailureKind::Invalid;

            throw SourceUnavailable::of($kind, \sprintf('"%s" is not a calendar the reader accepts: %s', $address, $unreadableCalendar->getMessage()), $unreadableCalendar);
        }
    }
}
