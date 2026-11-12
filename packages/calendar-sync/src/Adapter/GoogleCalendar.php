<?php

declare(strict_types=1);

namespace Phil\CalendarSync\Adapter;

use DateTimeImmutable;
use Override;
use Phil\CalendarSync\BusyPeriod;
use Phil\CalendarSync\CalendarConnection;
use Phil\CalendarSync\Contract\BusySource;
use Phil\CalendarSync\Contract\CalendarDestination;
use Phil\CalendarSync\Credentials;
use Phil\CalendarSync\Exception\InvalidCalendarData;
use Phil\CalendarSync\Exception\ProviderFailed;
use Phil\CalendarSync\OutboundEvent;
use Phil\CalendarSync\SyncWindow;
use Phil\Url\Url;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * The adapter `google`: a Google calendar, through the Google Calendar API v3, with an OAuth access token the
 * application obtained and refreshes — the scopes `calendar.freebusy` to read, `calendar.events` to write.
 *
 * Busy times are asked by `freeBusy`, which answers with times only. An event is written under an identifier derived
 * from its own — its SHA-256 in hexadecimal, which Google's alphabet admits —: replaced when it exists, inserted when
 * it does not, deleted by that identifier.
 *
 * The connection's calendar is the Google calendar identifier: `primary`, or an address such as
 * `abc123@group.calendar.google.com`.
 */
final readonly class GoogleCalendar implements BusySource, CalendarDestination
{
    public const string NAME = 'google';

    public const string API = 'https://www.googleapis.com/calendar/v3';

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
        [, $body] = $this->http->send('POST', self::API.'/freeBusy', $credentials->httpOptions() + ['json' => [
            'timeMin' => $window->from->format('Y-m-d\TH:i:s\Z'),
            'timeMax' => $window->to->format('Y-m-d\TH:i:s\Z'),
            'items' => [['id' => $connection->calendar]],
        ]]);
        $answer = Json::object($body);
        $calendars = \is_array($answer['calendars'] ?? null) ? $answer['calendars'] : [];
        $calendar = $calendars[$connection->calendar] ?? (1 === \count($calendars) ? array_first($calendars) : null);

        if (!\is_array($calendar)) {
            throw InvalidCalendarData::because('A free/busy answer of Google names the calendar asked for.');
        }

        $errors = \is_array($calendar['errors'] ?? null) ? $calendar['errors'] : [];

        if ([] !== $errors) {
            $first = array_first($errors);

            throw ProviderFailed::refused(self::NAME, \is_array($first) && \is_string($first['reason'] ?? null) ? $first['reason'] : 'unknown');
        }

        $periods = [];

        foreach (\is_array($calendar['busy'] ?? null) ? $calendar['busy'] : [] as $busy) {
            if (!\is_array($busy)) {
                throw InvalidCalendarData::because('A busy time of Google has a start and an end.');
            }

            $periods[] = new BusyPeriod(Json::moment($busy['start'] ?? null), Json::moment($busy['end'] ?? null));
        }

        return $periods;
    }

    #[Override]
    public function put(CalendarConnection $connection, Credentials $credentials, OutboundEvent $event): void
    {
        $events = self::API.'/calendars/'.rawurlencode($connection->calendar).'/events';
        $id = self::id($event->uid);
        $resource = $this->resource($event, $id);
        [$status] = $this->http->send('PUT', $events.'/'.$id, $credentials->httpOptions() + ['json' => $resource], [404]);

        if (404 === $status) {
            $this->http->send('POST', $events, $credentials->httpOptions() + ['json' => $resource + ['iCalUID' => $event->uid]]);
        }
    }

    #[Override]
    public function remove(CalendarConnection $connection, Credentials $credentials, string $uid): void
    {
        $this->http->send('DELETE', self::API.'/calendars/'.rawurlencode($connection->calendar).'/events/'.self::id($uid), $credentials->httpOptions(), [404, 410]);
    }

    /**
     * The identifier Google keeps for an event: the SHA-256 of its own, 64 characters of `0-9a-f`.
     */
    public static function id(string $uid): string
    {
        return hash('sha256', $uid);
    }

    /**
     * @return array<string, mixed>
     */
    private function resource(OutboundEvent $event, string $id): array
    {
        $resource = [
            'id' => $id,
            'summary' => $event->title,
            'start' => $this->moment($event, $event->start),
            'end' => $this->moment($event, $event->end),
            'sequence' => $event->sequence,
        ];

        if (null !== $event->description) {
            $resource['description'] = $event->description;
        }

        if (null !== $event->location) {
            $resource['location'] = $event->location;
        }

        if ($event->url instanceof Url) {
            $resource['source'] = ['title' => $event->title, 'url' => $event->url->toString()];
        }

        return $resource;
    }

    /**
     * @return array{date: string}|array{dateTime: string, timeZone: string}
     */
    private function moment(OutboundEvent $event, DateTimeImmutable $moment): array
    {
        return $event->allDay
            ? ['date' => $moment->format('Y-m-d')]
            : ['dateTime' => $moment->format('Y-m-d\TH:i:sP'), 'timeZone' => $this->zone($moment)];
    }

    /**
     * The IANA name of a time's zone, or UTC for an offset.
     */
    private function zone(DateTimeImmutable $moment): string
    {
        $name = $moment->getTimezone()->getName();

        return str_contains($name, '/') ? $name : 'UTC';
    }
}
