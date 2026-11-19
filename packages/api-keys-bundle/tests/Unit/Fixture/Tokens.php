<?php

declare(strict_types=1);

namespace Phil\ApiKeysBundle\Tests\Unit\Fixture;

use Phil\ApiKeysBundle\Application\ApiTokens;
use Phil\ApiKeysBundle\Application\Contract\ClientAddressAnonymizer;
use Phil\ApiKeysBundle\Application\OwnerTypes;
use Phil\ApiKeysBundle\Application\ScopeCatalogue;
use Phil\ApiKeysBundle\Application\TokenHasher;
use Phil\ApiKeysBundle\Domain\Contract\ApiTokenRepository;
use Phil\ApiKeysBundle\Domain\Token\TokenFormat;
use Phil\ApiKeysBundle\Infrastructure\Memory\InMemoryApiTokenRepository;
use Symfony\Component\Clock\MockClock;

/**
 * The pieces of the tests: a key, a catalogue of three scopes, and `ApiTokens` on a store in memory at a fixed time.
 */
final class Tokens
{
    public const string KEY = 'a-test-key-of-thirty-two-bytes!!';

    public const string NOW = '2026-11-08 10:00:00 UTC';

    private function __construct()
    {
    }

    public static function scopes(): ScopeCatalogue
    {
        return new ScopeCatalogue([
            'projects:read' => ['description' => 'Read the projects', 'label' => 'scope.projects_read'],
            'projects:write' => ['description' => 'Create and change projects', 'label' => null],
            'billing' => ['description' => 'See the invoices'],
        ]);
    }

    /**
     * @param non-negative-int $throttle
     */
    public static function service(
        ?ApiTokenRepository $repository = null,
        ?MockClock $clock = null,
        ?RecordingDispatcher $events = null,
        ?ClientAddressAnonymizer $addresses = null,
        int $throttle = 300,
        ?OwnerTypes $owners = null,
    ): ApiTokens {
        return new ApiTokens(
            $repository ?? new InMemoryApiTokenRepository(),
            new TokenFormat('test'),
            new TokenHasher(self::KEY),
            self::scopes(),
            $clock ?? new MockClock(self::NOW),
            30,
            365,
            $throttle,
            $events,
            $addresses,
            $owners,
        );
    }
}
