<?php

declare(strict_types=1);

namespace Phil\ApiContract\Deprecation;

use DateTimeImmutable;

/**
 * Where a client keeps the deprecation signals it has received, endpoint by endpoint.
 *
 * Three readers depend on it: the log, which warns once per endpoint and per day rather than on every call;
 * the profiler panel; and a verification command that a project's CI runs and that fails while any endpoint
 * it calls carries a sunset. The adapter (a cache pool, a table, a file) belongs to the application, and so
 * does the meaning of "endpoint": a route name, a method and path template, never a URL with identifiers in it.
 */
interface ObservedDeprecations
{
    /**
     * Records the latest signal seen on an endpoint. Returns `true` when nothing had been recorded for that
     * endpoint since `$observedAt` minus one day, which is when the caller logs its warning.
     */
    public function record(string $endpoint, DeprecationSignal $signal, DateTimeImmutable $observedAt): bool;

    /**
     * @return array<string, DeprecationSignal> the latest signal per endpoint
     */
    public function all(): array;
}
