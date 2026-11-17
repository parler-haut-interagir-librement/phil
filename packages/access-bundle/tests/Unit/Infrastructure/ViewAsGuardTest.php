<?php

declare(strict_types=1);

namespace Phil\AccessBundle\Tests\Unit\Infrastructure;

use Override;
use Phil\AccessBundle\Application\Exception\ViewAsRefused;
use Phil\AccessBundle\Domain\ValueObject\MemberId;
use Phil\AccessBundle\Infrastructure\Http\Attribute\AllowedDuringViewAs;
use Phil\AccessBundle\Infrastructure\Http\Attribute\RefusedDuringViewAs;
use Phil\AccessBundle\Infrastructure\Http\ViewAsController;
use Phil\AccessBundle\Infrastructure\Http\ViewAsGuard;
use Phil\AccessBundle\Tests\Fixtures\GuardedController;
use Phil\AccessBundle\Tests\Fixtures\Stage;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Security\Csrf\CsrfTokenManager;

#[CoversClass(ViewAsGuard::class)]
#[CoversClass(AllowedDuringViewAs::class)]
#[CoversClass(RefusedDuringViewAs::class)]
final class ViewAsGuardTest extends TestCase
{
    private Stage $stage;

    #[Override]
    protected function setUp(): void
    {
        $this->stage = new Stage();
        $this->stage->spaces->addAdmin(MemberId::of('ada'));
        $this->stage->spaces->addMember(MemberId::of('ann'));
        $this->stage->member->signIn('ada');
    }

    /**
     * @return iterable<string, array{string, string, bool}>
     */
    public static function requests(): iterable
    {
        foreach (['GET', 'HEAD', 'OPTIONS'] as $method) {
            yield $method.' plain' => [$method, 'plain', false];
            yield $method.' refused' => [$method, 'refused', true];
        }

        foreach (['POST', 'PUT', 'PATCH', 'DELETE'] as $method) {
            yield $method.' plain' => [$method, 'plain', true];
            yield $method.' allowed' => [$method, 'allowed', false];
            yield $method.' refused' => [$method, 'refused', true];
        }
    }

    #[DataProvider('requests')]
    public function testDuringAViewOnlyWhatReadsGoesThrough(string $method, string $action, bool $refused): void
    {
        $this->stage->viewAs->start(MemberId::of('ann'));
        $guard = new ViewAsGuard($this->stage->viewAs, $this->stage);

        try {
            $guard->onController($this->event($method, $action));
            self::assertFalse($refused, 'The request should have been refused.');
        } catch (AccessDeniedHttpException $accessDeniedHttpException) {
            self::assertTrue($refused, 'The request should have gone through.');
            self::assertSame('Viewing the site as a member only reads: stop viewing as them to make a change.', $accessDeniedHttpException->getMessage());
            self::assertInstanceOf(ViewAsRefused::class, $accessDeniedHttpException->getPrevious());
            self::assertSame(['notice', \sprintf('A %s request was refused while viewing the site as ann.', $method)], $this->stage->logs[1]);
        }
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function requestsWithoutAView(): iterable
    {
        foreach (self::requests() as $name => [$method, $action]) {
            yield $name => [$method, $action];
        }
    }

    #[DataProvider('requestsWithoutAView')]
    public function testWithoutAViewEverythingGoesThrough(string $method, string $action): void
    {
        new ViewAsGuard($this->stage->viewAs)->onController($this->event($method, $action));

        self::assertSame([], $this->stage->logs);
    }

    public function testTheFormsOfTheViewGoThroughByTheirClassAttribute(): void
    {
        $this->stage->viewAs->start(MemberId::of('ann'));
        $controller = new ViewAsController($this->stage->viewAs, new CsrfTokenManager());

        new ViewAsGuard($this->stage->viewAs, $this->stage)->onController(new ControllerEvent(self::createStub(HttpKernelInterface::class), $controller->stop(...), Request::create('/', 'POST'), HttpKernelInterface::MAIN_REQUEST));

        self::assertCount(1, $this->stage->logs);
    }

    public function testAViewWhoseAdminLostTheRightGuardsNothing(): void
    {
        $this->stage->viewAs->start(MemberId::of('ann'));
        $this->stage->spaces->removeAdmin(MemberId::of('ada'));

        new ViewAsGuard($this->stage->viewAs, $this->stage)->onController($this->event('POST', 'plain'));

        self::assertNull($this->stage->viewAsStore->member);
    }

    public function testARefusalWorksWithoutALogger(): void
    {
        $this->stage->viewAs->start(MemberId::of('ann'));

        $this->expectException(AccessDeniedHttpException::class);

        new ViewAsGuard($this->stage->viewAs)->onController($this->event('POST', 'plain'));
    }

    public function testASafeRequestDoesNotReadTheView(): void
    {
        $this->stage->viewAs->start(MemberId::of('ann'));
        $this->stage->spaces->removeAdmin(MemberId::of('ada'));

        new ViewAsGuard($this->stage->viewAs)->onController($this->event('GET', 'plain'));

        self::assertSame('ann', $this->stage->viewAsStore->member?->value);
    }

    private function event(string $method, string $action): ControllerEvent
    {
        $controller = new GuardedController();
        $callable = match ($action) {
            'allowed' => $controller->allowed(...),
            'refused' => $controller->refused(...),
            default => $controller->plain(...),
        };

        return new ControllerEvent(self::createStub(HttpKernelInterface::class), $callable, Request::create('/', $method), HttpKernelInterface::MAIN_REQUEST);
    }
}
