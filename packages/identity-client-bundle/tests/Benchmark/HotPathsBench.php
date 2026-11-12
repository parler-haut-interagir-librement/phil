<?php

declare(strict_types=1);

namespace Phil\IdentityClientBundle\Tests\Benchmark;

use Phil\IdentityClientBundle\Application\Elevation\AssuranceLevel;
use Phil\IdentityClientBundle\Application\Elevation\AssuranceLevels;
use Phil\IdentityClientBundle\Infrastructure\Security\AssuranceEvaluator;
use Phil\IdentityClientBundle\Infrastructure\Security\DeclaredOriginsReturnToPolicy;
use Phil\IdentityClientBundle\Infrastructure\Security\SessionCapListener;
use Phil\IdentityClientBundle\Infrastructure\Security\TokenAttribute;
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
        $this->token->setAuthenticationProofs(['pwd' => $clock->now()->getTimestamp()]);

        $this->cap = new SessionCapListener($clock, 43200);
        $this->event = new CheckRefreshedUserEvent($this->token, $user, $user);
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
}
