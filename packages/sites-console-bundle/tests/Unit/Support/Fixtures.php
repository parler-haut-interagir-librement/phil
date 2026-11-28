<?php

declare(strict_types=1);

namespace Phil\SitesConsoleBundle\Tests\Unit\Support;

use DateTimeImmutable;
use Phil\SitesConsoleBundle\Domain\Model\Site;
use Phil\SitesConsoleBundle\Domain\Status\HealthState;
use Phil\SitesConsoleBundle\Domain\ValueObject\AgentUrl;
use Phil\SitesConsoleBundle\Domain\ValueObject\CheckState;
use Phil\SitesConsoleBundle\Domain\ValueObject\HealthSummary;
use Phil\SitesConsoleBundle\Domain\ValueObject\StatusDocument;

/**
 * Sites and statuses the tests share.
 */
final class Fixtures
{
    public const string NOW = '2026-11-20 10:00:00';

    public static function now(): DateTimeImmutable
    {
        return new DateTimeImmutable(self::NOW);
    }

    /**
     * @param list<string> $groups
     */
    public static function site(string $id = 'shop', array $groups = []): Site
    {
        return Site::register($id, ucfirst($id), AgentUrl::fromString('https://'.$id.'.example.com/_sites-console'), Keys::site()->verifyingKey(), $groups, self::now());
    }

    /**
     * @param array<string, string> $packages
     */
    public static function status(string $site = 'shop', array $packages = ['phil/url' => '0.1.4'], HealthState $health = HealthState::Up): StatusDocument
    {
        return new StatusDocument($site, self::now(), '8.5.1', '8.2.0', $packages, new HealthSummary($health, [new CheckState('database', $health, 'ok')]));
    }
}
