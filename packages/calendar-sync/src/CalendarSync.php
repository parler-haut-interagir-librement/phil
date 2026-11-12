<?php

declare(strict_types=1);

namespace Phil\CalendarSync;

use Phil\CalendarSync\Contract\BusySource;
use Phil\CalendarSync\Contract\BusyStore;
use Phil\CalendarSync\Contract\CalendarDestination;
use Phil\CalendarSync\Contract\ConnectionRepository;
use Phil\CalendarSync\Contract\CredentialStore;
use Phil\CalendarSync\Exception\InvalidCalendarData;
use Phil\CalendarSync\Exception\ProviderFailed;
use Phil\CalendarSync\Exception\UnknownConnection;

/**
 * Synchronises the linked calendars: reads the busy periods of a connection into the application's store, and writes
 * the site's events into a connection, each through the adapter the connection names.
 *
 * It keeps nothing between calls: the connection and its credentials are asked for at each one, so a worker that runs
 * for days sees a token refreshed or a connection removed in the meantime.
 */
final readonly class CalendarSync
{
    /** @var array<string, BusySource> */
    private array $sources;

    /** @var array<string, CalendarDestination> */
    private array $destinations;

    /**
     * @param iterable<BusySource>          $sources      the adapters that read, by their name
     * @param iterable<CalendarDestination> $destinations the adapters that write, by their name
     */
    public function __construct(
        private ConnectionRepository $connections,
        private CredentialStore $credentials,
        private BusyStore $store,
        iterable $sources = [],
        iterable $destinations = [],
    ) {
        $byName = [];

        foreach ($sources as $source) {
            $byName[$source->name()] = $source;
        }

        $this->sources = $byName;
        $byName = [];

        foreach ($destinations as $destination) {
            $byName[$destination->name()] = $destination;
        }

        $this->destinations = $byName;
    }

    /**
     * Reads the busy periods of a connection within a window, and gives them to the store, joined and in order.
     *
     * @return list<BusyPeriod> what was stored
     *
     * @throws UnknownConnection   when the connection or its adapter is unknown
     * @throws ProviderFailed      when the service refuses or does not answer; the store is left as it was
     * @throws InvalidCalendarData when its answer cannot be read; the store is left as it was
     */
    public function refreshBusy(string $connectionId, SyncWindow $window): array
    {
        $connection = $this->connection($connectionId);
        $source = $this->sources[$connection->provider] ?? throw UnknownConnection::provider($connection->provider, 'to read busy times from');
        $periods = BusyPeriods::merge($source->busy($connection, $this->credentials->credentialsFor($connection), $window), $window);
        $this->store->replace($connection->id, $window, $periods);

        return $periods;
    }

    /**
     * Writes an event into a connection, replacing the one of the same identifier.
     *
     * @throws UnknownConnection
     * @throws ProviderFailed
     */
    public function publish(string $connectionId, OutboundEvent $event): void
    {
        $connection = $this->connection($connectionId);
        $this->destination($connection)->put($connection, $this->credentials->credentialsFor($connection), $event);
    }

    /**
     * Removes an event from a connection; one that is not there is not an error.
     *
     * @throws UnknownConnection
     * @throws ProviderFailed
     */
    public function withdraw(string $connectionId, string $uid): void
    {
        $connection = $this->connection($connectionId);
        $this->destination($connection)->remove($connection, $this->credentials->credentialsFor($connection), $uid);
    }

    private function connection(string $id): CalendarConnection
    {
        return $this->connections->find($id) ?? throw UnknownConnection::id($id);
    }

    private function destination(CalendarConnection $connection): CalendarDestination
    {
        return $this->destinations[$connection->provider] ?? throw UnknownConnection::provider($connection->provider, 'to write events to');
    }
}
