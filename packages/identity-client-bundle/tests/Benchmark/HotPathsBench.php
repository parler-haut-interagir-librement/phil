<?php

declare(strict_types=1);

namespace Phil\IdentityClientBundle\Tests\Benchmark;

use Phil\IdentityClientBundle\Application\Elevation\AssuranceLevel;
use Phil\IdentityClientBundle\Application\Elevation\AssuranceLevels;
use Phil\IdentityClientBundle\Infrastructure\Security\AssuranceEvaluator;
use Phil\IdentityClientBundle\Infrastructure\Security\DeclaredOriginsReturnToPolicy;
use Phil\IdentityClientBundle\Infrastructure\Security\SessionCapListener;
use Phil\IdentityClientBundle\Infrastructure\Security\SessionRequirementsListener;
use Phil\IdentityClientBundle\Infrastructure\Security\TokenAttribute;
use Phil\IdentityClientBundle\Infrastructure\Store\CacheReplayFloor;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Security\Core\User\InMemoryUser;
use Symfony\Component\Security\Http\Authenticator\Token\PostAuthenticationToken;
use Symfony\Component\Security\Http\Event\CheckRefreshedUserEvent;

/**
 * What runs on every request of a session, and what a visitor can make the application parse.
 *
 * @Groups({"identity-client"})
 */
final readonly class HotPathsBench
{
    private DeclaredOriginsReturnToPolicy $policy;

    private AssuranceEvaluator $evaluator;

    private AssuranceLevel $level;

    private PostAuthenticationToken $token;

    private SessionCapListener $cap;

    private CheckRefreshedUserEvent $event;

    private SessionRequirementsListener $requirements;

    public function __construct()
    {
        $clock = new MockClock();
        $this->policy = new DeclaredOriginsReturnToPolicy(['https://app.example.test']);
        $this->level = new AssuranceLevel('strong', 'b', 300);
        $levels = new AssuranceLevels([new AssuranceLevel('basic', 'a'), $this->level]);
        $this->evaluator = new AssuranceEvaluator($levels, $clock);
        $user = new InMemoryUser('subject', null);
        $this->token = new PostAuthenticationToken($user, 'main', []);
        $this->token->setAttribute(TokenAttribute::ASSURANCE, 'b');
        $this->token->setAttribute(TokenAttribute::SESSION_STARTED_AT, $clock->now()->getTimestamp());
        $this->token->setAttribute(TokenAttribute::SIGNED_IN_AT, $clock->now()->getTimestamp());
        $this->token->setAuthenticationProofs(['pwd' => $clock->now()->getTimestamp()]);

        $this->cap = new SessionCapListener($clock, 43200);
        $this->event = new CheckRefreshedUserEvent($this->token, $user, $user);
        $this->requirements = new SessionRequirementsListener(
            new AssuranceLevels([new AssuranceLevel('basic', 'a'), $this->level], 'basic'),
            new CacheReplayFloor(new ArrayAdapter(), $clock),
        );
    }

    /**
     * @Revs(2000)
     *
     * @Iterations(5)
     */
    public function benchLocalReturnTo(): void
    {
        $this->policy->resolve('/account/orders?tab=open&page=3');
    }

    /**
     * @Revs(2000)
     *
     * @Iterations(5)
     */
    public function benchRefusedReturnTo(): void
    {
        $this->policy->resolve('https://evil.example.test/'.str_repeat('a', 2000));
    }

    /**
     * @Revs(10000)
     *
     * @Iterations(5)
     */
    public function benchAssuranceCheck(): void
    {
        $this->evaluator->isSatisfied($this->level, $this->token);
    }

    /**
     * @Revs(10000)
     *
     * @Iterations(5)
     */
    public function benchSessionCap(): void
    {
        ($this->cap)($this->event);
    }

    /**
     * The required level and the replay floor, read from an in-memory pool: what the listener adds to a request.
     *
     * @Revs(10000)
     *
     * @Iterations(5)
     */
    public function benchSessionRequirements(): void
    {
        ($this->requirements)($this->event);
    }
}
