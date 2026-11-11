<?php

declare(strict_types=1);

namespace Phil\ConsoleBundle\Tests\Unit\Domain;

use Phil\ConsoleBundle\Domain\Attribute\ConfirmEnvironment;
use Phil\ConsoleBundle\Domain\Attribute\ForbidEnvironment;
use Phil\ConsoleBundle\Domain\ValueObject\EnvironmentGuards;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(EnvironmentGuards::class)]
final class EnvironmentGuardsTest extends TestCase
{
    public function testNoAttribute(): void
    {
        $guards = EnvironmentGuards::of([]);

        self::assertTrue($guards->isEmpty());
        self::assertFalse($guards->forbids('prod'));
        self::assertFalse($guards->asksConfirmationIn('prod'));
    }

    public function testTheEnvironmentsAddUp(): void
    {
        $guards = EnvironmentGuards::of([
            new ForbidEnvironment('prod'),
            new ConfirmEnvironment('staging'),
            new ForbidEnvironment('test', 'prod'),
            new ConfirmEnvironment('dev', 'staging'),
        ]);

        self::assertSame(['prod', 'test'], $guards->forbidden);
        self::assertSame(['staging', 'dev'], $guards->confirmed);
        self::assertFalse($guards->confirmEvery);
        self::assertFalse($guards->isEmpty());
        self::assertTrue($guards->forbids('test'));
        self::assertFalse($guards->forbids('staging'));
        self::assertTrue($guards->asksConfirmationIn('dev'));
        self::assertFalse($guards->asksConfirmationIn('prod'));
    }

    public function testAConfirmationWithoutEnvironmentIsAskedEverywhere(): void
    {
        $guards = EnvironmentGuards::of([new ConfirmEnvironment(), new ConfirmEnvironment('prod')]);

        self::assertTrue($guards->confirmEvery);
        self::assertSame(['prod'], $guards->confirmed);
        self::assertTrue($guards->asksConfirmationIn('anything'));
        self::assertFalse($guards->isEmpty());

        $later = EnvironmentGuards::of([new ConfirmEnvironment('prod'), new ConfirmEnvironment()]);
        self::assertTrue($later->confirmEvery);
    }

    public function testEveryEnvironmentOfEveryAttributeIsKeptOnceAsAList(): void
    {
        $guards = EnvironmentGuards::of([
            new ForbidEnvironment('prod'),
            new ForbidEnvironment('prod', 'test'),
            new ForbidEnvironment('dev', 'staging'),
            new ConfirmEnvironment('a'),
            new ConfirmEnvironment('a', 'b'),
            new ConfirmEnvironment('c', 'd'),
        ]);

        self::assertSame(['prod', 'test', 'dev', 'staging'], $guards->forbidden);
        self::assertSame(['a', 'b', 'c', 'd'], $guards->confirmed);
    }

    public function testEachKindAloneIsNotEmpty(): void
    {
        self::assertFalse(EnvironmentGuards::of([new ForbidEnvironment('prod')])->isEmpty());
        self::assertFalse(EnvironmentGuards::of([new ConfirmEnvironment('prod')])->isEmpty());
        self::assertFalse(EnvironmentGuards::of([new ConfirmEnvironment()])->isEmpty());
    }
}
