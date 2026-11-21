<?php

declare(strict_types=1);

namespace Phil\IdentityClientBundle\Tests\Unit\Infrastructure;

use Phil\IdentityClientBundle\Application\Contract\ReplayFloor;
use Phil\IdentityClientBundle\Application\Elevation\AssuranceLevel;
use Phil\IdentityClientBundle\Application\Elevation\AssuranceLevels;
use Phil\IdentityClientBundle\Application\Exception\AssuranceLevelNotReachedException;
use Phil\IdentityClientBundle\Application\Exception\SessionPredatesReplayFloorException;
use Phil\IdentityClientBundle\Infrastructure\Security\SessionRequirementsListener;
use Phil\IdentityClientBundle\Infrastructure\Security\TokenAttribute;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\User\InMemoryUser;
use Symfony\Component\Security\Http\Event\CheckRefreshedUserEvent;

#[CoversClass(SessionRequirementsListener::class)]
#[CoversClass(AssuranceLevelNotReachedException::class)]
#[CoversClass(SessionPredatesReplayFloorException::class)]
final class SessionRequirementsListenerTest extends TestCase
{
    private const int FLOOR = 1_800_000_000;

    public function testATokenOfAnotherAuthenticatorIsLeftAlone(): void
    {
        $floor = new FixedReplayFloor(self::FLOOR);

        self::assertFalse($this->check([TokenAttribute::ASSURANCE => 'a'], 'strong', $floor)->isUserChanged());
        self::assertSame(0, $floor->reads);
    }

    public function testASessionBelowTheRequiredLevelEnds(): void
    {
        $floor = new FixedReplayFloor(self::FLOOR);
        $event = $this->check($this->session('a', self::FLOOR), 'strong', $floor);

        self::assertTrue($event->isUserChanged());
        self::assertInstanceOf(AssuranceLevelNotReachedException::class, $event->getException());
        self::assertSame(0, $floor->reads);
    }

    public function testASessionAtTheRequiredLevelStays(): void
    {
        self::assertFalse($this->check($this->session('b', null), 'strong', null)->isUserChanged());
    }

    public function testWithoutRequirementAnySessionStays(): void
    {
        self::assertFalse($this->check($this->session(null, null), null, null)->isUserChanged());
    }

    public function testASessionBegunBeforeTheFloorEnds(): void
    {
        $event = $this->check($this->session('b', self::FLOOR - 1), null, new FixedReplayFloor(self::FLOOR));

        self::assertTrue($event->isUserChanged());
        self::assertInstanceOf(SessionPredatesReplayFloorException::class, $event->getException());
    }

    public function testASessionBegunAtTheFloorStays(): void
    {
        self::assertFalse($this->check($this->session(null, self::FLOOR), null, new FixedReplayFloor(self::FLOOR))->isUserChanged());
    }

    public function testASessionWithoutSignInDateEndsUnderAFloor(): void
    {
        $event = $this->check($this->session('b', null), 'strong', new FixedReplayFloor(self::FLOOR));

        self::assertInstanceOf(SessionPredatesReplayFloorException::class, $event->getException());
    }

    public function testTheExceptionsHaveMessages(): void
    {
        self::assertSame('The authentication does not reach the required assurance level.', new AssuranceLevelNotReachedException()->getMessageKey());
        self::assertSame('The session began before the replay floor.', new SessionPredatesReplayFloorException()->getMessageKey());
    }

    /**
     * @return array<string, mixed>
     */
    private function session(?string $acr, ?int $signedInAt): array
    {
        return array_filter([
            TokenAttribute::SESSION_STARTED_AT => self::FLOOR - 3600,
            TokenAttribute::ASSURANCE => $acr,
            TokenAttribute::SIGNED_IN_AT => $signedInAt,
        ], static fn (mixed $value): bool => null !== $value);
    }

    /**
     * @param array<string, mixed> $attributes
     */
    private function check(array $attributes, ?string $required, ?ReplayFloor $floor): CheckRefreshedUserEvent
    {
        $user = new InMemoryUser('subject-1', null);
        $event = new CheckRefreshedUserEvent(TokenFixture::token($attributes), $user, $user);
        $levels = new AssuranceLevels([new AssuranceLevel('basic', 'a'), new AssuranceLevel('strong', 'b')], $required);
        new SessionRequirementsListener($levels, $floor)($event);

        return $event;
    }
}
