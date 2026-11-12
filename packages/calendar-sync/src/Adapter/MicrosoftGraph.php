<?php

declare(strict_types=1);

namespace Phil\CalendarSync\Adapter;

use DateTimeImmutable;
use DateTimeZone;
use Override;
use Phil\CalendarSync\BusyPeriod;
use Phil\CalendarSync\CalendarConnection;
use Phil\CalendarSync\Contract\BusySource;
use Phil\CalendarSync\Contract\CalendarDestination;
use Phil\CalendarSync\Credentials;
use Phil\CalendarSync\Exception\InvalidCalendarData;
use Phil\CalendarSync\OutboundEvent;
use Phil\CalendarSync\SyncWindow;
use Phil\Url\Exception\InvalidUrl;
use Phil\Url\Url;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * The adapter `microsoft`: an Outlook calendar of a Microsoft 365 or Outlook.com account, through Microsoft Graph
 * v1.0, with an OAuth access token the application obtained and refreshes — `Calendars.ReadBasic` to read,
 * `Calendars.ReadWrite` to write.
 *
 * Busy times are read from the calendar view, asking only for the start, the end, the availability and the
 * cancellation of each event, in UTC: never a subject. Free and "working elsewhere" times are left out, tentative ones
 * kept as tentative. At most `MAX_PAGES` pages of `PAGE` events, the next ones followed only on Graph's own host.
 *
 * An event is found again by an extended property that holds its identifier: updated when it exists, created when it
 * does not, deleted when found.
 *
 * The connection's calendar is the Graph identifier of a calendar of the signed-in user, or `primary` for their
 * default calendar.
 */
final readonly class MicrosoftGraph implements BusySource, CalendarDestination
{
    public const string NAME = 'microsoft';

    public const string API = 'https://graph.microsoft.com/v1.0';

    public const int PAGE = 500;

    public const int MAX_PAGES = 20;

    /** The extended property that holds an event's identifier: a GUID of the package's and a name. */
    public const string PROPERTY = 'String {5b9c4a0e-3f1d-4c2b-9e57-7a1f0d6c8b24} Name PhilCalendarSyncUid';

    private const array FREE = ['free', 'workingelsewhere'];

    private HttpCall $http;

    public function __construct(?HttpClientInterface $client = null)
    {
        $this->http = new HttpCall($client ?? HttpClient::create(), self::NAME);
    }

    #[Override]
    public function name(): string
    {
        return self::NAME;
    }

    /**
     * @return list<BusyPeriod>
     */
    #[Override]
    public function busy(CalendarConnection $connection, Credentials $credentials, SyncWindow $window): iterable
    {
        $options = $credentials->httpOptions() + ['headers' => ['Prefer' => 'outlook.timezone="UTC"']];
        $url = $this->calendar($connection).'/calendarView';
        $query = [
            'startDateTime' => $window->from->format('Y-m-d\TH:i:s\Z'),
            'endDateTime' => $window->to->format('Y-m-d\TH:i:s\Z'),
            '$select' => 'start,end,showAs,isCancelled',
            '$top' => self::PAGE,
        ];
        $periods = [];
        $utc = new DateTimeZone('UTC');

        for ($page = 1; null !== $url; ++$page) {
            if ($page > self::MAX_PAGES) {
                throw InvalidCalendarData::because(\sprintf('A calendar of Microsoft Graph is read in %d pages at most.', self::MAX_PAGES));
            }

            [, $body] = $this->http->send('GET', $url, $options + (null === $query ? [] : ['query' => $query]));
            $answer = Json::object($body);

            foreach (\is_array($answer['value'] ?? null) ? $answer['value'] : [] as $event) {
                if (!\is_array($event) || !\is_array($event['start'] ?? null) || !\is_array($event['end'] ?? null)) {
                    throw InvalidCalendarData::because('An event of Microsoft Graph has a start and an end.');
                }

                $showAs = strtolower(\is_string($event['showAs'] ?? null) ? $event['showAs'] : 'busy');

                if (true === ($event['isCancelled'] ?? false) || \in_array($showAs, self::FREE, true)) {
                    continue;
                }

                $start = Json::moment($event['start']['dateTime'] ?? null, $utc);
                $end = Json::moment($event['end']['dateTime'] ?? null, $utc);

                if ($end > $start) {
                    $periods[] = new BusyPeriod($start, $end, 'tentative' === $showAs);
                }
            }

            $url = $this->next($answer['@odata.nextLink'] ?? null);
            $query = null;
        }

        return $periods;
    }

    #[Override]
    public function put(CalendarConnection $connection, Credentials $credentials, OutboundEvent $event): void
    {
        $id = $this->find($connection, $credentials, $event->uid);
        $resource = $this->resource($event);

        if (null === $id) {
            $this->http->send('POST', $this->calendar($connection).'/events', $credentials->httpOptions() + ['json' => $resource + [
                'singleValueExtendedProperties' => [['id' => self::PROPERTY, 'value' => $event->uid]],
            ]]);

            return;
        }

        $this->http->send('PATCH', self::API.'/me/events/'.rawurlencode($id), $credentials->httpOptions() + ['json' => $resource]);
    }

    #[Override]
    public function remove(CalendarConnection $connection, Credentials $credentials, string $uid): void
    {
        $id = $this->find($connection, $credentials, $uid);

        if (null !== $id) {
            $this->http->send('DELETE', self::API.'/me/events/'.rawurlencode($id), $credentials->httpOptions(), [404, 410]);
        }
    }

    /**
     * The Graph identifier of the event written under an identifier, or `null`.
     */
    private function find(CalendarConnection $connection, Credentials $credentials, string $uid): ?string
    {
        $filter = \sprintf("singleValueExtendedProperties/Any(ep: ep/id eq '%s' and ep/value eq '%s')", self::PROPERTY, str_replace("'", "''", $uid));
        [, $body] = $this->http->send('GET', $this->calendar($connection).'/events', $credentials->httpOptions() + ['query' => ['$filter' => $filter, '$select' => 'id', '$top' => 1]]);
        $answer = Json::object($body);
        $first = \is_array($answer['value'] ?? null) ? array_first($answer['value']) : null;

        return \is_array($first) && \is_string($first['id'] ?? null) && '' !== $first['id'] ? $first['id'] : null;
    }

    private function calendar(CalendarConnection $connection): string
    {
        return 'primary' === $connection->calendar ? self::API.'/me/calendar' : self::API.'/me/calendars/'.rawurlencode($connection->calendar);
    }

    /**
     * The next page, only on Graph's own host.
     */
    private function next(mixed $link): ?string
    {
        if (null === $link) {
            return null;
        }

        try {
            $url = \is_string($link) ? Url::fromString($link) : null;
        } catch (InvalidUrl) {
            $url = null;
        }

        if (!$url instanceof Url || 'https' !== $url->scheme || 'graph.microsoft.com' !== $url->host) {
            throw InvalidCalendarData::because('The next page of Microsoft Graph is on graph.microsoft.com.');
        }

        return $url->toString();
    }

    /**
     * @return array<string, mixed>
     */
    private function resource(OutboundEvent $event): array
    {
        $text = trim(($event->description ?? '').($event->url instanceof Url ? "\n\n".$event->url->toString() : ''));
        $resource = [
            'subject' => $event->title,
            'body' => ['contentType' => 'text', 'content' => $text],
            'start' => $this->moment($event, $event->start),
            'end' => $this->moment($event, $event->end),
            'isAllDay' => $event->allDay,
        ];

        if (null !== $event->location) {
            $resource['location'] = ['displayName' => $event->location];
        }

        return $resource;
    }

    /**
     * A time in UTC, or a day at midnight for whole days, as Graph reads them.
     *
     * @return array{dateTime: string, timeZone: string}
     */
    private function moment(OutboundEvent $event, DateTimeImmutable $moment): array
    {
        return [
            'dateTime' => $event->allDay ? $moment->format('Y-m-d').'T00:00:00' : $moment->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d\TH:i:s'),
            'timeZone' => 'UTC',
        ];
    }
}
