<?php

declare(strict_types=1);

namespace Phil\CalendarBundle\Application\View;

/**
 * How the application names its hosts, for the columns of a shared day: the calendar knows a host only by its kind and
 * reference.
 */
interface HostLabels
{
    /**
     * The label of each host asked for, by `kind:reference`; a host left out is shown by its `kind:reference`.
     *
     * @param list<string> $hosts as `kind:reference`
     *
     * @return array<string, string>
     */
    public function labels(array $hosts): array;
}
