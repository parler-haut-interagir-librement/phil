<?php

declare(strict_types=1);

namespace Phil\OrganizationsBundle\Tests\Unit\Application;

use Phil\OrganizationsBundle\Application\Exception\NotAMember;
use Phil\OrganizationsBundle\Application\Exception\OrganizationArchived;
use Phil\OrganizationsBundle\Application\Exception\PermissionDenied;
use Phil\OrganizationsBundle\Application\OnBehalf;
use Phil\OrganizationsBundle\Application\OnBehalfContext;
use Phil\OrganizationsBundle\Domain\Event\ActedOnBehalf;
use Phil\OrganizationsBundle\Domain\Exception\InvalidOrganization;
use Phil\OrganizationsBundle\Domain\Model\Organization;
use Phil\OrganizationsBundle\Tests\Fixtures\Stage;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;

#[CoversClass(OnBehalf::class)]
#[CoversClass(OnBehalfContext::class)]
final class OnBehalfTest extends TestCase
{
    private Stage $stage;

    private Organization $acme;

    protected function setUp(): void
    {
        $this->stage = new Stage();
        $this->acme = $this->stage->acme();
    }

    public function testTheRealAuthorIsRecordedBesideTheOrganization(): void
    {
        self::assertNull($this->stage->onBehalf->current());

        $result = $this->stage->onBehalf->act($this->acme->id, Stage::member('bob'), 'post.publish', function (): string {
            self::assertEquals(new OnBehalfContext($this->acme->id->value, 'bob', 'post.publish'), $this->stage->onBehalf->current());
            self::assertSame([], $this->stage->events->events);

            $this->stage->onBehalf->act($this->acme->id, Stage::member('ann'), 'post.sign', function (): void {
                self::assertSame('ann', $this->stage->onBehalf->current()?->authorId);
            });

            self::assertSame('bob', $this->stage->onBehalf->current()?->authorId);

            return 'published';
        });

        self::assertSame('published', $result);
        self::assertNull($this->stage->onBehalf->current());
        self::assertEquals([
            new ActedOnBehalf($this->acme->id->value, 'ann', 'post.sign', $this->stage->clock->now()),
            new ActedOnBehalf($this->acme->id->value, 'bob', 'post.publish', $this->stage->clock->now()),
        ], $this->stage->events->events);
    }

    public function testNothingIsRecordedWhenTheWorkFails(): void
    {
        $caught = null;

        try {
            $this->stage->onBehalf->act($this->acme->id, Stage::member('bob'), 'post.publish', $this->failingWork(...));
        } catch (RuntimeException $runtimeException) {
            $caught = $runtimeException->getMessage();
        }

        self::assertSame('Failed.', $caught);

        self::assertNull($this->stage->onBehalf->current());
        self::assertSame([], $this->stage->events->events);
    }

    public function testOnlyThoseWhoMayActOnBehalf(): void
    {
        $ran = false;
        $work = static function () use (&$ran): void {
            $ran = true;
        };

        foreach ([['cam', PermissionDenied::class], ['zed', NotAMember::class]] as [$member, $exception]) {
            try {
                $this->stage->onBehalf->act($this->acme->id, Stage::member($member), 'post.publish', $work);
                self::fail('A member without the permission acted on behalf.');
            } catch (PermissionDenied|NotAMember $refused) {
                self::assertInstanceOf($exception, $refused);
            }
        }

        $this->stage->organizations->archive($this->acme->id, Stage::member('ann'));

        try {
            $this->stage->onBehalf->act($this->acme->id, Stage::member('ann'), 'post.publish', $work);
            self::fail('Someone acted on behalf of an archived organization.');
        } catch (OrganizationArchived) {
        }

        self::assertFalse($ran);
    }

    public function testTheActionIsNamed(): void
    {
        foreach (['', 'Post', '1post', 'post publish', str_repeat('a', 65)] as $action) {
            try {
                $this->stage->onBehalf->act($this->acme->id, Stage::member('ann'), $action, static fn (): null => null);
                self::fail('A bad action was taken.');
            } catch (InvalidOrganization $invalidOrganization) {
                self::assertSame(\sprintf('An action is 1 to 64 lowercase letters, digits, "_", "." or "-", starting with a letter; "%s" is not.', $action), $invalidOrganization->getMessage());
            }
        }

        self::assertNull($this->stage->onBehalf->act($this->acme->id, Stage::member('ann'), str_repeat('a', 64), static fn (): null => null));
    }

    public function testResetForgetsTheContext(): void
    {
        $this->stage->onBehalf->act($this->acme->id, Stage::member('ann'), 'post.publish', function (): void {
            $this->stage->onBehalf->reset();
            self::assertNull($this->stage->onBehalf->current());
        });

        self::assertNull($this->stage->onBehalf->current());

        $silent = new OnBehalf($this->stage->access, $this->stage->clock);
        self::assertSame(1, $silent->act($this->acme->id, Stage::member('ann'), 'post.publish', static fn (): int => 1));
    }

    private function failingWork(): string
    {
        throw new RuntimeException('Failed.');
    }
}
