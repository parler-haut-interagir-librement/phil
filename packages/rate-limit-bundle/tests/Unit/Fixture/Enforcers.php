<?php

declare(strict_types=1);

namespace Phil\RateLimitBundle\Tests\Unit\Fixture;

use Phil\RateLimitBundle\Application\Contract\Limiter;
use Phil\RateLimitBundle\Application\KeyResolvers;
use Phil\RateLimitBundle\Application\Limiters;
use Phil\RateLimitBundle\Application\RateLimitEnforcer;
use Phil\RateLimitBundle\Application\RateLimitHeaders;
use Phil\RateLimitBundle\Domain\HeaderStyle;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;

/**
 * An enforcer over limiters in memory: `api` of three, `wide` of ten, `free` without a limit, and the key of the
 * `X-Account` header, also under `account`; the limiters named in `$observed` only observe.
 */
final class Enforcers
{
    /**
     * @param array<string, Limiter>|null $limiters
     * @param list<string>                $observed
     */
    public static function of(?array $limiters = null, ?EventDispatcherInterface $events = null, HeaderStyle $style = HeaderStyle::Draft, array $observed = [], ?LoggerInterface $logger = null): RateLimitEnforcer
    {
        $limiters ??= ['api' => new ScriptedLimiter(3), 'wide' => new ScriptedLimiter(10, 30), 'free' => new ScriptedLimiter(null)];
        $defaults = [];

        foreach (array_keys($limiters) as $name) {
            $defaults[$name] = ['key' => 'header', 'amount' => 1, 'observe' => \in_array($name, $observed, true)];
        }

        $keys = ['header' => new HeaderKey(), 'account' => new HeaderKey('X-Other')];

        return new RateLimitEnforcer(new Limiters(new Locator($limiters), $defaults), new KeyResolvers(new Locator($keys), array_keys($keys)), new RateLimitHeaders($style), $events, $logger);
    }
}
