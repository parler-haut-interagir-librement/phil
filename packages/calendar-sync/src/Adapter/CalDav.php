<?php

declare(strict_types=1);

namespace Phil\CalendarSync\Adapter;

use DOMDocument;
use Override;
use Phil\CalendarSync\BusyPeriod;
use Phil\CalendarSync\CalendarConnection;
use Phil\CalendarSync\Contract\BusySource;
use Phil\CalendarSync\Contract\CalendarDestination;
use Phil\CalendarSync\Credentials;
use Phil\CalendarSync\Exception\InvalidCalendarData;
use Phil\CalendarSync\Exception\InvalidSync;
use Phil\CalendarSync\ICalendar\ICalendarReader;
use Phil\CalendarSync\ICalendar\ICalendarWriter;
use Phil\CalendarSync\ICalendar\RuleBudget;
use Phil\CalendarSync\OutboundEvent;
use Phil\CalendarSync\SyncWindow;
use Phil\Url\Exception\InvalidUrl;
use Phil\Url\Url;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\Clock\NativeClock;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\HttpClient\NoPrivateNetworkHttpClient;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * The adapter `caldav`: a calendar collection of a CalDAV server (RFC 4791) — Nextcloud, Fastmail, iCloud, Radicale —,
 * read and written with a user name and an application password, or a bearer token.
 *
 * Busy times are asked by the `free-busy-query` report (§7.10), which answers with times only; a server that does not
 * offer it is asked by a `calendar-query` for the times of the events alone, expanded by the server, and read again here
 * when it does not expand them. An event is written at `<collection>/<hash of its identifier>.ics`, replaced when it is
 * written again, and deleted by that address.
 *
 * The connection's calendar is the address of the collection. The client is wrapped in Symfony's
 * `NoPrivateNetworkHttpClient`: an address that is or resolves to a private network is refused, and no redirect is
 * followed, so credentials never reach another host.
 */
final readonly class CalDav implements BusySource, CalendarDestination
{
    public const string NAME = 'caldav';

    private const string DAV = 'DAV:';

    private const string CALDAV = 'urn:ietf:params:xml:ns:caldav';

    private HttpCall $http;

    /**
     * @param array<string, string> $resolve fixed addresses for host names, as curl's `--resolve`, still checked against
     *                                       private networks
     */
    public function __construct(
        ?HttpClientInterface $client = null,
        private array $resolve = [],
        private ClockInterface $clock = new NativeClock(),
    ) {
        $this->http = new HttpCall(new NoPrivateNetworkHttpClient($client ?? HttpClient::create()), self::NAME);
    }

    #[Override]
    public function name(): string
    {
        return self::NAME;
    }

    #[Override]
    public function busy(CalendarConnection $connection, Credentials $credentials, SyncWindow $window): iterable
    {
        $collection = $this->collection($connection);
        $range = \sprintf('<C:time-range start="%s" end="%s"/>', $window->from->format('Ymd\THis\Z'), $window->to->format('Ymd\THis\Z'));
        [$status, $body] = $this->http->send('REPORT', $collection, $this->options($credentials, [
            'headers' => ['Depth' => '1', 'Content-Type' => 'application/xml; charset=utf-8'],
            'body' => '<?xml version="1.0" encoding="utf-8"?><C:free-busy-query xmlns:C="'.self::CALDAV.'">'.$range.'</C:free-busy-query>',
        ]), [400, 404, 405, 415, 422, 501]);

        if (200 === $status) {
            return ICalendarReader::busy($body, $window, $connection->timezone);
        }

        $times = implode('', array_map(static fn (string $property): string => '<C:prop name="'.$property.'"/>', ['UID', 'DTSTART', 'DTEND', 'DURATION', 'RRULE', 'RDATE', 'EXDATE', 'RECURRENCE-ID', 'STATUS', 'TRANSP']));
        [, $body] = $this->http->send('REPORT', $collection, $this->options($credentials, [
            'headers' => ['Depth' => '1', 'Content-Type' => 'application/xml; charset=utf-8'],
            'body' => '<?xml version="1.0" encoding="utf-8"?>'
                .'<C:calendar-query xmlns:D="'.self::DAV.'" xmlns:C="'.self::CALDAV.'">'
                .'<D:prop><C:calendar-data><C:comp name="VCALENDAR"><C:comp name="VEVENT">'.$times.'</C:comp></C:comp>'
                .str_replace('time-range', 'expand', $range).'</C:calendar-data></D:prop>'
                .'<C:filter><C:comp-filter name="VCALENDAR"><C:comp-filter name="VEVENT">'.$range.'</C:comp-filter></C:comp-filter></C:filter>'
                .'</C:calendar-query>',
        ]));

        return $this->periods($body, $window, $connection);
    }

    #[Override]
    public function put(CalendarConnection $connection, Credentials $credentials, OutboundEvent $event): void
    {
        $this->http->send('PUT', $this->resource($connection, $event->uid), $this->options($credentials, [
            'headers' => ['Content-Type' => 'text/calendar; charset=utf-8'],
            'body' => ICalendarWriter::event($event, $this->clock->now()),
        ]));
    }

    #[Override]
    public function remove(CalendarConnection $connection, Credentials $credentials, string $uid): void
    {
        $this->http->send('DELETE', $this->resource($connection, $uid), $this->options($credentials), [404, 410]);
    }

    /**
     * @param array<string, mixed> $options
     *
     * @return array<string, mixed>
     */
    private function options(Credentials $credentials, array $options = []): array
    {
        return $options + $credentials->httpOptions() + ['resolve' => $this->resolve];
    }

    /**
     * The busy periods of each `calendar-data` of a multistatus answer.
     *
     * @return list<BusyPeriod>
     */
    private function periods(string $body, SyncWindow $window, CalendarConnection $connection): array
    {
        $document = new DOMDocument();

        // A document type is refused before the text is parsed, so that no entity it declares is ever expanded.
        if ('' === $body || false !== stripos($body, '<!DOCTYPE') || !$document->loadXML($body, \LIBXML_NONET | \LIBXML_NOERROR | \LIBXML_NOWARNING)) {
            throw InvalidCalendarData::because('A CalDAV answer is an XML multistatus without a document type.');
        }

        $periods = [];
        $rules = new RuleBudget();

        foreach ($document->getElementsByTagNameNS(self::CALDAV, 'calendar-data') as $data) {
            if ('' !== trim($data->textContent)) {
                array_push($periods, ...ICalendarReader::busy($data->textContent, $window, $connection->timezone, $rules));
            }
        }

        return $periods;
    }

    private function collection(CalendarConnection $connection): string
    {
        try {
            $url = Url::fromString($connection->calendar);
        } catch (InvalidUrl) {
            throw InvalidSync::because('The calendar of a "caldav" connection is the https address of a collection.');
        }

        if ('https' !== $url->scheme || null !== $url->query || null !== $url->fragment) {
            throw InvalidSync::because('The calendar of a "caldav" connection is the https address of a collection.');
        }

        return rtrim($url->toString(), '/').'/';
    }

    /**
     * The address of an event in the collection: a name of its own that no identifier can turn into a path.
     */
    private function resource(CalendarConnection $connection, string $uid): string
    {
        return $this->collection($connection).hash('sha256', $uid).'.ics';
    }
}
